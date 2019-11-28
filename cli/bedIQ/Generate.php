<?php

namespace Bediq\Cli;

use GuzzleHttp\Pool;
use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7;

class Generate
{
    private $url;
    private $domain;
    private $siteKey;
    private $concurrency = 5;

    private $scripts = [];
    private $styles = [];

    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct($url, $domain, $siteKey)
    {
        $this->url    = $url;
        $this->domain = $domain;
        $this->siteKey = $siteKey;
    }

    /**
     * [setConcurrency description]
     *
     * @param integer $concurrency
     *
     * @return self
     */
    public function setConcurrency($concurrency)
    {
        $this->concurrency = $concurrency;

        return $this;
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        $client = $this->getClient();

        if (!$this->rootDir()) {
            echo 'Error: Site root is not set' . PHP_EOL;
            return;
        }

        $pages = $this->getPages();

        if (false === $pages) {
            return false;
        }

        $this->cleanDir();
        $this->savePages($pages);
    }

    /**
     * Get Guzzle Client
     *
     * @return Client
     */
    public function getClient()
    {
        return new Client([
            'verify' => false
        ]);
    }

    /**
     * Site root directory
     *
     * @return string
     */
    private function rootDir()
    {
        $dir = '/var/www';

        if (!$dir) {
            throw new \Exception('Site root is not set');
        }

        // Removes trailing forward slashes and backslashes if they exist.
        $dir = rtrim( $dir, '/\\' );

        return $dir . '/';
    }

    public function cleanDir()
    {
        $directory = $this->rootDir() . $this->domain;

        (new Filesystem)->cleanDirectory( $directory );
    }

    /**
     * Get the list of pages to save from the API
     *
     * @return array
     */
    private function getPages()
    {
        $client = $this->getClient();

        try {
            $response = $client->request('GET', $this->url . '/wp-json/static/v1/pages', [
                'verify' => false,
                'headers' => [
                    'X-Site-Key' => $this->siteKey
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

            // echo $e->getMessage();
            return false;
        }

        $pages = json_decode($response->getBody());

        if (!$pages->routes) {
            return false;
        }

        $requests = [];
        foreach ($pages->routes as $key => $page) {
            $requests[$key] = new Request('GET', $page->url);
        }

        return $requests;
    }

    /**
     * Save the pages as HTML
     *
     * @param  array $requests
     *
     * @return void
     */
    public function savePages($requests)
    {
        $paths = [];
        $pool = new Pool($this->getClient(), $requests, [
            'concurrency' => $this->concurrency,
            'fulfilled' => function ($response, $index) use ($requests, &$paths) {
                // this is delivered each successful response
                $url      = $requests[$index]->getUri();
                $fullPath = $this->rootDir() . $this->domain . '/' . $url->getPath();

                // echo 'Path: ' . $url->getPath() . PHP_EOL;

                if (!file_exists($fullPath)) {
                    mkdir($fullPath, 0777, true);
                }

                $content = $response->getBody()->getContents();

                $dom = new \DOMDocument;
                libxml_use_internal_errors(true);
                $dom->loadHTML($content);

                $this->extractAssets($dom, $this->styles, 'style');
                $this->extractAssets($dom, $this->scripts, 'script');

                $paths[]    = '/' . $url->getPath() . '/index.html';

                file_put_contents($fullPath . 'index.html', $content);
                // echo $url->getPath() . PHP_EOL;
                // echo 'Success! ' . $index . PHP_EOL;
                // print_r( $response );
            },
            'rejected' => function ($reason, $index) use($requests) {
                // this is delivered each failed request
                echo 'Fail! ' . $requests[$index]->getUri() . PHP_EOL;
                // echo 'Reason: ' . $reason . PHP_EOL;
            },
        ]);

        // Initiate the transfers and create a promise
        $promise = $pool->promise();

        // Force the pool of requests to complete.
        $promise->wait();
        $promise->then(function() {
            $this->saveAssets($this->scripts, 'js');
        })->then(function() {
            $this->saveAssets($this->styles, 'css');
        })->then(function() use($paths) {
            $pages = json_encode($paths, JSON_PRETTY_PRINT);
            file_put_contents( $this->rootDir() . $this->domain . '/pages.json', $pages );

            echo 'Done!' . PHP_EOL;
        });
    }

    /**
     * Extract assets from the DOM
     *
     * @param  \DomDocument $dom
     * @param  array &$var
     * @param  string $type
     *
     * @return void
     */
    function extractAssets($dom, &$var, $type = 'script')
    {
        $nodes = ('script' == $type) ? $dom->getElementsByTagName('script') : $dom->getElementsByTagName('link');
        foreach ($nodes as $node) {
            $url = '';

            if ('style' === $type && $node->hasAttribute('rel') && $node->getAttribute('rel') == 'stylesheet') {
                $url = $node->getAttribute('href');
            }

            if ('script' === $type && $node->hasAttribute('src')) {
                $url = $node->getAttribute('src');
            }

            if (!empty($url)) {
                $url = strtok($url, '?'); // remove version string

                // relative urls start with either "/wp" or "/app" (for bedrock sites)
                if ( ( substr($url, 0, 3) === "/wp" || substr($url, 0, 4) === "/app" ) && !in_array($url, $var)) {
                    $var[] = $url;
                }
            }
        }
    }

    /**
     * Save assets
     *
     * @param  string $scripts
     *
     * @return void
     */
    function saveAssets($scripts, $type)
    {
        if (!$scripts) {
            return;
        }

        $requests = [];
        $client = new Client([
            'verify'   => false,
            'base_uri' => $this->url,
        ]);

        foreach ($scripts as $key => $url) {
            $requests[$key] = new Request('GET', $url);
        }

        // save JS and CSS in a JSON file for caching via service worker
        $json_string = json_encode($scripts, JSON_PRETTY_PRINT);
        file_put_contents( $this->rootDir() . $this->domain . '/' . $type . '.json', $json_string );

        $pool = new Pool($client, $requests, [
            'concurrency' => $this->concurrency,
            'fulfilled' => function ($response, $index) use ($requests) {
                $url      = $requests[$index]->getUri();
                $fullPath = $this->rootDir() . $this->domain . $url->getPath();
                $directory = dirname($fullPath);

                if (!file_exists($directory)) {
                    mkdir($directory, 0777, true);
                }

                $content = $response->getBody()->getContents();
                file_put_contents($fullPath, $content);
            }
        ]);

        // Initiate the transfers and create a promise
        $promise = $pool->promise();

        // Force the pool of requests to complete.
        $promise->wait();
    }
}
