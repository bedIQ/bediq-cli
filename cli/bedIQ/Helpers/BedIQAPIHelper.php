<?php

namespace Bediq\Cli\Helpers;

use Bediq\Cli\CommandLine;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class BedIQAPIHelper
{
    public function getPluginsFromServer()
    {
        return $this->serverDirCheck('/root/base_extracted_files/plugins');
    }

    public function getPluginsFromAPI()
    {
        return $this->newRequest('/v1/tools/plugins');
    }

    public function getThemesFromServer()
    {
        return $this->serverDirCheck('/root/base_extracted_files/themes');
    }

    public function getThemesFromAPI()
    {
        return $this->newRequest('/v1/tools/themes');
    }

    public function getBaseToolFromAPI()
    {
        return $this->newRequest('/v1/tools/latest_base_tool');
    }

    public function newRequest($url, $method = 'GET')
    {
        $endPoint = getenv('BEDIQ_API_ENDPOINT');
        $cliKey = getenv('BEDIQ_KEY');

        $client = new Client();

        try {
            $response = $client->request($method, $endPoint . $url, [
                'verify' => false,
                'headers' => [
                    'CLI-Key' => $cliKey
                ]
            ]);

            return $response->getBody()->getContents();
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $exception = (string) $e->getResponse()->getBody();
                $exception = json_decode($exception);
                print_r( $exception );
            } else {
                echo $e->getMessage();
            }
            return [];
        }

        $data = json_decode($response->getBody()->getContents(), true);

        return $data;
    }

    public function serverDirCheck($path)
    {
        $cli  = new CommandLine();

        $output = $cli->run('cd '.$path.' && ls -d $PWD/*');
        if ($output) {
            $output = array_filter( explode("\n", $output), 'strlen');

            // checking if OS returning 'No such file or directory' exception
            if (!is_array($output) || substr($output[0], 0, 2) == 'ls'  || substr($output[0], 0, 2) == 'sh') {
                return false;
            }

            return $output;
        }

        return false;
    }
}