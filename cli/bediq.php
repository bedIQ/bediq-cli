#!/usr/bin/env php
<?php

/**
 * Load correct autoloader depending on install location.
 */
if (file_exists(__DIR__ . '/../vendor/autoload.php')) {
    require __DIR__ . '/../vendor/autoload.php';
} else {
    require __DIR__ . '/../../../autoload.php';
}

$dotenv = Dotenv\Dotenv::create(__DIR__.'/../');
$dotenv->load();

use Silly\Application;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Bediq\Cli\Site;
use Bediq\Cli\CommandLine;
use Bediq\Cli\Apt;
use Bediq\Cli\Nginx;
use Bediq\Cli\Provision;
use Bediq\Cli\Filesystem;
use Bediq\Cli\Lxc;
use Bediq\Cli\WP;
use Bediq\Cli\Generate;

use function Bediq\Cli\info;
use function Bediq\Cli\error;
use function Bediq\Cli\output;
use function Bediq\Cli\table;
use function Bediq\Cli\warning;

define('BEDIQ_STUBS', __DIR__ . '/stubs');

$version = '1.0';

$app = new Application('Ubuntu server management cli interface for bedIQ', $version);

$app->command('test', function () {
    $file = new Filesystem();
    $cli  = new CommandLine();
    $apt  = new Apt($cli, $file);
    $lxd  = new Lxc($cli, $file);
    $wp   = new WP($cli, $file);
    $nginx   = new Nginx($cli, $file);

    $path      = '/var/www/html/';
    $container = 'example-com';

    $bediqApi = new \Bediq\Cli\BedIQApi();

    print_r($bediqApi->plugins());
    print_r($bediqApi->themes());
});

$app->command('provision:vm', function (SymfonyStyle $io) {

    $cli       = new CommandLine();
    $file      = new Filesystem();
    $nginx     = new Nginx($cli, $file);
    $provision = new Provision($cli, $file);
    $apt       = new Apt($cli, $file);
    $lxd       = new Lxc($cli, $file);

    // install vim
    // $apt->ensureInstalled('vim');

    // install nginx
    $apt->ensureNginxInstalled();

    // install ufw
    $apt->ensureInstalled('ufw');
    $provision->enableFirewall();

    // configure SSH keys
    if (!$file->exists('~/.ssh/id_rsa')) {
        $cli->quietly('yes y | ssh-keygen -f $HOME/.ssh/id_rsa -t rsa -N ""');
    }

    // create swap disk
    $provision->createSwapFile();

    // Setup Unattended Security Upgrades
    $provision->install();

    // Disable The Default Nginx Site
    $nginx->tweakConfig();
    $nginx->addCatchAll();
    $nginx->removeDefault();

    # Install LXD and launch container
    if (!$apt->installed('zpool')) {
        $apt->installOrFail('zfsutils-linux');
    }

    # launch LXD container
    output('Initializing LXD...');
    $lxd->init();
    $cli->quietly('lxc list');
    $ip = $lxd->launch('base');

    $this->runCommand("provision:container base");

    info("bedIQ installed");
})->descriptions('Provision the bediq VM');

$app->command('container:create container', function ($container) {
    $cli   = new CommandLine();
    $file  = new Filesystem();
    $lxd   = new Lxc($cli, $file);

    $lxd->launch($container);

    info("{$container} created.");
});

$app->command('provision:container container', function ($container) {

    $cli       = new CommandLine();
    $file      = new Filesystem();
    $nginx     = new Nginx($cli, $file);
    $provision = new Provision($cli, $file);
    $apt       = new Apt($cli, $file);
    $lxd       = new Lxc($cli, $file);

    if (!$lxd->exists($container)) {
        throw new \Exception("Container {$container} doesn't exist");
    }

    output('Provisioning started...');

    if (!$lxd->isRunning($container)) {
        if ($verbose) {
            $io->writeln("{$container} is stopped. Starting...");
        }

        $lxd->start($container);
    }

    output('Running update...');
    $lxd->exec($container, 'apt-get update');
    // $lxd->exec($container, 'apt-get upgrade -y');

    $mysqlPass = bin2hex(random_bytes(12));

    output('Installing software-properties-common...');
    $cli->quietly('apt-get install -y software-properties-common');

    $apt->ensureNginxInstalled($container);
    $apt->ensurePhpInstalled($container);
    $apt->ensureMysqlInstalled($mysqlPass, $container);
    $apt->ensureWpInstalled($container);

    output('Configuring nginx...');
    $nginx->tweakConfig($container);

    // copy PHP optimized .ini settings
    output('Optimizing PHP...');
    $lxd->pushFile($container, BEDIQ_STUBS . '/php/php.ini', '/etc/php/7.3/fpm/conf.d/30-bediq');
    $lxd->restartService($container, 'php7.3-fpm');
})->descriptions('Provision the LXD container');

