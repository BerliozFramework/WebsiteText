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

class FileSystem extends AbstractLoader
{
    /** @var string[] Files */
    private $files;

    /**
     * Dir to array.
     *
     * @param string $dir    Directory to scan
     * @param string $prefix Prefix
     *
     * @return array
     * @throws \Berlioz\WebsiteText\Exception\LoaderException
     */
    private function getAllFiles(string $dir, string $prefix = '')
    {
        $files = [];

        foreach (scandir($dir) as $filename) {
            $fullFilename = rtrim($dir, '\\/') . '/' . $filename;
            $partialFilename = rtrim($prefix, '\\/') . '/' . $filename;

            if (is_file($fullFilename) && is_readable($fullFilename)) {
                if ($this->testFilter($fullFilename)) {
                    $files[] = str_replace('\\', '/', $partialFilename);
                }
            } else {
                if (!in_array($filename, ['.', '..']) && is_dir($fullFilename)) {
                    $files = array_merge($files, $this->getAllFiles($fullFilename, $partialFilename));
                }
            }
        }

        return $files;
    }

    /**
     * @inheritdoc
     */
    public function getUniqId(): string
    {
        return sha1($this->getBasePath());
    }

    /**
     * @inheritdoc
     */
    public function scan(): array
    {
        if (is_null($this->files)) {
            $this->files = $this->getAllFiles($this->getBasePath());
        }

        return $this->files;
    }

    /**
     * @inheritdoc
     */
    public function load(string $path): string
    {
        $fullPath = sprintf('%s/%s', rtrim($this->getBasePath(), '\\/'), ltrim($path, '\\/'));

        if (($content = file_get_contents($fullPath)) === false) {
            throw new LoaderException(sprintf('Unable to load file "%s", in "%s" directory', $path, $this->getBasePath()));
        }

        return $content;
    }
}