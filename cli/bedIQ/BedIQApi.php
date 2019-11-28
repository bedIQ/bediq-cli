<?php
namespace Bediq\Cli;


use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class BedIQApi
{
    private $url;
    private $cliKey;

    public function __construct()
    {
        $this->url = getenv('BEDIQ_API_ENDPOINT');
        $this->cliKey = getenv('BEDIQ_KEY');
    }

    public function plugins()
    {
        $client = new Client(['verify' => false]);

        try {
            $response = $client->request('GET', $this->url . '/v1/tools/plugins', [
                'verify' => false,
                'headers' => [
                    'CLI-Key' => $this->cliKey
                ]
            ]);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $exception = (string) $e->getResponse()->getBody();
                $exception = json_decode($exception);
                print_r( $exception );
            } else {
                echo $e->getMessage();
            }
            return false;
        }

        $plugins = json_decode($response->getBody()->getContents(), true);

        return $plugins;
    }

    public function themes()
    {
        $client = new Client(['verify' => false]);

        try {
            $response = $client->request('GET', $this->url . '/v1/tools/themes', [
                'verify' => false,
                'headers' => [
                    'CLI-Key' => $this->cliKey
                ]
            ]);
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $exception = (string) $e->getResponse()->getBody();
                $exception = json_decode($exception);
                print_r( $exception );
            } else {
                echo $e->getMessage();
            }
            return false;
        }

        $themes = json_decode($response->getBody()->getContents(), true);

        return $themes;
    }
}
