<?php
namespace Bediq\Cli;

use Symfony\Component\Yaml\Yaml;

/**
 * Site
 */
class Site
{
    /**
     * The site name
     *
     * @var string
     */
    private $sitename;

    /**
     * The type of the site
     *
     * Allowed types are: php, wordpress, static, laravel, bedrock
     *
     * @var string
     */
    private $type;

    /**
     * The PHP version
     *
     * @var string
     */
    private $php;

    /**
     * The webroot of the site
     *
     * @var string
     */
    private $root;

    public function __construct($sitename)
    {
        $this->sitename = $sitename;
    }

    /**
     * Create a site
     *
     * @return void
     */
    public function create($type)
    {
        $this->type = $type;
        $this->php  = $php;
        $this->root = $root;

        $this->configureSite();
        $this->enable();
        $this->addHost();

        (new Configuration())->addSite($this->sitename, ['something', 'other']);
    }

    /**
     * Delete the site
     *
     * @return void
     */
    public function delete()
    {
        $this->disable();
        $this->deleteHost();
        $this->deleteFolder();

        (new Configuration())->removeSite($this->sitename);
    }

    /**
     * Bootstrap the site folder
     *
     * @return void
     */
    private function configureSite()
    {
        $this->copyFiles();
        $this->generateDockerCompose();
    }

    private function copyFiles()
    {
        output('Copying files');

        $files   = new Filesystem();
        $confDir = __DIR__ . '/../../configs';
        $siteDir = Configuration::sitePath() . '/' . $this->sitename;

        $files->ensureDirExists($siteDir, user());
        $files->ensureDirExists($siteDir . '/app', user());
        $files->ensureDirExists($siteDir . '/conf', user());
        $files->ensureDirExists($siteDir . '/data', user());
        $files->ensureDirExists($siteDir . '/data/logs', user());
        $files->ensureDirExists($siteDir . '/data/mysql', user());
        $files->ensureDirExists($siteDir . '/data/backups', user());
        $files->ensureDirExists($siteDir . '/data/nginx-cache', user());

        // nginx conf
        $files->copy($confDir . '/.env.example', $siteDir . '/.env');
        $files->copyDir($confDir . '/default/config', $siteDir . '/conf');

        // replace nginx hostname
        $nginxConf = $siteDir . '/conf/nginx/default.conf';
        $nginxCont = $files->get($nginxConf);
        $nginxCont = str_replace('NGINX_HOST', $this->sitename, $nginxCont);
        $files->put($nginxConf, $nginxCont);

        // default index.php
        $files->put($siteDir . '/app/index.php', '<?php phpinfo();');
    }

    private function generateDockerCompose()
    {
        output('Generating docker-compose.yml');

        $files   = new Filesystem();
        $siteDir = Configuration::sitePath() . '/' . $this->sitename;

        $config = [
            'version' => '3',
            'services' => [],
            'networks' => [
                'site-network' => [
                    'external' => [
                        'name' => $this->sitename
                    ]
                ]
            ]
        ];

        // $config['services']['redis'] = [
        //     'image'          => 'redis:alpine',
        //     'container_name' => 'redis',
        //     'restart'        => 'always',
        //     'ports'          => [ '6379:6379' ]
        // ];

        $config['services']['nginx'] = [
            'image'       => 'nginx:alpine',
            'restart'     => 'always',
            'environment' => [
                'VIRTUAL_HOST=' . $this->sitename
            ],
            'volumes' => [
                './app:/var/www/html',
                './conf/nginx/common:/etc/nginx/common',
                './conf/nginx/default.conf:/etc/nginx/conf.d/default.conf',
                './conf/nginx/nginx.conf:/etc/nginx/nginx.conf',
                './data/logs/nginx:/var/log/nginx',
                './data/nginx-cache:/var/run/nginx-cache'
            ],
            'depends_on' => [ 'php' ],
            'networks' => ['site-network']
        ];

        $config['services']['php'] = [
            'image'   => 'nanoninja/php-fpm:latest',
            'volumes' => [
                './app:/var/www/html'
            ],
            'networks' => ['site-network']
        ];

        // $config['services']['mariadb'] = [
        //     'image'          => 'bitnami/mariadb:latest',
        //     'restart'        => 'always',
        //     'ports'          => [ '3306:3306' ],
        //     'environment'    => [
        //         'MARIADB_ROOT_USER=${MYSQL_ROOT_USER}',
        //         'MARIADB_ROOT_PASSWORD=${MYSQL_ROOT_PASSWORD}',
        //         'MARIADB_DATABASE=${MYSQL_DATABASE}',
        //         'MARIADB_USER=${MYSQL_USER}',
        //         'MARIADB_PASSWORD=${MYSQL_PASSWORD}'
        //     ],
        //     'volumes'        => [
        //         './data/mysql:/bitnami'
        //     ]
        // ];

        $yaml = Yaml::dump($config, 4, 2);
        $files->put($siteDir . '/docker-compose.yml', $yaml);
    }

    /**
     * Enable a site
     *
     * @return void
     */
    public function enable()
    {
        $docker = new Docker();

        try {
            output('Creating network: ' . $this->sitename);
            $docker->createNetwork($this->sitename);

            output('Connecting "' . $this->sitename . '" network to "nginx-proxy"');
            $docker->connectNetwork($this->sitename);

            output('Running docker-compose up -d');
            $docker->composeUp($this->sitename);
        } catch (Exception $e) {
            warning($e->getMessage());
        }
    }

    /**
     * Disable a site
     *
     * @return void
     */
    public function disable()
    {
        $docker = new Docker();

        try {
            output('Taking down docker-compose');
            $docker->composeDown($this->sitename);

            output('Disconnecting from "nginx-proxy" network');
            $docker->disconnectNetwork($this->sitename);

            output('Removing the network: ' . $this->sitename);
            $docker->removeNetwork($this->sitename);
        } catch (Exception $e) {
            warning($e->getMessage());
        }
    }

    /**
     * Update the host entry
     *
     * @return void
     */
    private function addHost()
    {
        $path       = '/etc/hosts';
        $line       = "\n127.0.0.1\t$this->sitename";

        $filesystem = new Filesystem();
        $content    = $filesystem->get($path);

        if (! preg_match("/\s+$this->sitename\$/m", $content)) {
            // $filesystem->append( $path, $line );
            $cli = new CommandLine();
            $cli->run('echo "' . $line . '" | sudo tee -a ' . $path);

            info('Host entry successfully added.');
        } else {
            warning('Host entry already exists. Skipped.');
        }
    }

    private function deleteHost()
    {
        output('Deleting Hosts file entry');
        $path       = '/etc/hosts';
        $line       = "127.0.0.1\t$this->sitename";

        $filesystem = new Filesystem();
        $content    = $filesystem->get($path);

        if (preg_match("/\s+$this->sitename\$/m", $content)) {
            $cli = new CommandLine();
            $cli->run('sudo sed -i "" "/^' . $line . '/d" ' . $path);
        }
    }

    private function deleteFolder()
    {
        output('Deleting site folder');

        $siteDir = Configuration::sitePath() . '/' . $this->sitename;

        $cli = new CommandLine();
        $cli->run('rm -rf ' . $siteDir);
    }
}
