<?php
namespace Bediq\Cli;

/**
 * Configuration class
 */
class WP extends Lxc
{
    /**
     * Download WordPress to the given path
     *
     * @param  string $container
     * @param  string $path
     *
     * @return string
     */
    public function download($container, $path)
    {
        return $this->exec($container, 'wp core download --allow-root --path=' . $path);
    }

    /**
     * Generate wp-config.php
     *
     * @param  string $container
     * @param  string $config
     * @param  string $path
     *
     * @return string
     */
    public function generateConfig($container, $config, $path)
    {
        return $this->exec($container, "wp core config --dbname={$config['dbname']} --dbuser={$config['dbuser']} --dbpass={$config['dbpass']} --allow-root --path={$path} --extra-php <<'PHP'\n" . $this->extraPhp($config['id'], $config['key'], $config['secret']) . "\nPHP");
    }

    public function extraPhp($siteId, $key, $secret)
    {
        $var = <<<EOF
define( 'WP_DEBUG', true );
define( 'WP_DEBUG_LOG', true );
define( 'DISALLOW_FILE_EDIT', true );

// bedIQ settings
define( 'BEDIQ_DEV_MODE', false );
define( 'BEDIQ_SITE_ID', '$siteId' );
define( 'AS3CF_SETTINGS', serialize( array(
    'provider'          => 'do',
    'access-key-id'     => '$key',
    'secret-access-key' => '$secret',
) ) );

/**
 * Allow WordPress to detect HTTPS when used behind a reverse proxy or a load balancer
 * See https://codex.wordpress.org/Function_Reference/is_ssl#Notes
 */
if (isset(\$_SERVER['HTTP_X_FORWARDED_PROTO']) && \$_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
    \$_SERVER['HTTPS'] = 'on';
}

EOF;

        return $var;
    }

    /**
     * Install WordPress
     *
     * @param  string $container
     * @param  string $path
     * @param  string $url
     * @param  string $title
     * @param  string $username
     * @param  string $pass
     * @param  string $email
     *
     * @return void
     */
    public function install($container, $path, $url, $title, $username, $pass, $email)
    {
        $this->exec($container, 'wp db create --allow-root --path=' . $path);
        $this->exec($container, 'wp core install --url="http://' . $url .'" --title="' . $title .'" --admin_user="' . $username .'" --admin_password="' . $pass .'" --admin_email="' . $email .'" --allow-root --path=' . $path);
        $this->exec($container, 'wp rewrite structure "/%postname%/" --hard --allow-root --path=' . $path);
        $this->exec($container, 'wp plugin delete akismet hello --allow-root --path=' . $path);
    }

    /**
     * Install plugins on a site
     *
     * @param  string $container
     * @param  string $path
     * @param  array  $plugins
     *
     * @return string
     */
    public function installPlugins($container, $path, array $plugins)
    {
        return $this->exec($container, 'wp plugin install --activate ' . implode(' ', $plugins) . ' --allow-root --path=' . $path);
    }

    /**
     * Install themes on a site
     *
     * @param  string $container
     * @param  string $path
     * @param  array  $themes
     *
     * @return string
     */
    public function installThemes($container, $path, array $themes)
    {
        return $this->exec($container, 'wp theme install ' . implode(' ', $themes) . ' --allow-root --path=' . $path);
    }

    public function backup($container, $path)
    {
        $fileName = $container . '-' . date('Y-m-d') . '.sql.gz';
        $this->exec($container, 'sh -c "wp db export - --allow-root --path=' . $path . ' | gzip > ' . $fileName . '"');

        $this->pullFile($container, 'root/' . $fileName, '/root/backups/');
        $this->deleteFile($container, 'root/' . $fileName);

        return '/root/backups/' . $fileName;
    }
}
