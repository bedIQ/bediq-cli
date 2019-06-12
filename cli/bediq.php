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
    // $apt = new Apt();
    // $apt->ensureNginxInstalled();
    $nginx = new Nginx();

    $isWp = false;
    $nginx->createSite('hello.com', $isWp);
    // $nginx->removeSite('hello.com', $isWp);
});

$app->command('provision type', function($type) {

    $cli       = new CommandLine();
    $cli->quietly('ls');
    // $file      = new Filesystem();
    // $provision = new Provision($cli, $file);

    // if (!in_array($type, ['vm', 'container'])) {
    //     error('Invalid type provided. Use "vm" or "container"');
    //     return;
    // }

    // $container = 'base';
    // $mysqlPass = 'root';

    // $apt = new Apt();
    // $apt->ensurePhpInstalled($container);
    // $apt->ensureNginxInstalled($container);
    // $apt->ensureMysqlInstalled($mysqlPass, $container);

    // if ($output->isVerbose()) {
    //     $output->writeln("hello");
    // }

    // $provision->install();
    // $lxc = new Lxc($cli, $file);
    // $domain = 'hello-com';
    // var_dump( $lxc->launch($domain) );
    // $lxc->remove($domain);
    // output($lxc->getIp($domain));

    info( "bedIQ installed" );

})->descriptions('Provision the bediq VM', [
    'type' => 'Type of server. "vm" or "container"'
]);

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
