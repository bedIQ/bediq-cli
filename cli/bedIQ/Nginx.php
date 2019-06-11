<?php

namespace Bediq\Cli;

class Nginx
{
    private $cli;
    private $filesystem;

    public function __construct()
    {
        $this->filesystem = new Filesystem();
    }

    function tweakConfig()
    {
        $this->filesystem->copy(BEDIQ_STUBS . '/nginx/nginx.conf', '/etc/nginx/nginx.conf');

        // put common configs
        $this->filesystem->ensureDirExists('/etc/nginx/common');
        $this->filesystem->copy(BEDIQ_STUBS . '/nginx/common/general.conf', '/etc/nginx/common/general.conf');
        $this->filesystem->copy(BEDIQ_STUBS . '/nginx/common/php_fastcgi.conf', '/etc/nginx/common/php_fastcgi.conf');
        $this->filesystem->copy(BEDIQ_STUBS . '/nginx/common/wordpress.conf', '/etc/nginx/common/wordpress.conf');
    }

    function createSite($domain, $wp = false)
    {
        $domain   = strtolower($domain);
        $filename = $wp ? 'default' : $domain;
        $stub     = $wp? 'wp.conf' : 'static.conf';

        info("Creating nginx entry for {$domain}...");

        $config = $this->filesystem->get(BEDIQ_STUBS . '/nginx/site/' . $stub);
        $config = str_replace('{domain}', $domain, $config);

        $this->filesystem->put('/etc/nginx/sites-available/' . $filename, $config);
        $this->filesystem->symlink('/etc/nginx/sites-available/' . $filename, '/etc/nginx/sites-enabled/' . $filename );

        $this->reloadNginx();
    }

    function removeSite($domain, $wp = false)
    {
        $domain   = strtolower($domain);
        $filename = $wp ? 'default' : $domain;

        info("Removing nginx entry for {$domain}...");

        $this->filesystem->unlink('/etc/nginx/sites-available/' . $filename);
        $this->filesystem->unlink('/etc/nginx/sites-enabled/' . $filename);

        $this->reloadNginx();
    }

    function reloadNginx()
    {
        (new Apt())->restartService('nginx');
    }
}