$app->command('site:create domain [--type=] [--title=] [--email=] [--username=] [--password=] [--site-key=] [--site-id=]', function ($domain, $type, $title, $email, $username, $password, $siteKey, $siteId) {

    $allowedTypes = ['static', 'wp'];

    if (!in_array($type, $allowedTypes)) {
        throw new Exception('Invalid supported site type');
    }

    $cli            = new CommandLine();
    $file           = new Filesystem();
    $nginx          = new Nginx($cli, $file);
    $lxd            = new Lxc($cli, $file);
    $bediqApi       = new \Bediq\Cli\BedIQApi();

    if ($type == 'static') {
        $sitePath = Provision::sitePath($domain);

        if ($file->exists($sitePath)) {
            throw new Exception('Site already exists');
        }

        $file->mkdir($sitePath);

        // put a default HTML file
        $html = $file->get(BEDIQ_STUBS . '/site-index.html');
        $html = str_replace('{domain}', $domain, $html);
        $file->put($sitePath . '/index.html', $html);

        $nginx->createStaticSite($domain);
    } else {
        if ($nginx->siteExists($domain)) {
            throw new Exception('Site already exists');
        }

        $required = ['email', 'username', 'password', 'siteKey'];

        foreach ( $required as $reqField ) {
            if (empty($$reqField)) {
                throw new Exception('Missing field: ' . $reqField);
            }
        }

        $container = $lxd->nameByDomain($domain);

        // check if the container exists
        // if not, launch a new one
        if (!$lxd->exists($container)) {

            if ($lxd->exists('base')) {
                output('Copying base to '. $container);
                $ip = $lxd->copyContainer('base', $container);
            } else {
                $ip = $lxd->launch($container);

                $this->runCommand("provision:container {$container}");
            }
        } else {
            if (!$lxd->isRunning($container)) {
                $lxd->start($container);
            }

            $ip = $lxd->getIp($container);
        }

        output("Container '{$container}' has IP: {$ip}");

        $wp   = new WP($cli, $file);
        $path = '/var/www/html/';

        $pass = $lxd->exec($container, 'sh -c "grep \'password=\' ~/.my.cnf"');
        $pass = explode('=', $pass);

        $config = [
            'dbname'  => 'bediq',
            'dbuser'  => 'root',
            'dbpass'  => trim($pass[1]),
            'siteid'  => $siteId,
            'sitekey' => $siteKey,
        ];

        $plugins    = $bediqApi->plugins();

        $themes     = $bediqApi->themes();;

        output('Downloading WordPress...');
        $wp->download($container, $path);
        $wp->generateConfig($container, $config, $path);

        output('Installing WordPress...');
        $wp->install($container, $path, $domain, $title, $username, $password, $email);

        output('Installing plugins...');
        $wp->installPlugins($container, $path, $plugins);

        output('Installing themes...');
        $wp->installThemes($container, $path, $themes);

        output('Activating bediq theme...');
        $wp->activateTheme($container, $path, 'bediq');

        output('Importing MU plugins...');
        $wp->installMUPlugins($container, $path);

        output('Importing default data...');
        $wp->defaultDataImport($container, $path);

        $wp->changeOwner($container);

        output('Create nginx proxy on VM...');
        $nginx->createWpProxy($domain, $ip);

        $nginx->createDefaultWp($container);
    }

    info("Site '{$domain}' with '{$type}' created");
})->descriptions('Create a new site', [
    'domain'     => 'The url of the site without http(s)',
    '--type'     => 'Type of the site. e.g. static, wp',
    '--title'    => 'The site title',
    '--username' => 'The admin account username',
    '--password' => 'The admin account password',
    '--email'    => 'The admin email address',
])
->defaults([
    'type' => 'wp', // wp, static,
    'title' => 'Just another bedIQ site'
]);

$app->command('site:delete domain [--type=]', function ($domain, $type) {

    $file  = new Filesystem();
    $cli   = new CommandLine();
    $nginx = new Nginx($cli, $file);

    if ($type == 'static') {
        $sitePath = Provision::sitePath($domain);

        if ($file->exists($sitePath)) {
            $cli->run('rm -rf ' . $sitePath);
        }

        $nginx->removeSite($domain);
    } else {
        $nginx->removeSite($domain);

        $lxd = new Lxc($cli, $file);
        $container = $lxd->nameByDomain($domain);

        $lxd->stop($container);
        $lxd->remove($container);
    }

    info("Site $domain deleted");
})->descriptions('Delete the site.');

$app->command('site:generate url static [--key=]', function($url, $static, $key) {
    $starttime = microtime(true);

    $generate = new Generate($url, $static, $key);
    $generate->handle();

    $endtime = microtime(true);
    $timediff = $endtime - $starttime;

    output('Time Elapsed: ' . $timediff);

    info('Site generated');
})->descriptions('Generate a static site.', [
    'url'    => 'The url to the WP installation.',
    'static' => 'Domain name of the static site (without http).',
    '--key'  => 'bedIQ site secret key'
]);

$app->command('update:domain domain [extra]', function ($domain, $extra) {

    $file  = new Filesystem();
    $cli   = new CommandLine();
    $nginx = new Nginx($cli, $file);

    $nginx->updateStaticDomain($domain, $extra);

    info('Domain updated');
});

$app->command('db:export domain', function ($domain) {

    $file   = new Filesystem();
    $cli    = new CommandLine();
    $lxd    = new Lxc($cli, $file);
    $wp     = new WP($cli, $file);

    $path = '/var/www/html';

    $container = $lxd->nameByDomain($domain);

    $fileName = $wp->backup($container, $path);
    output($fileName);

})->descriptions('Export database.');

$app->command('site:ssl static_url wp_url', function ($static_url, $wp_url) {
    $cli = new CommandLine();
    $file = new Filesystem();
    $nginx = new Nginx($cli, $file);
    $lxd = new Lxc($cli, $file);

    $container = $lxd->nameByDomain($static_url);
    $ip = $lxd->getIp($container);

    // apply ssl for static
    output("Adding certificate for {$static_url}...");
    $cli->run('certbot certonly -d '.$static_url.'  --nginx');
    $nginx->applySSL($static_url);

    // apply ssl for staging
    output("Adding certificate for {$wp_url}...");
    $cli->run('certbot certonly -d staging-'.c.'  --nginx');
    $nginx->applySSL($wp_url, $ip);

    $nginx->reloadNginx();

    output('SSL applied to '.$static_url.' and '.$wp_url);

})->descriptions('SSL certificate install.');

$app->run();
