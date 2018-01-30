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

namespace Berlioz\WebsiteText\Summary;


class Element extends AbstractElement
{
    /** @var string Title */
    private $title;
    /** @var string|null Url */
    private $url;
    /** @var string|null Id of element */
    private $id;
    /** @var int|null Order */
    private $order;
    /** @var bool Selected ? */
    private $selected = false;

    /**
     * __sleep() magic method.
     */
    public function __sleep(): array
    {
        return array_merge(['title', 'url', 'id', 'order'], parent::__sleep());
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
     * @return static
     */
    public function setTitle(string $title): Element
    {
        $this->title = $title;

        return $this;
    }

    /**
     * Get url.
     *
     * @return null|string
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * Set url.
     *
     * @param null|string $url
     *
     * @return static
     */
    public function setUrl(string $url): Element
    {
        $this->url = $url;

        return $this;
    }

    /**
     * Get id.
     *
     * @return null|string
     */
    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * Set id.
     *
     * @param null|string $id
     *
     * @return static
     */
    public function setId(?string $id): Element
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get order of element.
     *
     * @return int|null
     */
    public function getOrder(): ?int
    {
        return $this->order;
    }

    /**
     * Set order of element.
     *
     * @param int|null $order
     *
     * @return Element
     */
    public function setOrder(?int $order): Element
    {
        $this->order = $order;

        return $this;
    }

    /**
     * Is selected ?
     *
     * @return bool
     */
    public function isSelected(): bool
    {
        return $this->selected;
    }

    /**
     * Set selected.
     *
     * @param bool $selected
     *
     * @return Element
     */
    public function setSelected(bool $selected): Element
    {
        $this->selected = $selected;

        // Set selected
        if (!is_null($parentElement = $this->getParentElement()) && $parentElement instanceof Element) {
            $parentElement->setSelected($selected);
        }

        return $this;
    }
}