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
            return $this->bedIQAPIHelper->getPluginsFromAPI();
        }

        return $plugins;
    }

    public function themes()
    {
        $themes = $this->bedIQAPIHelper->getThemesFromServer();

        if (!$themes) {
            return $this->bedIQAPIHelper->getThemesFromAPI();
        }

        return $themes;
    }

    public function getLatestBaseToolPath()
    {
        try {
            return json_decode($this->bedIQAPIHelper->getBaseToolFromAPI(), true);
        }catch (\Exception $exception) {
            output($exception->getMessage());
            return false;
        }
    }
}
