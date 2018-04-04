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

namespace Berlioz\WebsiteText;


use Berlioz\HtmlSelector\Exception\HtmlSelectorException;
use Berlioz\HtmlSelector\Query;
use Berlioz\WebsiteText\Exception\WebsiteTextException;
use Berlioz\WebsiteText\Parser\Markdown;
use Berlioz\WebsiteText\Parser\reStructuredText;
use Berlioz\WebsiteText\Summary\Element;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheException;
use Psr\SimpleCache\CacheInterface;

class Generator
{
    use LoggerAwareTrait;
    /** Key name used in system cache to store generator information */
    const CACHE_KEY_GENERATOR = '_BERLIOZ_WEBSITETEXT_GENERATOR_%s';
    /** Key name used in system cache to store document information */
    const CACHE_KEY_DOCUMENT = '_BERLIOZ_WEBSITETEXT_DOCUMENT_%s';
    /** @var array Options */
    private $options;
    /** @var \Berlioz\WebsiteText\Loader Loader */
    private $loader;
    /** @var \Psr\SimpleCache\CacheInterface Cache manager */
    private $cacheManager;
    /** @var \Berlioz\WebsiteText\Parser[] Parsers */
    private $parsers;
    /** @var \Berlioz\WebsiteText\Summary Summary */
    private $summary;
    /** @var \Berlioz\WebsiteText\Document[] Documents */
    private $documents;
    /** @var string[] Documents urls */
    private $documentsUrls;

    /**
     * Generator constructor.
     *
     * @param \Berlioz\WebsiteText\Loader|null     $loader       Loader
     * @param array                                $options      Options
     * @param null|\Psr\SimpleCache\CacheInterface $cacheManager Cache manager
     */
    public function __construct(?Loader $loader = null, array $options = [], ?CacheInterface $cacheManager = null)
    {
        // Options
        $this->options = ['url.host'                => null,
                          'url.host_external_blank' => true,
                          'url.host_external_rel'   => 'noopener',
                          // Prefix of urls of documentation
                          'url.prefix'              => '',
                          // Path of imgs of documentation
                          'url.images-path'         => '',
                          'parsing.remove-h1'       => true,
                          'parsing.summary'         => true,
                          'summary'                 => true];
        $options = array_intersect_key($options, $this->options);
        $this->options = array_merge($this->options, $options);

        $this->setCacheManager($cacheManager);
        $this->setLoader($loader);
    }

    /**
     * __set_state() magic method.
     *
     * @param array $an_array Properties array.
     *
     * @return array
     */
    public static function __set_state($an_array): array
    {
        return ['cacheManager' => get_class($an_array['cacheManager']),
                'loader'       => $an_array['loader'],
                'parsers'      => $an_array['parsers'],
                'documents'    => $an_array['documents']];
    }

    /**
     * __debugInfo() magic method.
     *
     * @return array
     */
    public function __debugInfo(): array
    {
        return ['cacheManager' => get_class($this->cacheManager),
                'loader'       => $this->loader,
                'parsers'      => $this->parsers,
                'documents'    => $this->documents];
    }

    /**
     * Get option.
     *
     * @param string $name Name of option
     *
     * @return mixed|false
     */
    public function getOption(string $name)
    {
        return $this->options[$name] ?? false;
    }

