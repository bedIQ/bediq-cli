<?php
namespace Bediq\Cli;

/**
 * LXC class
 */
class Lxc
{
    private $files;
    private $cli;

    function __construct(CommandLine $cli, Filesystem $files) {
        $this->cli   = $cli;
        $this->files = $files;
    }

    /**
     * Initialize lxd
     *
     * @return void
     */
    function init()
    {
        $seed = $this->files->get(BEDIQ_STUBS . '/lxd.yaml');

        echo $this->cli->run('cat <<EOF | lxd init --preseed ' . PHP_EOL . $seed . PHP_EOL . 'EOF');
    }

    /**
     * Check if a container exists
     *
     * @param  string $container
     *
     * @return boolean
     */
    function containerExists($container)
    {
        return $this->cli->run("lxc list | grep {$container} | awk '{print \$2}'") != '';
    }

    function hasBase()
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
    function isRunning($container)
    {
        return trim($this->cli->run("lxc list | grep {$container} | awk '{print \$4}'")) == 'RUNNING';
    }

    /**
     * Get the container IP address
     *
     * @param  string $container
     *
     * @return string|false
     */
    function getIp($container)
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
    function start($container)
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
    function stop($container)
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
    function remove($container)
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
    function launch($container)
    {
        info("Creating container {$container}...");

        $this->cli->run("lxc launch ubuntu:18.04 {$container}", function($code, $output) {
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
