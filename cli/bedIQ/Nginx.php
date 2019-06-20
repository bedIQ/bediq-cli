<?php

namespace Bediq\Cli;

class Nginx
{
    private $cli;
    private $files;

    public function __construct(CommandLine $cli, Filesystem $files)
    {
        $this->cli   = $cli;
        $this->files = $files;
    }

    /**
     * Tweak server Nginx config
     *
     * @param  string $container
     *
     * @return void
     */
    public function tweakConfig($container = '')
    {
        if ($container) {
            $lxc = new Lxc($this->cli, $this->files);

            $lxc->pushFile($container, BEDIQ_STUBS . '/nginx/nginx.conf', '/etc/nginx/nginx.conf');

            // put common configs
            $lxc->exec($container, 'mkdir /etc/nginx/common');
            $lxc->pushFile($container, BEDIQ_STUBS . '/nginx/common/general.conf', '/etc/nginx/common/general.conf');
            $lxc->pushFile($container, BEDIQ_STUBS . '/nginx/common/php_fastcgi.conf', '/etc/nginx/common/php_fastcgi.conf');
            $lxc->pushFile($container, BEDIQ_STUBS . '/nginx/common/wordpress.conf', '/etc/nginx/common/wordpress.conf');
        } else {
            $this->files->copy(BEDIQ_STUBS . '/nginx/nginx.conf', '/etc/nginx/nginx.conf');

            // put common configs
            $this->files->ensureDirExists('/etc/nginx/common');
            $this->files->copy(BEDIQ_STUBS . '/nginx/common/general.conf', '/etc/nginx/common/general.conf');
        }
    }

    /**
     * Create a static site in the main VM
     *
     * @param  void $domain
     *
     * @return void
     */
    public function createStaticSite($domain)
    {
        $domain = strtolower($domain);

        output("Creating nginx entry for {$domain}...");

        $config = $this->files->get(BEDIQ_STUBS . '/nginx/site/static.conf');
        $config = str_replace('{domain}', $domain, $config);

        $this->files->put('/etc/nginx/sites-available/' . $domain, $config);
        $this->files->symlink('/etc/nginx/sites-available/' . $domain, '/etc/nginx/sites-enabled/' . $domain);

        $this->addHostEntry($domain);

        $this->reloadNginx();
    }

    /**
     * Add domain to hosts file
     *
     * @param string $domain
     */
    public function addHostEntry($domain)
    {
        $hostFile   = '/etc/hosts';
        $line       = "127.0.0.1\t{$domain}\n";
        $content    = $this->files->get( $hostFile );

        if ( ! preg_match( "/\s+{$domain}\$/m", $content ) ) {
            $this->cli->run( 'echo "' . $line . '" | sudo tee -a ' . $hostFile );
            output( 'Added domain to /etc/hosts' );
        }
    }

    /**
     * Remove domain from host entry
     *
     * @param  string $domain
     *
     * @return void
     */
    public function removeHostEntry($domain)
    {
        $hostFile   = '/etc/hosts';
        $content    = $this->files->get( $hostFile );

        if ( preg_match( "/\s+{$domain}\$/m", $content ) ) {
            $this->cli->run( 'sed -ie "/[[:space:]]' . $domain . '/d" ' . $hostFile );
            info('Removed host entry');
        } else {
            warning('Could not remove');
        }
    }

    /**
     * Update domain on a static site
     *
     * @param  string $domain
     * @param  string $extraDomain
     *
     * @return void
     */
    public function updateStaticDomain($domain, $extraDomain)
    {
        $config = $this->files->get(BEDIQ_STUBS . '/nginx/site/static.conf');
        $config = str_replace('server_name {domain}', 'server_name ' . $domain . ' ' . $extraDomain, $config);
        $config = str_replace('{domain}', $domain, $config);

        $this->files->put('/etc/nginx/sites-available/' . $domain, $config);
        $this->reloadNginx();
    }

    /**
     * Create a WP proxy site in the main VM
     *
     * @param  string $domain
     * @param  string $ip
     *
     * @return void
     */
    public function createWpProxy($domain, $ip)
    {
        $domain = strtolower($domain);

        output("Creating nginx entry for {$domain}...");

        $config = $this->files->get(BEDIQ_STUBS . '/nginx/site/wp-proxy.conf');
        $config = str_replace('{domain}', $domain, $config);
        $config = str_replace('{ip}', $ip, $config);

        $this->files->put('/etc/nginx/sites-available/' . $domain, $config);
        $this->files->symlink('/etc/nginx/sites-available/' . $domain, '/etc/nginx/sites-enabled/' . $domain);

        $this->addHostEntry($domain);

        $this->reloadNginx();
    }

    /**
     * Create the default WordPress nginx vhost
     *
     * @param  string $container
     *
     * @return void
     */
    public function createDefaultWp($container)
    {
        $lxc = new Lxc($this->cli, $this->files);
        $lxc->pushFile($container, BEDIQ_STUBS . '/nginx/site/wp.conf', '/etc/nginx/sites-available/default');

        $lxc->restartService($container, 'nginx');
    }

    /**
     * Remove default nginx server
     *
     * @return void
     */
    public function removeDefault()
    {
        if ($this->files->exists('/etc/nginx/sites-available/default')) {
            $this->removeSite('default');
        }
    }

    /**
     * Check if a site exists
     *
     * @param  string $domain
     *
     * @return boolean
     */
    public function siteExists($domain)
    {
        return $this->files->exists('/etc/nginx/sites-available/' . $domain);
    }

    /**
     * Add a catch all server block
     */
    public function addCatchAll()
    {
        if (!$this->files->exists('/etc/nginx/sites-available/catch-all')) {
            $this->files->copy(BEDIQ_STUBS . '/nginx/site/catch-all.conf', '/etc/nginx/sites-available/catch-all');
            $this->files->symlink('/etc/nginx/sites-available/catch-all', '/etc/nginx/sites-enabled/catch-all');
        }
    }

    /**
     * Remove a site from nginx
     *
     * @param  string  $domain
     *
     * @return void
     */
    public function removeSite($domain)
    {
        $domain   = strtolower($domain);

        output("Removing nginx entry for {$domain}...");

        $this->files->unlink('/etc/nginx/sites-available/' . $domain);
        $this->files->unlink('/etc/nginx/sites-enabled/' . $domain);

        $this->removeHostEntry($domain);

        $this->reloadNginx();
    }

    /**
     * Reload nginx
     *
     * @return void
     */
    public function reloadNginx()
    {
        (new Apt(new CommandLine(), $this->files))->restartService('nginx');
    }
}
