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
use Berlioz\WebsiteText\Loader;

abstract class AbstractLoader implements Loader
{
    const FILTER_ALL = 0;
    const FILTER_INCLUDE = 1;
    const FILTER_EXCLUDE = 2;
    /** @var string Base path */
    private $basePath;
    /** @var string[] Filter includes */
    private $filterIncludes;
    /** @var string[] Filter excludes */
    private $filterExcludes;

    /**
     * AbstractLoader constructor.
     *
     * @param string $basePath       Base path
     * @param array  $filterIncludes Filter includes
     * @param array  $filterExcludes Filter excludes
     */
    public function __construct(string $basePath = '', array $filterIncludes = [], array $filterExcludes = [])
    {
        $this->basePath = $basePath;
        $this->filterIncludes = $filterIncludes;
        $this->filterExcludes = $filterExcludes;
    }

    /**
     * Get base path.
     *
     * @return string
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Set base path.
     *
     * @param string[] $basePath
     *
     * @return \Berlioz\WebsiteText\Loader
     */
    public function setBasePath(array $basePath): Loader
    {
        $this->basePath = $basePath;

        return $this;
    }

    /**
     * Get filter includes.
     *
     * @return string[]
     */
    public function getFilterIncludes(): array
    {
        return $this->filterIncludes;
    }

    /**
     * Set filter includes.
     *
     * @param string[] $filterIncludes
     *
     * @return \Berlioz\WebsiteText\Loader\AbstractLoader
     */
    public function setFilterIncludes(array $filterIncludes): AbstractLoader
    {
        $this->filterIncludes = $filterIncludes;

        return $this;
    }

    /**
     * Get filter excludes.
     *
     * @return string[]
     */
    public function getFilterExcludes(): array
    {
        return $this->filterExcludes;
    }

    /**
     * Set filter excludes.
     *
     * @param string $filterExcludes
     *
     * @return \Berlioz\WebsiteText\Loader\AbstractLoader
     */
    public function setFilterExcludes(string $filterExcludes): AbstractLoader
    {
        $this->filterExcludes = $filterExcludes;

        return $this;
    }

    /**
     * Test filters includes/excludes.
     *
     * @param string $path Path to test
     * @param int    $type Constant of class (FILTER_ALL, FILTER_INCLUDE, FILTER_EXCLUDE)
     *
     * @return bool
     * @throws \Berlioz\WebsiteText\Exception\LoaderException
     */
    public function testFilter(string $path, int $type = self::FILTER_ALL): bool
    {
        switch ($type) {
            case self::FILTER_ALL:
                if ($this->testFilter($path, self::FILTER_INCLUDE)) {
                    if ($this->testFilter($path, self::FILTER_EXCLUDE)) {
                        return true;
                    }
                }
                break;
            case self::FILTER_INCLUDE:
                if (empty($this->getFilterIncludes())) {
                    return true;
                } else {
                    foreach ($this->getFilterIncludes() as $included) {
                        if (($regexResult = @preg_match(sprintf('#%s#i', str_replace('#', '\\#', $included)), $path)) == 1) {
                            return true;
                        }

                        if ($regexResult === false) {
                            throw new LoaderException(sprintf('Invalid filter format: "%s", must be a valid regex', $included));
                        }
                    }
                }
                break;
            case self::FILTER_EXCLUDE:
                if (!empty($this->getFilterExcludes())) {
                    foreach ($this->getFilterExcludes() as $excluded) {
                        if (($regexResult = @preg_match(sprintf('#%s#i', str_replace('#', '\\#', $excluded)), $path)) == 1) {
                            return false;
                        }

                        if ($regexResult === false) {
                            throw new LoaderException(sprintf('Invalid filter format: "%s", must be a valid regex', $excluded));
                        }
                    }
                }

                return true;
        }

        return false;
    }
}