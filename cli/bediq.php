#!/usr/bin/env php
<?php

/**
 * Load correct autoloader depending on install location.
 */
if ( file_exists( __DIR__ . '/../vendor/autoload.php' ) ) {
    require __DIR__ . '/../vendor/autoload.php';
} else {
    require __DIR__ . '/../../../autoload.php';
}

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

use function Bediq\Cli\info;
use function Bediq\Cli\error;
use function Bediq\Cli\output;
use function Bediq\Cli\table;
use function Bediq\Cli\warning;

define( 'BEDIQ_STUBS', __DIR__ . '/stubs' );

$version = '1.0';

$app = new Application('Ubuntu server management cli interface for bedIQ', $version);

$app->command('test', function() {
    $file = new Filesystem();
    $cli  = new CommandLine();
    $apt  = new Apt($cli, $file);
    $lxd  = new Lxc($cli, $file);
    $wp   = new WP($cli, $file);

    $container = 'base';
    $path      = '/var/www/html/';

    $config = [
        'dbname' => 'bediq',
        'dbuser' => 'root',
        'dbpass' => 'root',
        'id'     => 'the-site-id',
        'key'    => 'do-key',
        'secret' => 'do-secret',
    ];

    // site details
    $url      = 'bediq-wp-base.com';
    $title    = 'bedIQ Test Site';
    $username = 'admin';
    $pass     = 'admin';
    $email    = 'tareq1988@gmail.com';

    $plugins = ['weforms', 'advanced-custom-fields'];
    $themes  = ['hestia'];
    // $wp->download($container, $path);
    // $wp->generateConfig($container, $config, $path);
    // $wp->install($container, $path, $url, $title, $username, $pass, $email);
    // $wp->installPlugins($container, $path, $plugins);
    // $wp->installThemes($container, $path, $themes);
    // $wp->backup($container, $path);
});

$app->command('provision:vm', function(SymfonyStyle $io) {

    $cli       = new CommandLine();
    $file      = new Filesystem();
    $nginx     = new Nginx($cli, $file);
    $provision = new Provision($cli, $file);
    $apt       = new Apt($cli, $file);
    $lxd       = new Lxc($cli, $file);

    $cli->run('apt-get update && apt-get upgrade -y');

    // install software-properties-common
    $cli->quietly('apt-get install -y software-properties-common');

    // install vim
    $apt->ensureInstalled('vim');

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
    $provision->unattendedUpgrades();

    // Disable The Default Nginx Site
    $nginx->tweakConfig();
    $nginx->addCatchAll();
    $nginx->removeDefault();

    # Install LXD and launch container
    if (!$apt->installed('zpool')) {
        $apt->installOrFail('zfsutils-linux');
    }

    # launch LXD container
    $lxd->init();
    $ip = $lxd->launch('base');

    // info('Base IP address: ' . $ip);

    info( "bedIQ installed" );

})->descriptions('Provision the bediq VM');

$app->command('provision:container container', function($container) {

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

    output('Installing software-properties-common...');
    $cli->quietly('apt-get install -y software-properties-common');

    $apt->ensureNginxInstalled($container);
    $apt->ensurePhpInstalled($container);
    $apt->ensureMysqlInstalled('root', $container);
    $apt->ensureWpInstalled($container);

    output('Configuring nginx...');
    $nginx->tweakConfig($container);

    // copy PHP optimized .ini settings
    output('Optimizing PHP...');
    $lxd->pushFile($container, BEDIQ_STUBS . '/php/php.ini', '/etc/php/7.3/fpm/conf.d/30-bediq');
    $lxd->restartService($container, 'php7.3-fpm');

})->descriptions('Provision the LXD container');

$app->command('site:create domain [--type=]', function ($domain, $type) {

    $allowedTypes = ['static', 'wp'];

    if (!in_array($type, $allowedTypes)) {
        throw new Exception('Invalid supported site type');
    }

    $cli   = new CommandLine();
    $file  = new Filesystem();
    $nginx = new Nginx($cli, $file);
    $lxd   = new Lxc($cli, $file);

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

        $nginx->createSite($domain);
    } else {
        if ($nginx->siteExists($domain)) {
            throw new Exception('Site already exists');
        }

        $container = $lxd->nameByDomain($domain);

        // check if the container exists
        // if not, launch a new one
        if (!$lxd->exists($container)) {

            $ip = $lxd->launch($container);

            $this->runCommand("provision:container {$container}");

        } else {

            if (!$lxd->isRunning($container)) {
                $lxd->start($container);
            }

            $ip = $lxd->getIp($container);
        }

        output("Container '{$container}' has IP: {$ip}");

        $wp   = new WP($cli, $file);
        $path = '/var/www/html/';

        $config = [
            'dbname' => 'bediq',
            'dbuser' => 'root',
            'dbpass' => 'root',
            'id'     => 'the-site-id',
            'key'    => 'do-key',
            'secret' => 'do-secret',
        ];

        // site details
        $title    = 'bedIQ Test Site';
        $username = 'admin';
        $pass     = 'admin';
        $email    = 'tareq1988@gmail.com';

        $plugins = ['weforms', 'advanced-custom-fields'];
        $themes  = ['hestia'];

        output('Downloading WordPress...');
        $wp->download($container, $path);
        $wp->generateConfig($container, $config, $path);

        output('Installing WordPress...');
        $wp->install($container, $path, $domain, $title, $username, $pass, $email);

        output('Installing plugins...');
        $wp->installPlugins($container, $path, $plugins);

        output('Installing themes...');
        $wp->installThemes($container, $path, $themes);

        output('Create nginx proxy on VM...');
        $nginx->createWpProxy($domain, $ip);

        $nginx->createDefaultWp($container);
    }

    // $sites = new Site( $site );
    // $sites->create( $type, $php, $root );

    info( "Site '{$domain}' with '{$type}' created" );

})->descriptions('Create a new site', [
    'domain' => 'The url of the site without http(s)',
    '--type' => 'Type of the site. e.g. static, wp',
])
->defaults([
    'type' => 'wp', // wp, static
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

    info( "Site $domain deleted" );

})->descriptions('Delete the site.');

$app->run();
