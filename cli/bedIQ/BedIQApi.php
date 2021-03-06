<?php
namespace Bediq\Cli;


use Bediq\Cli\Helpers\BedIQAPIHelper;
use GuzzleHttp\Exception\RequestException;

class BedIQApi
{
    public $bedIQAPIHelper;

    public function __construct()
    {
        $this->bedIQAPIHelper = new BedIQAPIHelper();
    }

    public function plugins()
    {
        $plugins = $this->bedIQAPIHelper->getPluginsFromServer();

        if (!$plugins) {
            return json_decode($this->bedIQAPIHelper->getPluginsFromAPI(), true);
        }

        return $plugins;
    }

    public function themes()
    {
        $themes = $this->bedIQAPIHelper->getThemesFromServer();

        if (!$themes) {
            return json_decode($this->bedIQAPIHelper->getThemesFromAPI(), true);
        }

        return $themes;
    }

    public function getLatestBaseToolPath()
    {
        try {
            return $this->bedIQAPIHelper->getBaseToolFromAPI();
        }catch (\Exception $exception) {
            return false;
        }
    }
}
