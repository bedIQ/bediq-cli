<?php
namespace Bediq\Cli;

/**
 * Configuration class
 */
class Provision
{
    private $files;
    private $cli;

    /**
     * [__construct description]
     *
     * @param CommandLine $cli
     * @param Filesystem  $files
     */
    public function __construct(CommandLine $cli, Filesystem $files)
    {
        $this->files = $files;
        $this->cli   = $cli;
    }

    /**
     * Install the initial configs
     *
     * @return void
     */
    public function install()
    {
        $this->writeBaseConfiguration();
        $this->unattendedUpgrades();

        $this->createBackupsDirectory();
        $this->createSitesDirectory();
    }

    /**
     * Create sites directory
     *
     * @return void
     */
    public function createSitesDirectory()
    {
        $this->files->ensureDirExists(self::sitePath(), user());
    }

    /**
     * Create backups directory
     *
     * @return void
     */
    public function createBackupsDirectory()
    {
        $this->files->ensureDirExists('~/backups');
    }

    /**
     * Create a swap file if not exists
     *
     * @return void
     */
    public function createSwapFile()
    {
        if (!$this->files->exists('/swapfile')) {
            $this->cli->run('fallocate -l 1G /swapfile');
            $this->cli->run('chmod 600 /swapfile');
            $this->cli->run('mkswap /swapfile');
            $this->cli->run('swapon /swapfile');
            $this->cli->run('echo "/swapfile none swap sw 0 0" >> /etc/fstab');
            $this->cli->run('echo "vm.swappiness=30" >> /etc/sysctl.conf');
            $this->cli->run('echo "vm.vfs_cache_pressure=50" >> /etc/sysctl.conf');
        }
    }

    /**
     * Enable firewall
     *
     * @return void
     */
    public function enableFirewall()
    {
        $this->cli->quietly('ufw allow 22');
        $this->cli->quietly('ufw allow 80');
        $this->cli->quietly('ufw allow 443');
        $this->cli->quietly('ufw --force enable');
    }

    /**
     * Write basic confi on sites.json
     *
     * @return void
     */
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

    /**
     * Configure unattended upgrades
     *
     * @return void
     */
    public function unattendedUpgrades()
    {
        $this->files->copy(BEDIQ_STUBS . '/apt/50unattended-upgrades', '/etc/apt/apt.conf.d/50unattended-upgrades');
        $this->files->copy(BEDIQ_STUBS . '/apt/10periodic', '/etc/apt/apt.conf.d/10periodic');
    }

    /**
     * Config file path
     *
     * @return string
     */
    private function path()
    {
        return '/root/sites.json';
    }

    /**
     * The static site path
     *
     * @return string
     */
    public static function sitePath($domain)
    {
        return '/var/www/' . $domain;
    }
}
