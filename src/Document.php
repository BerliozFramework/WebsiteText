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


class Document
{
    /** @var array Default pages */
    public static $defaultPages = ['index', 'index.html'];
    /** @var string Path of document */
    private $filename;
    /** @var \DateTime Date time of document */
    private $datetime;
    /** @var string Title */
    private $title;
    /** @var string Raw content */
    private $rawContent;
    /** @var string Parsed content */
    private $content;
    /** @var array Meta data */
    private $metas;
    /** @var \Berlioz\WebsiteText\Summary Summary */
    private $summary;

    /**
     * Document constructor.
     *
     * @param string|null $rawContent Raw content
     * @param string|null $title      Title
     * @param string|null $filename   Filename
     * @param array       $meta       Metas
     */
    public function __construct(string $rawContent = null, string $title = null, string $filename = null, array $meta = [])
    {
        $this->rawContent = $rawContent;
        $this->title = $title;
        $this->filename = $filename;
        $this->metas = $meta;
    }

    /**
     * __toString() PHP magic method.
     */
    public function __toString()
    {
        return $this->getContent() ?? $this->getRawContent() ?? '';
    }

    /**
     * Get filename of document.
     *
     * @param bool $withExtension With extension ? (default: true)
     *
     * @return string
     */
    public function getFilename(bool $withExtension = true): string
    {
        if ($withExtension === false && ($extPos = strripos($this->filename, '.')) !== false && $extPos > 0) {
            return substr($this->filename, 0, $extPos);
        } else {
            return $this->filename;
        }
    }

    /**
     * Set filename of document.
     *
     * @param string $filename
     *
     * @return Document
     */
    public function setFilename(string $filename): Document
    {
        $this->filename = $filename;

        return $this;
    }

    /**
     * Get date time.
     *
     * @return \DateTime
     */
    public function getDatetime(): \DateTime
    {
        return $this->datetime ?? new \DateTime('now');
    }

    /**
     * Set date time.
     *
     * @param \DateTime $datetime
     *
     * @return Document
     */
    public function setDatetime(\DateTime $datetime): Document
    {
        $this->datetime = $datetime;

        return $this;
    }

    /**
     * Get title.
     *
     * @return string
     */
    public function getTitle(): ?string
    {
        return $this->title;
    }

    /**
     * Set title.
     *
     * @param string $title
     *
     * @return Document
     */
    public function setTitle(string $title): Document
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Get original content of document.
     *
     * @return mixed
     */
    public function getRawContent()
    {
        return $this->rawContent;
    }

    /**
     * Set original content of document.
     *
     * @param mixed $rawContent
     *
     * @return Document
     */
    public function setRawContent($rawContent)
    {
        $this->rawContent = $rawContent;

        return $this;
    }

    /**
     * Get parsed content.
     *
     * @return string
     */
    public function getContent(): ?string
    {
        return $this->content;
    }

    /**
     * Set parsed content.
     *
     * @param string $content
     *
     * @return Document
     */
    public function setContent(string $content): Document
    {
        $this->content = $content;

        return $this;
    }

    /**
     * Get metas of document.
     *
     * @return array
     */
    public function getMetas(): array
    {
        return $this->metas;
    }

    /**
     * Get meta.
     *
     * @param string $name
     *
     * @return mixed|null
     */
    public function getMeta(string $name)
    {
        return $this->metas[$name] ?? null;
    }

    /**
     * Set meta.
     *
     * @param string $name
     * @param mixed  $value
     *
     * @return \Berlioz\WebsiteText\Document
     */
    public function setMeta(string $name, $value): Document
    {
        $this->metas[$name] = $value;

        return $this;
    }

    /**
     * Set metas of document.
     *
     * @param array $metas
     *
     * @return Document
     */
    public function setMetas(array $metas): Document
    {
        $this->metas = $metas;

        return $this;
    }

    /**
     * Get url path.
     *
     * @return string
     */
    public function getUrlPath(): string
    {
        $path = $this->getMeta('url') ?? $this->getFilename(false);
        $absolute = substr($path, 0, 1) == '/';
        $path = ltrim($path, '/');

        if (in_array($basename = basename($path), self::$defaultPages)) {
            $path = substr($path, 0, -mb_strlen($basename));
        }

        return ($absolute ? '/' : '') . $path;
    }

    /**
     * Get summary.
     *
     * @return \Berlioz\WebsiteText\Summary
     */
    public function getSummary(): ?Summary
    {
        return $this->summary;
    }

    /**
     * Set summary.
     *
     * @param \Berlioz\WebsiteText\Summary $summary
     *
     * @return Document
     */
    public function setSummary(Summary $summary): Document
    {
        $this->summary = $summary;

        return $this;
    }
}