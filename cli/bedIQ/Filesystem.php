<?php

namespace Bediq\Cli;

use CommandLine as CommandLineFacade;
use FilesystemIterator;
use ErrorException;

class Filesystem
{
    /**
     * Determine if the given path is a directory.
     *
     * @param  string  $path
     * @return bool
     */
    public function isDir($path)
    {
        return is_dir($path);
    }

    /**
     * Create a directory.
     *
     * @param  string  $path
     * @param  string|null  $owner
     * @param  int  $mode
     * @return void
     */
    public function mkdir($path, $owner = null, $mode = 0755)
    {
        mkdir($path, $mode, true);

        if ($owner) {
            $this->chown($path, $owner);
        }
    }

    /**
     * Ensure that the given directory exists.
     *
     * @param  string  $path
     * @param  string|null  $owner
     * @param  int  $mode
     * @return void
     */
    public function ensureDirExists($path, $owner = null, $mode = 0755)
    {
        if (! $this->isDir($path)) {
            $this->mkdir($path, $owner, $mode);
        }
    }

    /**
     * Create a directory as the non-root user.
     *
     * @param  string  $path
     * @param  int  $mode
     * @return void
     */
    public function mkdirAsUser($path, $mode = 0755)
    {
        $this->mkdir($path, user(), $mode);
    }

    /**
     * Touch the given path.
     *
     * @param  string  $path
     * @param  string|null  $owner
     * @return string
     */
    public function touch($path, $owner = null)
    {
        touch($path);

        if ($owner) {
            $this->chown($path, $owner);
        }

        return $path;
    }

    /**
     * Touch the given path as the non-root user.
     *
     * @param  string  $path
     * @return void
     */
    public function touchAsUser($path)
    {
        return $this->touch($path, user());
    }

    /**
     * Determine if the given file exists.
     *
     * @param  string  $path
     * @return bool
     */
    public function exists($path)
    {
        return file_exists($path);
    }

    /**
     * Read the contents of the given file.
     *
     * @param  string  $path
     * @return string
     */
    public function get($path)
    {
        return file_get_contents($path);
    }

    /**
     * Write to the given file.
     *
     * @param  string  $path
     * @param  string  $contents
     * @param  string|null  $owner
     * @return void
     */
    public function put($path, $contents, $owner = null)
    {
        file_put_contents($path, $contents);

        if ($owner) {
            $this->chown($path, $owner);
        }
    }

    /**
     * Write to the given file as the non-root user.
     *
     * @param  string  $path
     * @param  string  $contents
     * @return void
     */
    public function putAsUser($path, $contents)
    {
        $this->put($path, $contents, user());
    }

    /**
     * Append the contents to the given file.
     *
     * @param  string  $path
     * @param  string  $contents
     * @param  string|null  $owner
     * @return void
     */
    public function append($path, $contents, $owner = null)
    {
        file_put_contents($path, $contents, FILE_APPEND);

        if ($owner) {
            $this->chown($path, $owner);
        }
    }

    /**
     * Append the contents to the given file as the non-root user.
     *
     * @param  string  $path
     * @param  string  $contents
     * @return void
     */
    public function appendAsUser($path, $contents)
    {
        $this->append($path, $contents, user());
    }

    /**
     * Copy the given file to a new location.
     *
     * @param  string  $from
     * @param  string  $to
     * @return void
     */
    public function copy($from, $to)
    {
        copy($from, $to);
    }

    /**
     * Copy the given file to a new location for the non-root user.
     *
     * @param  string  $from
     * @param  string  $to
     * @return void
     */
    public function copyAsUser($from, $to)
    {
        copy($from, $to);

        $this->chown($to, user());
    }

    /**
     * Create a symlink to the given target.
     *
     * @param  string  $target
     * @param  string  $link
     * @return void
     */
    public function symlink($target, $link)
    {
        if ($this->exists($link)) {
            $this->unlink($link);
        }

        symlink($target, $link);
    }

    /**
     * Create a symlink to the given target for the non-root user.
     *
     * This uses the command line as PHP can't change symlink permissions.
     *
     * @param  string  $target
     * @param  string  $link
     * @return void
     */
    public function symlinkAsUser($target, $link)
    {
        if ($this->exists($link)) {
            $this->unlink($link);
        }

        CommandLineFacade::runAsUser('ln -s '.escapeshellarg($target).' '.escapeshellarg($link));
    }

    /**
     * Delete the file at the given path.
     *
     * @param  string  $path
     * @return void
     */
    public function unlink($path)
    {
        if (file_exists($path) || is_link($path)) {
            @unlink($path);
        }
    }

    /**
     * Change the owner of the given path.
     *
     * @param  string  $path
     * @param  string  $user
     */
    public function chown($path, $user)
    {
        chown($path, $user);
    }

    /**
     * Change the group of the given path.
     *
     * @param  string  $path
     * @param  string  $group
     */
    public function chgrp($path, $group)
    {
        chgrp($path, $group);
    }

    /**
     * Resolve the given path.
     *
     * @param  string  $path
     * @return string
     */
    public function realpath($path)
    {
        return realpath($path);
    }

    /**
     * Determine if the given path is a symbolic link.
     *
     * @param  string  $path
     * @return bool
     */
    public function isLink($path)
    {
        return is_link($path);
    }

    /**
     * Resolve the given symbolic link.
     *
     * @param  string  $path
     * @return string
     */
    public function readLink($path)
    {
        return readlink($path);
    }

    /**
     * Recursively delete a directory.
     *
     * The directory itself may be optionally preserved.
     *
     * @param  string  $directory
     * @param  bool    $preserve
     *
     * @return bool
     */
    public function deleteDirectory($directory, $preserve = false)
    {
        if (! $this->isDir($directory)) {
            return false;
        }

        $items = new FilesystemIterator($directory);

        foreach ($items as $item) {
            // If the item is a directory, we can just recurse into the function and
            // delete that sub-directory otherwise we'll just delete the file and
            // keep iterating through each file until the directory is cleaned.
            if ($item->isDir() && ! $item->isLink()) {
                $this->deleteDirectory($item->getPathname());
            }

            // If the item is just a file, we can go ahead and delete it since we're
            // just looping through and waxing all of the files in this directory
            // and calling directories recursively, so we delete the real path.
            else {
                $this->delete($item->getPathname());
            }
        }

        if (! $preserve) {
            @rmdir($directory);
        }

        return true;
    }

    /**
     * Empty the specified directory of all files and folders.
     *
     * @param  string  $directory
     *
     * @return bool
     */
    public function cleanDirectory($directory)
    {
        return $this->deleteDirectory($directory, true);
    }

    /**
     * Delete the file at a given path.
     *
     * @param  string|array  $paths
     *
     * @return bool
     */
    public function delete($paths)
    {
        $paths = is_array($paths) ? $paths : func_get_args();

        $success = true;

        foreach ($paths as $path) {
            try {
                if (! @unlink($path)) {
                    $success = false;
                }
            } catch (ErrorException $e) {
                $success = false;
            }
        }

        return $success;
    }
}
