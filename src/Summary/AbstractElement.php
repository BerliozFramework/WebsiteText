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


abstract class AbstractElement implements \IteratorAggregate, \Countable
{
    /** @var \Berlioz\WebsiteText\Summary\AbstractElement|null Parent element */
    protected $parentElement;
    /** @var \Berlioz\WebsiteText\Summary\Element[] $subElements */
    protected $subElements = [];

    /**
     * __sleep() magic method.
     */
    public function __sleep(): array
    {
        return ['subElements'];
    }

    /**
     * __wakeup() magic method.
     */
    public function __wakeup()
    {
        // Reset parents
        foreach ($this->subElements as $element) {
            $element->setParentElement($this);
        }
    }

    /**
     * Create new iterator.
     *
     * @return \ArrayIterator
     */
    public function getIterator()
    {
        return new \ArrayIterator($this->getSubElements());
    }

    /**
     * Count sub elements.
     *
     * @return int
     */
    public function count()
    {
        return count($this->subElements);
    }

    /**
     * Filter titles.
     *
     * @param string[] $titles
     *
     * @return string[]
     */
    protected function filterTitles(array $titles): array
    {
        $titles = array_map('trim', $titles);
        $titles = array_filter($titles);

        return $titles;
    }

    /**
     * Get parent element.
     *
     * @return \Berlioz\WebsiteText\Summary\AbstractElement|null
     */
    public function getParentElement(): ?AbstractElement
    {
        return $this->parentElement;
    }

    /**
     * @param \Berlioz\WebsiteText\Summary\AbstractElement|null $parentElement
     *
     * @return AbstractElement
     */
    public function setParentElement(?AbstractElement $parentElement): AbstractElement
    {
        $this->parentElement = $parentElement;

        return $this;
    }

    /**
     * Order sub elements.
     */
    protected function orderSubElements()
    {
        usort($this->subElements,
            function ($el1, $el2) {
                /** @var \Berlioz\WebsiteText\Summary\Element $el1 */
                /** @var \Berlioz\WebsiteText\Summary\Element $el2 */
                if ($el1->getOrder() === $el2->getOrder()) {
                    if (is_null($el1->getOrder())) {
                        return 0;
                    } else {
                        return strcasecmp($el1->getTitle(), $el2->getTitle());
                    }
                } else {
                    if (is_null($el1->getOrder()) && !is_null($el2->getOrder())) {
                        return 1;
                    } elseif (!is_null($el1->getOrder()) && is_null($el2->getOrder())) {
                        return -1;
                    } else {
                        return ($el1->getOrder() < $el2->getOrder()) ? -1 : 1;
                    }
                }
            });
    }

    /**
     * Get element by title.
     *
     * @param string $title
     *
     * @return \Berlioz\WebsiteText\Summary\Element|null
     */
    public function getElementByTitle(string $title)
    {
        foreach ($this->subElements as $element) {
            if ($element->getTitle() == $title) {
                return $element;
            }
        }

        return null;
    }

    /**
     * Get sub elements.
     *
     * @return \Berlioz\WebsiteText\Summary\Element[]
     */
    public function getSubElements(): array
    {
        return $this->subElements;
    }

    /**
     * Set sub eel
     *
     * @param \Berlioz\WebsiteText\Summary\Element[] $subElements
     *
     * @return static
     */
    public function setSubElements(array $subElements): AbstractElement
    {
        // Filter elements
        $subElements =
            array_filter(
                $subElements,
                function ($value) {
                    return $value instanceof Element;
                });

        // Set sub elements
        $this->subElements = $subElements;

        // Set parent
        foreach ($subElements as $element) {
            $element->setParentElement($this);
        }

        // Order
        $this->orderSubElements();

        return $this;
    }

    /**
     * Add sub element.
     *
     * @param \Berlioz\WebsiteText\Summary\Element $element
     *
     * @return static
     */
    public function addSubElement(Element $element): AbstractElement
    {
        $this->subElements[] = $element;
        $element->setParentElement($this);

        // Order
        $this->orderSubElements();

        return $this;
    }
}