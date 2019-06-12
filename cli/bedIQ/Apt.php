<?php

namespace Bediq\Cli;

use Symfony\Component\Process\Process;
use DomainException;

class Apt
{
    private $cli;
    private $file;

    public function __construct()
    {
        $this->cli  = new CommandLine();
        $this->file = new Filesystem();
    }

    /**
     * Determine if the given package is installed.
     *
     * @param  string  $package
     * @return bool
     */
    public function installed($package, $container = '')
    {
        return $this->cli->runAsUser($this->containerExec($container) . 'which ' . $package) != '';
    }

    /**
     * Get the lxc exec command which container prefix
     *
     * @param  string $container
     *
     * @return string
     */
    public function containerExec($container = '')
    {
        return $container ? 'lxc exec ' . $container . ' -- ' : '';
    }

    /**
     * Ensure that the given package is installed.
     *
     * @param  string  $package
     * @param  array  $options
     * @param  array  $taps
     *
     * @return void
     */
    public function ensureInstalled($package, $options = [], $taps = [])
    {
        if (! $this->installed($package)) {
            $this->installOrFail($package, $options, $taps);
        }
    }

    /**
     * Install the given package and throw an exception on failure.
     *
     * @param  string  $package
     * @param  array  $options
     * @param  array  $taps
     *
     * @return void
     */
    public function installOrFail($package, $options = [], $taps = [])
    {
        info("Installing {$package}...");

        $this->cli->runAsUser(trim('sudo apt-get install -y '.$package.' '.implode(' ', $options)), function ($exitCode, $errorOutput) use ($package) {
            output($errorOutput);

            throw new DomainException('Apt was unable to install ['.$package.'].');
        });
    }

    /**
     * Determine if a compatible nginx version is Homebrewed.
     *
     * @return bool
     */
    public function hasInstalledNginx($container = '')
    {
        return $this->installed('nginx', $container);
    }

    /**
     * Determine if a compatible nginx version is Homebrewed.
     *
     * @return bool
     */
    public function hasInstalledMysql($container = '')
    {
        return $this->installed('mysql', $container);
    }

    /**
     * Determine if a compatible nginx version is Homebrewed.
     *
     * @return bool
     */
    public function hasInstalledPhp($container = '')
    {
        return $this->installed('php', $container);
    }

    /**
     * Ensure nginx is installed
     *
     * @return void
     */
    public function ensureNginxInstalled($container = '')
    {
        if (!$this->hasInstalledNginx($container)) {
            info("Installing nginx...");

            $prefix = $this->containerExec($container);

            $this->cli->quietly($prefix . 'apt-add-repository ppa:nginx/mainline -y');
            $this->cli->quietly($prefix . 'apt-get update');
            $this->cli->quietly($prefix . 'apt install -y nginx');
        } else {
            warning("nginx already installed.");
        }
    }

    /**
     * Ensure nginx is installed
     *
     * @return void
     */
    public function ensureMysqlInstalled($container = '')
    {
        if (!$this->hasInstalledMysql($container)) {
            info("Installing mariadb...");

            $prefix = $this->containerExec($container);

            $this->cli->quietly($prefix . 'apt-key adv --recv-keys --keyserver hkp://keyserver.ubuntu.com:80 0xF1656F24C74CD1D8');
            $this->cli->quietly($prefix . 'apt-add-repository "deb [arch=amd64,i386] http://nyc2.mirrors.digitalocean.com/mariadb/repo/10.3/ubuntu bionic main"');
            $this->cli->quietly($prefix . 'apt-get update');
            $this->cli->quietly($prefix . 'apt install -y mariadb-server mariadb-client');
        } else {
            warning("mariadb already installed.");
        }
    }

    /**
     * Ensure nginx is installed
     *
     * @return void
     */
    public function ensurePhpInstalled($container = '')
    {
        if (!$this->hasInstalledPhp($container)) {
            info("Installing PHP...");

            $prefix = $this->containerExec($container);

            $this->cli->quietly($prefix . 'apt-add-repository ppa:ondrej/php -y');
            $this->cli->quietly($prefix . 'apt-get update');
            $this->cli->quietly($prefix . 'apt-get install -y php7.3-cli php7.3-common php7.3-curl php7.3-dev php7.3-fpm php7.3-gd php7.3-mbstring php7.3-mysql php7.3-opcache php7.3-xml php7.3-xmlrpc php7.3-zip');
        } else {
            warning("PHP already installed.");
        }
    }

    /**
     * Restart the given Homebrew services.
     *
     * @param
     */
    public function restartService($services)
    {
        $services = is_array($services) ? $services : func_get_args();

        foreach ($services as $service) {
            if ($this->installed($service)) {
                info("Restarting {$service}...");
                $this->cli->runCommand('service ' . $service . ' restart');
            } else {
                warning("Service {$service} not installed");
            }
        }
    }

    /**
     * Stop the given Homebrew services.
     *
     * @param
     */
    public function stopService($services)
    {
        $services = is_array($services) ? $services : func_get_args();

        foreach ($services as $service) {
            if ($this->installed($service)) {
                info("Stopping {$service}...");

                $this->cli->quietly('services ' . $service . ' stop');
            }
        }
    }
}
