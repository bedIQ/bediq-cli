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
    function installed($package)
    {
        return $this->cli->runAsUser('which ' . $package) != '';
    }

    /**
     * Ensure that the given package is installed.
     *
     * @param  string  $package
     * @param  array  $options
     * @param  array  $taps
     * @return void
     */
    function ensureInstalled($package, $options = [], $taps = [])
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
     * @return void
     */
    function installOrFail($package, $options = [], $taps = [])
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
    function hasInstalledNginx()
    {
        return $this->installed('nginx');
    }

    /**
     * Ensure nginx is installed
     *
     * @return void
     */
    function ensureNginxInstalled()
    {
        if (!$this->hasInstalledNginx()) {
            info("Installing nginx...");
            $this->cli->quietly('sudo apt-add-repository ppa:nginx/development -y');
            $this->cli->quietly('sudo apt-get update');
            $this->cli->quietly('apt install -y nginx');
        } else {
            warning("nginx already installed.");
        }
    }

    /**
     * Restart the given Homebrew services.
     *
     * @param
     */
    function restartService($services)
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
    function stopService($services)
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
