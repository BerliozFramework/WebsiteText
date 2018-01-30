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


use Berlioz\WebsiteText\Summary\AbstractElement;
use Berlioz\WebsiteText\Summary\Element;

class Summary extends AbstractElement
{
    /**
     * Find by document.
     *
     * @param \Berlioz\WebsiteText\Document $document
     *
     * @return \Berlioz\WebsiteText\Summary\Element|null
     */
    public function findByDocument(Document $document): ?Element
    {
        if (!empty($titles = $document->getMeta('index'))) {
            $titles = explode(';', $titles);

            return $this->findByTitles($titles);
        }

        return null;
    }

    /**
     * Find by titles.
     *
     * @param array $titles
     *
     * @return \Berlioz\WebsiteText\Summary\Element|null
     */
    public function findByTitles(array $titles): ?Element
    {
        $titles = $this->filterTitles($titles);

        // Search
        if (($nbTitles = count($titles)) > 0) {
            $iTitle = 0;
            $element = $this;
            do {
                $element = $element->getElementByTitle($titles[$iTitle]);
                $iTitle++;
            } while (!is_null($element) && $iTitle < $nbTitles);

            return $element;
        }

        return null;
    }

    /**
     * Add document to summary.
     *
     * @param \Berlioz\WebsiteText\Document $document
     *
     * @return \Berlioz\WebsiteText\Summary
     */
    public function addDocument(Document $document): Summary
    {
        if (!empty($titles = $document->getMeta('index'))) {
            $titles = explode(';', $titles);
            $titles = $this->filterTitles($titles);
            $nbTitles = count($titles);
            $parentElement = $this;

            for ($i = 0; $i < $nbTitles; $i++) {
                if (is_null($element = $parentElement->getElementByTitle($titles[$i]))) {
                    $element = new Element;
                    $element->setTitle($titles[$i]);

                    if ($i + 1 == $nbTitles) {
                        $element->setUrl($document->getUrlPath());
                    }
                    $element->setOrder($document->getMeta('index-order'));

                    $parentElement->addSubElement($element);
                }

                $parentElement = $element;
            }
        }

        return $this;
    }
}