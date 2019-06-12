<?php
namespace Bediq\Cli;

/**
 * LXC class
 */
class Lxc
{
    private $files;
    private $cli;

    public function __construct(CommandLine $cli, Filesystem $files)
    {
        $this->cli   = $cli;
        $this->files = $files;
    }

    /**
     * Initialize lxd
     *
     * @return void
     */
    public function init()
    {
        $seed = $this->files->get(BEDIQ_STUBS . '/lxd.yaml');

        $this->cli->run('cat <<EOF | lxd init --preseed ' . PHP_EOL . $seed . PHP_EOL . 'EOF');
    }

    /**
     * Check if a container exists
     *
     * @param  string $container
     *
     * @return boolean
     */
    public function containerExists($container)
    {
        return $this->cli->run("lxc list | grep {$container} | awk '{print \$2}'") != '';
    }

    public function hasBase()
    {
        return $this->containerExists('base');
    }

    /**
     * Check if a container is in running state
     *
     * @param  string  $container
     *
     * @return boolean
     */
    public function isRunning($container)
    {
        return trim($this->cli->run("lxc list | grep {$container} | awk '{print \$4}'")) == 'RUNNING';
    }

    /**
     * Run a command on an instance
     *
     * @param  string $container
     * @param  string $command
     *
     * @return string
     */
    public function exec($container, $command)
    {
        return $this->cli->run("lxc exec {$container} -- $command");
    }

    /**
     * Get the container IP address
     *
     * @param  string $container
     *
     * @return string|false
     */
    public function getIp($container)
    {
        $ip = trim($this->cli->run("lxc list | grep {$container} | awk '{print \$6}'"));

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return $ip;
        }

        return false;
    }

    /**
     * Start a container
     *
     * @param  string $container
     *
     * @return void
     */
    public function start($container)
    {
        $this->cli->run("lxc start {$container}");
    }

    /**
     * Stop a container
     *
     * @param  string $container
     *
     * @return string
     */
    public function stop($container)
    {
        return $this->cli->run("lxc stop {$container}");
    }

    /**
     * Remove a container
     *
     * @param  string $container
     *
     * @return void
     */
    public function remove($container)
    {
        $this->stop($container);
        $this->cli->run("lxc delete {$container}");

        info("Removed container {$container}");
    }

    /**
     * Launch a container
     *
     * @param  string $container
     *
     * @return string The IP address
     */
    public function launch($container)
    {
        if ($this->containerExists($container)) {
            throw new \Exception("Container {$container} exists");
        }

        info("Creating container {$container}...");

        $this->cli->run("lxc launch ubuntu:18.04 {$container}", function ($code, $output) {
            output($output);

            throw new \Exception('Could not launch container');
        });

        while (true) {
            $ip = $this->getIp($container);

            if ($ip) {
                break;
            }

            sleep(1);
        }

        return $ip;
    }
}
