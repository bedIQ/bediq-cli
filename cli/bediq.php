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

    $apt->ensureWpInstalled('base');
});

$app->command('provision:vm', function(SymfonyStyle $io) {

    $cli       = new CommandLine();
    $file      = new Filesystem();
    $nginx     = new Nginx($file);
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

$app->command('provision:container container', function($container, SymfonyStyle $io) {

    $cli       = new CommandLine();
    $file      = new Filesystem();
    $nginx     = new Nginx($file);
    $provision = new Provision($cli, $file);
    $apt       = new Apt($cli, $file);
    $lxd       = new Lxc($cli, $file);

    if (!$lxd->containerExists($container)) {
        throw new \Exception("Container {$container} doesn't exist");
    }

    $verbose = $io->isVerbose();

    if (!$lxd->isRunning($container)) {

        if ($verbose) {
            $io->writeln("{$container} is stopped. Starting...");
        }

        $lxd->start($container);
    }

    if ($verbose) {
        $io->writeln("Running update...");
    }

    $lxd->exec($container, 'apt-get update');
    // $lxd->exec($container, 'apt-get upgrade -y');

    $cli->quietly('apt-get install -y software-properties-common');

    $apt->ensureNginxInstalled($container);
    $apt->ensurePhpInstalled($container);
    $apt->ensureMysqlInstalled('root', $container);
    $apt->ensureWpInstalled($container);

})->descriptions('Provision the LXD container');

$app->command('create site [--type=] [--php=] [--root=]', function ($site, $type, $php, $root) {

    $sites = new Site( $site );
    $sites->create( $type, $php, $root );

    info( "Site $site created" );

})->descriptions('Create a new site', [
    'site'   => 'The url of the site',
    '--type' => 'Type of the site.',
])
->defaults([
    'type' => 'php', // wp, bedrock, laravl, static
]);

$app->command('delete name', function ($name) {

    $site = new Site( $name );
    $site->delete();

    info( "Site $name deleted" );

})->descriptions('Delete the site.');

$app->run();
