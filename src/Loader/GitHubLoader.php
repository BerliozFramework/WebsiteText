<?php
/**
 * This file is part of Berlioz framework.
 *
 * @license   https://opensource.org/licenses/MIT MIT License
 * @copyright 2017 Ronan GIRON
 * @author    Ronan GIRON <https://github.com/ElGigi>
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

class GitHubLoader extends AbstractLoader
{
    const API_URL = 'https://api.github.com/graphql';
    /** @var string[] GitHub options */
    private $gitHubOptions;
    /** @var \Http\Client\HttpClient Http client */
    private $httpClient;
    /** @var \Http\Message\MessageFactory Http message factory */
    private $httpMessageFactory;
    /** @var array Files */
    private $files;

    /**
     * GitHubLoader constructor.
     *
     * @param array                        $gitHubOptions      GitHub options
     * @param string|null                  $basePath           Base path
     * @param array                        $filterIncludes     Filter includes
     * @param array                        $filterExcludes     Filter excludes
     * @param \Http\Client\HttpClient|null $httpClient         HTTP client
     * @param \Http\Message\MessageFactory $httpMessageFactory HTTP Message factory
     *
     * @option string $token      GitHub token
     * @option string $owner      Owner name
     * @option string $repository Repository name
     * @option string $branch     Repository branch
     * @option string $directory  First repository directory
     */
    public function __construct(array $gitHubOptions = [],
                                string $basePath = null,
                                array $filterIncludes = [],
                                array $filterExcludes = [],
                                ?HttpClient $httpClient = null,
                                ?MessageFactory $httpMessageFactory = null)
    {
        $this->gitHubOptions = ['token'      => $gitHubOptions['token'] ?? null,
                                'owner'      => $gitHubOptions['owner'] ?? null,
                                'repository' => $gitHubOptions['repository'] ?? null,
                                'branch'     => $gitHubOptions['branch'] ?? 'master',
                                'directory'  => $gitHubOptions['directory'] ?? ''];
        $this->gitHubOptions['directory'] = trim($this->gitHubOptions['directory'], '/') . '/';

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
    public function setHttpClient(HttpClient $httpClient): GitHubLoader
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
     * @return GitHubLoader
     */
    public function setHttpMessageFactory(MessageFactory $httpMessageFactory): GitHubLoader
    {
        $this->httpMessageFactory = $httpMessageFactory;

        return $this;
    }

    public function loadFromGithub(bool $force = false)
    {
        if (is_null($this->files) || $force) {
            $this->files = $this->graphqlRequest([$this->gitHubOptions['directory'] ?? '/']);
        }
    }

    /**
     * Do request on GitHub API.
     *
     * @param string $method      Http method
     * @param string $requestBody Request body
     *
     * @return \Psr\Http\Message\ResponseInterface
     * @throws \Berlioz\WebsiteText\Exception\LoaderException
     */
    private function doRequest(string $method, string $requestBody): ResponseInterface
    {
        try {
            try {
                // Request
                $request = $this->getHttpMessageFactory()
                                ->createRequest($method,
                                                static::API_URL,
                                                ['Authorization' => sprintf('bearer %s', $this->gitHubOptions['token']),
                                                 'Content-Type'  => 'application/json'],
                                                json_encode(['query' => $requestBody]));

                $response = $this->getHttpClient()->sendRequest($request);

                return $response;
            } catch (Exception $e) {
                throw $e;
            }
        } catch (\Exception $e) {
            throw new LoaderException('Unable to dialog with GitHub API', 0, $e);
        }
    }

    /**
     * Do GraphQL request on GitHub Api to get directories content.
     *
     * @param array $directories Directories to get content
     *
     * @return array
     * @throws \Berlioz\WebsiteText\Exception\LoaderException
     */
    private function graphqlRequest(array $directories): array
    {
        $files = [];
        $graphqlRequest = '';

        // Create GraphQL body request
        foreach ($directories as &$directory) {
            $directory = trim($directory, '/') . '/';
            $directory = ltrim($directory, '/');
            $directoryHash = sprintf('dir_%s', md5($directory));

            $graphqlRequest .= <<<EOD
    {$directoryHash}: object(expression: "{$this->gitHubOptions['branch']}:{$directory}") {
      ... on Tree {
        entries {
          oid
          name
          type
          content: object {
            ...on Blob {
              text
            }
          }
        }
      }
    }
EOD;
        }

        // Do HTTP request
        $response = $this->doRequest(
            'POST',
            <<<EOD
{
  repository(name: "{$this->gitHubOptions['repository']}", owner: "{$this->gitHubOptions['owner']}") {
    {$graphqlRequest}
  }
}
EOD
        );

        if (($jsonResponse = json_decode($response->getBody(), true)) !== false) {
            $subDirectories = [];

            foreach ($directories as $directory) {
                $directoryHash = sprintf('dir_%s', md5($directory));

                if (!empty($directoryContent = $jsonResponse['data']['repository'][$directoryHash]['entries'])) {
                    foreach ($directoryContent as $file) {
                        $fullFilename = '/' . ltrim($directory, '/') . $file['name'];
                        $fullFilename = substr($fullFilename, mb_strlen(rtrim('/' . $this->gitHubOptions['directory'], '/')));

                        if ($file['type'] == 'tree') {
                            $subDirectories[] = $fullFilename;
                        } else {
                            if (!empty($file['content']['text'])) {
                                if ($this->testFilter($fullFilename)) {
                                    $files[$fullFilename] = $file['content']['text'];
                                }
                            }
                        }
                    }
                }
            }

            // If subdirectories detected, recursive call
            if (!empty($subDirectories)) {
                $files = array_merge($files, $this->graphqlRequest($subDirectories));
            }
        } else {
            throw new LoaderException('Unable to get content of files');
        }

        return $files;
    }

    /**
     * Get unique id uses for cache.
     *
     * @return string
     */
    public function getUniqId(): string
    {
        return sha1(implode('-', $this->gitHubOptions));
    }

    /**
     * Scan paths.
     *
     * @return string[]
     * @throws \Berlioz\WebsiteText\Exception\LoaderException if unable to scan paths
     */
    public function scan(): array
    {
        $this->loadFromGithub();

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
        $this->loadFromGithub();

        if (isset($this->files[$path])) {
            return $this->files[$path];
        } else {
            throw new LoaderException(sprintf('File "%s" doesn\'t exists', $path));
        }
    }
}