<?php
namespace Bediq\Cli;

/**
 * Configuration class
 */
class WP extends Lxc
{
    private $files;
    private $cli;

    public function download($container, $path)
    {
        $this->exec($container, 'wp core download --allow-root --path=' . $path);
    }

    public function generateConfig($container, array $config)
    {
        $this->cli->runCommand("wp core config --dbname={$config['dbname']} --dbuser={$config['dbuser']} --dbpass=$config['dbpass']");
    }
}
