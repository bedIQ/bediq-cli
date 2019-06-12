<?php

namespace Bediq\Cli;

class Nginx
{
    private $cli;
    private $files;

    public function __construct(Filesystem $files)
    {
        $this->files = $files;
    }

    public function tweakConfig()
    {
        $this->files->copy(BEDIQ_STUBS . '/nginx/nginx.conf', '/etc/nginx/nginx.conf');

        // put common configs
        $this->files->ensureDirExists('/etc/nginx/common');
        $this->files->copy(BEDIQ_STUBS . '/nginx/common/general.conf', '/etc/nginx/common/general.conf');
        $this->files->copy(BEDIQ_STUBS . '/nginx/common/php_fastcgi.conf', '/etc/nginx/common/php_fastcgi.conf');
        $this->files->copy(BEDIQ_STUBS . '/nginx/common/wordpress.conf', '/etc/nginx/common/wordpress.conf');
    }

    public function createSite($domain, $wp = false)
    {
        $domain   = strtolower($domain);
        $filename = $wp ? 'default' : $domain;
        $stub     = $wp? 'wp.conf' : 'static.conf';

        info("Creating nginx entry for {$domain}...");

        $config = $this->files->get(BEDIQ_STUBS . '/nginx/site/' . $stub);
        $config = str_replace('{domain}', $domain, $config);

        $this->files->put('/etc/nginx/sites-available/' . $filename, $config);
        $this->files->symlink('/etc/nginx/sites-available/' . $filename, '/etc/nginx/sites-enabled/' . $filename);

        $this->reloadNginx();
    }

    public function removeDefault()
    {
        if ($this->files->exists('/etc/nginx/sites-available/default')) {
            $this->removeSite('default');
        }
    }

    public function addCatchAll()
    {
        if (!$this->files->exists('/etc/nginx/sites-available/catch-all')) {
            $this->files->copy(BEDIQ_STUBS . '/nginx/site/catch-all.conf', '/etc/nginx/sites-available/catch-all');
            $this->files->symlink('/etc/nginx/sites-available/catch-all', '/etc/nginx/sites-enabled/catch-all');
        }
    }

    public function removeSite($domain, $wp = false)
    {
        $domain   = strtolower($domain);
        $filename = $wp ? 'default' : $domain;

        info("Removing nginx entry for {$domain}...");

        $this->files->unlink('/etc/nginx/sites-available/' . $filename);
        $this->files->unlink('/etc/nginx/sites-enabled/' . $filename);

        $this->reloadNginx();
    }

    public function reloadNginx()
    {
        (new Apt(new CommandLine(), $this->files))->restartService('nginx');
    }
}
