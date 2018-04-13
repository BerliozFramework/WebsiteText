<?php
/**
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2017 Ronan GIRON
 * @author    Ronan GIRON <https://gitLab.com/ElGigi>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code, to the root.
 */

namespace Berlioz\WebsiteText\Loader;


use Berlioz\WebsiteText\Exception\LoaderException;
use Http\Client\Exception;
use Http\Client\HttpClient;
use Http\Message\MessageFactory;
use Psr\Http\Message\ResponseInterface;

class GitLabLoader extends AbstractLoader
{
    /** @var string[] GitLab options */
    private $gitLabOptions;
    /** @var \Http\Client\HttpClient Http client */
    private $httpClient;
    /** @var \Http\Message\MessageFactory Http message factory */
    private $httpMessageFactory;
    /** @var array Files */
    private $files;

    /**
     * GitLabLoader constructor.
     *
     * @param array                        $gitLabOptions      GitLab options
     * @param string|null                  $basePath           Base path
     * @param array                        $filterIncludes     Filter includes
     * @param array                        $filterExcludes     Filter excludes
     * @param \Http\Client\HttpClient|null $httpClient         HTTP client
     * @param \Http\Message\MessageFactory $httpMessageFactory HTTP Message factory
     *
     * @option string $api       GitLab API URL
     * @option string $token     GitLab token
     * @option string $project   Project key
     * @option string $ref       Repository reference
     * @option string $directory First repository directory
     */
    public function __construct(array $gitLabOptions = [],
                                string $basePath = null,
                                array $filterIncludes = [],
                                array $filterExcludes = [],
                                ?HttpClient $httpClient = null,
                                ?MessageFactory $httpMessageFactory = null)
    {
        $this->gitLabOptions = ['api'     => $gitLabOptions['api'] ?? null,
                                'token'   => $gitLabOptions['token'] ?? null,
                                'project' => $gitLabOptions['project'] ?? null,
                                'ref'     => $gitLabOptions['ref'] ?? 'master',
                                'path'    => $gitLabOptions['path'] ?? '/'];
        $this->gitLabOptions['path'] = trim($this->gitLabOptions['path'], '/') . '/';

        // Http providers
        $this->httpClient = $httpClient;
        $this->httpMessageFactory = $httpMessageFactory;

        parent::__construct($basePath, $filterIncludes, $filterExcludes);
    }

    /**
     * Get http client.
     *
     * @return \Http\Client\HttpClient
     */
    public function getHttpClient(): HttpClient
    {
        return $this->httpClient;
    }

    /**
     * Set http client.
     *
     * @param \Http\Client\HttpClient $httpClient
     *
     * @return self
     */
    public function setHttpClient(HttpClient $httpClient): GitLabLoader
    {
        $this->httpClient = $httpClient;

        return $this;
    }

    /**
     * Get http message factory.
     *
     * @return \Http\Message\MessageFactory
     */
    public function getHttpMessageFactory(): MessageFactory
    {
        return $this->httpMessageFactory;
    }

    /**
     * Set http message factory.
     *
     * @param \Http\Message\MessageFactory $httpMessageFactory
     *
     * @return GitLabLoader
     */
    public function setHttpMessageFactory(MessageFactory $httpMessageFactory): GitLabLoader
    {
        $this->httpMessageFactory = $httpMessageFactory;

        return $this;
    }

    /**
     * Do request on GitLab API.
     *
     * @param string $method Http method
     * @param string $uri    URI
     *
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Berlioz\WebsiteText\Exception\LoaderException
     */
    private function doRequest(string $method, string $uri): ResponseInterface
    {
        try {
            try {
                // Request
                $request = $this->getHttpMessageFactory()
                                ->createRequest($method,
                                                $uri,
                                                ['Private-Token' => $this->gitLabOptions['token'],
                                                 'Content-Type'  => 'application/json']);

                $response = $this->getHttpClient()->sendRequest($request);

                return $response;
            } catch (Exception $e) {
                throw $e;
            }
        } catch (\Exception $e) {
            throw new LoaderException('Unable to dialog with GitLab API', 0, $e);
        }
    }

    /**
     * Load files from GitLab API.
     *
     * @param bool $force
     *
     * @throws \Berlioz\WebsiteText\Exception\LoaderException
     */
    private function loadFromGitLab(bool $force = false)
    {
        if (is_null($this->files) || $force) {
            $this->files = [];

            $page = 0;
            $nbPage = 1;
            while ($page <= $nbPage) {
                $page++;
                $response = $this->doRequest('GET',
                                             sprintf('%s/api/v4/projects/%s/repository/tree?recursive=true&ref=%s&path=%s&per_page=100&page=%d',
                                                     $this->gitLabOptions['api'],
                                                     urlencode($this->gitLabOptions['project']),
                                                     $this->gitLabOptions['ref'],
                                                     $this->gitLabOptions['path'],
                                                     $page));

                if (($jsonResponse = json_decode($response->getBody(), true)) !== false) {
                    foreach ($jsonResponse as $entry) {
                        $fullFilename = '/' . trim($entry['path'], '/');

                        if ($entry['type'] == 'blob') {
                            if ($this->testFilter($fullFilename)) {
                                $this->files[$fullFilename] = $this->getFileFromGitLab($entry['id']);
                            }
                        }
                    }
                }

                // Get nb pages
                if (!empty($nextPageHeader = $response->getHeader('X-Total-Pages'))) {
                    $nbPage = intval($response->getHeader('X-Total-Pages')[0]);
                }
            }
        }
    }

    /**
     * Get file from GitLab.
     *
     * @param string $id
     *
     * @return bool|string
     * @throws \Http\Client\Exception\HttpException
     * @throws \Berlioz\WebsiteText\Exception\LoaderException
     */
    private function getFileFromGitLab(string $id)
    {
        $response = $this->doRequest('GET',
                                     sprintf('%s/api/v4/projects/%s/repository/blobs/%s',
                                             $this->gitLabOptions['api'],
                                             urlencode($this->gitLabOptions['project']),
                                             $id));

        if (($jsonResponse = json_decode($response->getBody(), true)) !== false) {
            if ($jsonResponse['encoding'] == 'base64') {
                return base64_decode($jsonResponse['content']);
            } else {
                throw new LoaderException(sprintf('Unable to get file id "%s", bad encoding', $id));
            }
        } else {
            throw new LoaderException(sprintf('Unable to get file id "%s"', $id));
        }
    }

    /**
     * Get unique id uses for cache.
     *
     * @return string
     */
    public function getUniqId(): string
    {
        return sha1(implode('-', $this->gitLabOptions));
    }

    /**
     * Scan paths.
     *
     * @return string[]
     * @throws \Berlioz\WebsiteText\Exception\LoaderException if unable to scan paths
     */
    public function scan(): array
    {
        $this->loadFromGitLab();

        return array_keys($this->files);
    }

    /**
     * Load file.
     *
     * @param string $path
     *
     * @return string
     * @throws \Berlioz\WebsiteText\Exception\LoaderException if unable to find filename
     */
    public function load(string $path): string
    {
        $this->loadFromGitLab();

        if (isset($this->files[$path])) {
            return $this->files[$path];
        } else {
            throw new LoaderException(sprintf('File "%s" doesn\'t exists', $path));
        }
    }
}