    /**
     * Get logger.
     *
     * @return null|\Psr\Log\LoggerInterface
     */
    public function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }

    /**
     * Get cache manager.
     *
     * @return \Psr\SimpleCache\CacheInterface|null
     */
    public function getCacheManager(): ?CacheInterface
    {
        return $this->cacheManager;
    }

    /**
     * Set cache manager.
     *
     * @param null|\Psr\SimpleCache\CacheInterface $cacheManager
     *
     * @return self
     */
    public function setCacheManager(?CacheInterface $cacheManager): Generator
    {
        $this->cacheManager = $cacheManager;

        return $this;
    }

    /**
     * Load generator information from cache.
     */
    private function loadFromCacheManager()
    {
        if (!is_null($this->getCacheManager())) {
            try {
                $cache = $this->getCacheManager()->get(sprintf(self::CACHE_KEY_GENERATOR, $this->getLoader()->getUniqId()));

                $this->setSummary($cache['summary'] ?? null);
                $this->documentsUrls = $cache['documentsUrls'] ?? null;

                // Log
                if (!is_null($this->getLogger())) {
                    $this->getLogger()->info(sprintf('%s / Generator information loaded from cache', __METHOD__));
                }
            } catch (CacheException $e) {
                $this->setSummary(null);
                $this->documentsUrls = null;
            }
        }
    }

    /**
     * Save generator information and documents to cache.
     */
    private function saveToCacheManager()
    {
        if (!is_null($this->getCacheManager())) {
            try {
                // Generator
                {
                    $generatorInfo = ['summary'       => $this->getSummary(),
                                      'documentsUrls' => $this->documentsUrls];

                    $this->getCacheManager()->set(sprintf(self::CACHE_KEY_GENERATOR, $this->getLoader()->getUniqId()), $generatorInfo);
                }

                // Documents
                {
                    $documentsCache = [];
                    foreach ($this->documents as $document) {
                        $cacheKey = sprintf(self::CACHE_KEY_DOCUMENT, sha1($this->getLoader()->getUniqId() . sha1($document->getFilename())));
                        $documentsCache[$cacheKey] = $document;
                    }

                    $this->getCacheManager()->setMultiple($documentsCache);
                }

                // Log
                if (!is_null($this->getLogger())) {
                    $this->getLogger()->info(sprintf('%s / Generator information and documents saved to cache', __METHOD__));
                }
            } catch (CacheException $e) {
            }
        }
    }

    /**
     * Clear generator cache from loader information.
     *
     * @return bool
     */
    public function clearCache(): bool
    {
        if (!is_null($this->getCacheManager())) {
            try {
                $keys = [];
                $keys[] = sprintf(self::CACHE_KEY_GENERATOR, $this->getLoader()->getUniqId());

                if (is_array($this->documentsUrls)) {
                    foreach ($this->documentsUrls as $url => $filename) {
                        $keys[] = sprintf(self::CACHE_KEY_DOCUMENT, sha1($this->getLoader()->getUniqId() . sha1($filename)));
                    }
                }

                return $this->getCacheManager()->deleteMultiple($keys);
            } catch (CacheException $e) {
            }
        }

        return false;
    }

    /**
     * Get loader.
     *
     * @return \Berlioz\WebsiteText\Loader|null
     */
    public function getLoader(): ?Loader
    {
        return $this->loader;
    }

    /**
     * Set loader.
     *
     * @param \Berlioz\WebsiteText\Loader $loader
     */
    public function setLoader(Loader $loader)
    {
        $this->loader = $loader;
    }

    /**
     * Get parser for a defined format.
     *
     * @param string $format
     *
     * @return \Berlioz\WebsiteText\Parser|null
     */
    public function getParser(string $format): ?Parser
    {
        return $this->parsers[$format] ?? null;
    }

    /**
     * Set parser.
     *
     * @param string                      $format
     * @param \Berlioz\WebsiteText\Parser $parser
     *
     * @return \Berlioz\WebsiteText\Generator
     */
    public function setParser(string $format, Parser $parser): Generator
    {
        $this->parsers[$format] = $parser;

        return $this;
    }

    /**
     * Get document.
     *
     * @param string $path Path of file
     *
     * @return \Berlioz\WebsiteText\Document|null
     * @throws \Berlioz\WebsiteText\Exception\WebsiteTextException if an error occurred during parsing
     */
    public function getDocument(string $path): ?Document
    {
        $cacheKey = sprintf(self::CACHE_KEY_DOCUMENT, sha1($this->getLoader()->getUniqId() . sha1($path)));

        // Get from cache
        if (!is_null($this->getCacheManager())) {
            try {

                if ($this->getCacheManager()->has($cacheKey)) {
                    $this->documents[$path] = $this->getCacheManager()->get($cacheKey) ?? null;

                    // Log
                    if (!is_null($this->getLogger())) {
                        $this->getLogger()->info(sprintf('%s / Document "%s" loaded from cache', __METHOD__, $path));
                    }
                }
            } catch (CacheException $e) {
                $this->documents[$path] = null;
            }
        }

        $document = $this->documents[$path] ?? $this->parse($path);

        return $document;
    }

    /**
     * Get summary.
     *
     * @return \Berlioz\WebsiteText\Summary
     */
    public function getSummary(): ?Summary
    {
        if ($this->getOption('summary') === true) {
            if (is_null($this->summary)) {
                $this->summary = new Summary;
            }

            return $this->summary;
        }

        return null;
    }

    /**
     * Set summary.
     *
     * @param \Berlioz\WebsiteText\Summary|null $summary
     *
     * @return \Berlioz\WebsiteText\Generator
     */
    public function setSummary(?Summary $summary): Generator
    {
        $this->summary = $summary;

        return $this;
    }

    /**
     * Parse.
     *
     * @param string $path Path of file
     *
     * @return \Berlioz\WebsiteText\Document|null
     * @throws \Berlioz\WebsiteText\Exception\WebsiteTextException
     */
    protected function parse(string $path): ?Document
    {
        try {
            // Format
            $format = null;
            $matches = [];
            if (preg_match('/^.*\.([a-z0-9]+)$/', $path, $matches) == 1) {
                $format = $matches[1];
            }

            // Get parser
            if (is_null($parser = $this->getParser($format))) {
                // Defaults parsers
                switch ($format) {
                    case 'rst':
                        $this->setParser($format, $parser = new reStructuredText);
                        break;
                    case 'md':
                        $this->setParser('md', $parser = new Markdown);
                }
            }

            if (!is_null($parser)) {
                // Set current loader
                $parser->setLoader($this->getLoader());

                // Parse content
                if (!is_null($document = $parser->parse($path, true))) {
                    // Complete document
                    $document->setFilename($path);
                    $document->setMeta('url', $this->getOption('url.prefix') . $document->getUrlPath());

                    // Add document to list
                    $this->documents[$document->getFilename()] = $document;

                    // Log
                    if (!is_null($this->getLogger())) {
                        $this->getLogger()->debug(sprintf('%s / Document "%s" parsed', __METHOD__, $path));
                    }

                    return $document;
                } else {
                    throw new WebsiteTextException(sprintf('Unable to parse content of document with "%s" parser', get_class($parser)));
                }
            }
        } catch (WebsiteTextException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw new WebsiteTextException(sprintf('Unable to parse document "%s"', $path), 0, $e);
        }

        return null;
    }

    /**
     * Resolve relative path.
     *
     * @param string $initialPath
     * @param string $path
     *
     * @return string|false
     */
    private function resolvRelativePath(string $initialPath, string $path)
    {
        if ((substr($path, 0, 1) == '/' && substr($path, 0, 2) !== '//') ||
            substr($path, 0, 2) == './' ||
            substr($path, 0, 3) == '../') {
            // Unification of directories separators
            $path = str_replace(DIRECTORY_SEPARATOR, '/', $path);
            $finalPath = dirname($initialPath);
            $finalPath = str_replace(DIRECTORY_SEPARATOR, '/', $finalPath);

            // Concatenation
            $finalPath = sprintf('%s/%s', rtrim($finalPath, '/'), ltrim($path, '/'));

            // Replacement of '//'
            $finalPath = preg_replace('#/{2,}#', '/', $finalPath);

            // Replacement of './'
            $finalPath = preg_replace('#/\./#', '/', $finalPath);

            // Replacement of '../'
            do {
                $finalPath = preg_replace('#/([^\\/?%*:|"<>\.]+)/../#', '/', $finalPath, -1, $nbReplacements);
            } while ($nbReplacements > 0);

            if (strpos($finalPath, './') === false) {
                return $finalPath;
            }
        }

        return false;
    }

    /**
     * HTML treatments from parsing.
     *
     * @param \Berlioz\WebsiteText\Document $document
     *
     * @return \Berlioz\WebsiteText\Document
     * @throws \Berlioz\WebsiteText\Exception\WebsiteTextException
     * @throws \Berlioz\HtmlSelector\Exception\HtmlSelectorException
     */
    private function htmlTreatment(Document $document): Document
    {
        $html = $document->getContent();

        // Encoding
        $encoding = mb_detect_encoding($html) ?? 'ascii';
        $html = sprintf('<html><head><meta charset="%s"></head><body>%s</body></html>', $encoding, $html);

        // Load HTML in HtmlSelector library
        $query = Query::loadHtml($html);

        // Replacement of links
        foreach ($query->find('a[href]') as $link) {
            $href = $this->resolvRelativePath($document->getFilename(), $link->attr('href'));

            if ($href !== false && !is_null($documentLinked = $this->getDocument($href))) {
                $link->attr('href', $documentLinked->getUrlPath());
            } else {
                if (!is_null($this->getOption('url.host')) && $this->getOption('url.host_external_blank') === true) {
                    $host = parse_url($link->attr('href'), PHP_URL_HOST);
                    $searchSubdomains = substr($host, 1) == '.';

                    if (!empty($host)) {
                        $addExternal = true;

                        foreach ((array) $this->getOption('url.host') as $internalHost) {
                            if ($host == $internalHost ||
                                ($searchSubdomains === true && $host == substr($internalHost, 1)) ||
                                ($searchSubdomains === true && mb_substr($host, -mb_strlen($internalHost)) == $internalHost)) {
                                $addExternal = false;
                            }
                        }

                        if ($addExternal) {
                            $link
                                ->attr('target', '_blank')
                                ->attr('rel', $this->getOption('url.host_external_rel'));
                        }
                    }
                }
            }
        }

        // Images
        if (!empty($this->getOption('url.images-path'))) {
            foreach ($query->find('img') as $img) {
                $img->attr('src', $this->getOption('url.images-path') . $this->resolvRelativePath($document->getFilename(), $img->attr('src')));
            }
        }

        // Remove H1
        if ($this->getOption('parsing.remove-h1') === true) {
            if (count($title = $query->find('h1:first')) == 1) {
                $document->setTitle(trim($title->text()));
                $title->remove();
            }
        }

        // Summary
        if ($this->getOption('parsing.summary') === true) {
            $document->setSummary($this->extractDocumentSummary($document, $query));
        }

        $document->setContent((string) $query->find('html > body')->html());

        // Log
        if (!is_null($this->getLogger())) {
            $this->getLogger()->debug(sprintf('%s / HTML treatments done on document "%s"', __METHOD__, $document->getFilename()));
        }

        return $document;
    }

    /**
     * Extract document summary from Query.
     *
     * @param \Berlioz\WebsiteText\Document $document
     * @param \Berlioz\HtmlSelector\Query   $query
     *
     * @return \Berlioz\WebsiteText\Summary
     * @throws \Berlioz\HtmlSelector\Exception\HtmlSelectorException
     */
    private function extractDocumentSummary(Document $document, Query $query): Summary
    {
        $summary = new Summary;
        $ids = [];
        $headers = $query->find(':header:not(h1)');

        // Function to treat id
        $prepareId =
            function (string $text): string {
                $id = preg_replace(['/[^\w\s\-]/i', '/\s+/', '/-{2,}/'], ['', '-', '-'], $text);
                $id = trim(mb_strtolower($id), '-');

                return $id;
            };

        $elements = [];
        foreach ($headers as $header) {
            try {
                // Header level
                if ($header->is('h3')) {
                    $headerLevel = 2;
                } elseif ($header->is('h4')) {
                    $headerLevel = 3;
                } elseif ($header->is('h5')) {
                    $headerLevel = 4;
                } elseif ($header->is('h6')) {
                    $headerLevel = 5;
                } else {
                    $headerLevel = 1;
                }

                // Remove old parent
                for ($i = count($elements) - 1; $i >= 0; $i--) {
                    if ($elements[$i]['level'] >= $headerLevel) {
                        array_pop($elements);
                    }
                }

                // Get id of header
                if (is_null($id = $header->attr('id')) || in_array($id, $ids)) {
                    if (is_null($id)) {
                        $id = '';
                        foreach ($elements as $element) {
                            /** @var \Berlioz\WebsiteText\Summary\Element $element */
                            $element = $element['element'];
                            $id .= $prepareId($element->getTitle()) . '-';
                        }
                        $id .= $prepareId($header->text());
                    }

                    // Find new id
                    $idPattern = $id;
                    $i = 1;
                    while ($query->find(sprintf('[id="%s"]', $id))->count() > 0) {
                        $id = sprintf('%s-%d', $idPattern, $i);
                        $i++;
                    }

                    // Set new id to header
                    $header->attr('id', $id);
                }

                // Create summary element
                $summaryElement = new Element;
                $summaryElement->setTitle($header->text());
                $summaryElement->setUrl($document->getUrlPath());
                $summaryElement->setId($id);

                // Add element to summary hierarchy
                {
                    if (($lastElement = end($elements)) !== false) {
                        /** @var \Berlioz\WebsiteText\Summary\Element $lastElement */
                        $lastElement = $lastElement['element'];
                        $lastElement->addSubElement($summaryElement);
                    }

                    $elements[] = ['element' => $summaryElement, 'level' => $headerLevel];

                    if (count($elements) <= 1) {
                        $summary->addSubElement($summaryElement);
                    }
                }
            } catch (\Exception $e) {
            }
        }

        // Log
        if (!is_null($this->getLogger())) {
            $this->getLogger()->debug(sprintf('%s / Summary extracted from document "%s"', __METHOD__, $document->getFilename()));
        }

        return $summary;
    }

    /**
     * Scan.
     *
     * @throws \Berlioz\WebsiteText\Exception\LoaderException
     * @throws \Berlioz\WebsiteText\Exception\WebsiteTextException
     */
    public function scan()
    {
        if (is_null($this->documentsUrls)) {
            // Log
            if (!is_null($this->getLogger())) {
                $this->getLogger()->debug(sprintf('%s / Scan launched', __METHOD__));
            }

            // Load files
            foreach ($this->getLoader()->scan() as $filename) {
                $this->parse($filename);
            }

            // Reset urls
            $this->documentsUrls = [];

            // Documents treatments
            foreach ($this->documents as $document) {
                try {
                    // Add document url
                    $this->documentsUrls[$document->getUrlPath()] = $document->getFilename();

                    // Some treatments
                    $this->htmlTreatment($document);

                    // Summary
                    if (!is_null($this->getSummary())) {
                        $this->getSummary()->addDocument($document);
                    }
                } catch (HtmlSelectorException $e) {
                    throw new WebsiteTextException(sprintf('Unable to treat document "%s", bad html format', $document->getFilename()));
                }
            }

            // Log
            if (!is_null($this->getLogger())) {
                $this->getLogger()->debug(sprintf('%s / Scan finished', __METHOD__));
            }

            // Save to cache
            $this->saveToCacheManager();
        }
    }

    /**
     * Handle.
     *
     * @param \Psr\Http\Message\ServerRequestInterface|string $request ServerRequest object or path
     *
     * @return \Berlioz\WebsiteText\Document|false
     * @throws \Berlioz\WebsiteText\Exception\WebsiteTextException
     */
    public function handle($request)
    {
        if ($request instanceof ServerRequestInterface) {
            $fullPath = $request->getUri()->getPath();

            if (!is_null($websiteTextPath = $request->getAttribute('path'))) {
                if ($fullPath != $websiteTextPath) {
                    $this->options['url.prefix'] = mb_substr($fullPath, 0, mb_strlen($fullPath) - mb_strlen($websiteTextPath));

                    if (mb_substr($this->options['url.prefix'], -1) == '/') {
                        $this->options['url.prefix'] = mb_substr($this->options['url.prefix'], 0, -1);
                    }
                }
            }

            $websiteTextPath = $fullPath;
        } else {
            if (is_string($request)) {
                $websiteTextPath = $request;
            } else {
                throw new \InvalidArgumentException('Argument $request must be a string or an object who implements ServerRequestInterface interface');
            }
        }

        // Load from cache and delete documents referenced
        $this->loadFromCacheManager();
        $this->documents = [];

        // Scan loader
        $this->scan();

        // Find document
        if (isset($this->documentsUrls[$websiteTextPath])) {
            if (!is_null($document = $this->getDocument($this->documentsUrls[$websiteTextPath]))) {
                if (!is_null($summaryElement = $this->getSummary()->findByDocument($document))) {
                    $summaryElement->setSelected(true);
                }

                return $document;
            }
        }

        return false;
    }
}