<?php
namespace Bediq\Cli;

/**
 * Configuration class
 */
class Provision
{
    private $files;
    private $cli;

    public function __construct(CommandLine $cli, Filesystem $files)
    {
        $this->files = $files;
        $this->cli   = $cli;
    }

    public function install()
    {
        $this->writeBaseConfiguration();
        $this->unattendedUpgrades();
    }

    public function createSitesDirectory()
    {
        $this->files->ensureDirExists(self::sitePath(), user());
    }

    public function writeBaseConfiguration()
    {
        if (! $this->files->exists($this->path())) {
            $this->write([
                'sites' => []
            ]);
        }
    }

    /**
     * Add the given path to the configuration.
     *
     * @param  string  $path
     * @param  array  $info
     * @return void
     */
    public function addSite($site, $info = [])
    {
        $this->write(tap($this->read(), function (&$config) use ($site, $info) {
            $config['sites'][$site] = $info;
        }));
    }

    /**
     * Remove the given path from the configuration.
     *
     * @param  string  $path
     * @return void
     */
    public function removeSite($site)
    {
        $this->write(tap($this->read(), function (&$config) use ($site) {
            unset($config['sites'][$site]);
        }));
    }

    /**
     * Read the configuration file as JSON.
     *
     * @return array
     */
    public function read()
    {
        return json_decode($this->files->get($this->path()), true);
    }

    /**
     * Update a specific key in the configuration file.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return array
     */
    public function updateKey($key, $value)
    {
        return tap($this->read(), function (&$config) use ($key, $value) {
            $config[$key] = $value;
            $this->write($config);
        });
    }

    /**
     * Write the given configuration to disk.
     *
     * @param  array  $config
     * @return void
     */
    public function write($config)
    {
        $this->files->putAsUser($this->path(), json_encode(
            $config,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        ) . PHP_EOL);
    }

    public function unattendedUpgrades()
    {
        $this->files->copy(BEDIQ_STUBS . '/apt/50unattended-upgrades', '/etc/apt/apt.conf.d/50unattended-upgrades');
        $this->files->copy(BEDIQ_STUBS . '/apt/10periodic', '/etc/apt/apt.conf.d/10periodic');
    }

    private function path()
    {
        return '/root/bediq.json';
    }

    public static function sitePath()
    {
        return $_SERVER['HOME'] . '/sites';
    }
}
