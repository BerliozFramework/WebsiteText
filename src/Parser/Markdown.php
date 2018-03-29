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

namespace Berlioz\WebsiteText\Parser;


use Berlioz\WebsiteText\Document;
use Berlioz\WebsiteText\Exception\ParserException;
use Berlioz\WebsiteText\Exception\WebsiteTextException;
use Berlioz\WebsiteText\GeneratorAwareTrait;
use Berlioz\WebsiteText\Loader\LoaderAwareTrait;
use Berlioz\WebsiteText\Parser;

class Markdown implements Parser
{
    use LoaderAwareTrait, GeneratorAwareTrait;
    /** @var \Berlioz\WebsiteText\Parser\ParsedownBerlioz Parser */
    private $parsedownBerlioz;

    /**
     * Get ParseDownExtra library.
     *
     * @return \Berlioz\WebsiteText\Parser\ParsedownBerlioz
     * @throws \Berlioz\WebsiteText\Exception\WebsiteTextException
     */
    private function getParsedownBerlioz(): ParsedownBerlioz
    {
        if (is_null($this->parsedownBerlioz)) {
            $this->parsedownBerlioz = new ParsedownBerlioz();
        }

        return $this->parsedownBerlioz;
    }

    /**
     * Parse content.
     *
     * @param string $content       Html content or filename
     * @param bool   $contentIsFile If content is file
     *
     * @return \Berlioz\WebsiteText\Document
     * @throws \Berlioz\WebsiteText\Exception\WebsiteTextException if loader not declared for second parameter
     * @throws \Berlioz\WebsiteText\Exception\LoaderException if file not exists
     * @throws \Berlioz\WebsiteText\Exception\ParserException if an error occurred during parsing
     */
    public function parse(string $content, bool $contentIsFile = false): Document
    {
        // Load file
        if ($contentIsFile === true) {
            if (!is_null($this->getLoader())) {
                $filename = $content;
                $content = $this->getLoader()->load($filename);
            } else {
                throw new WebsiteTextException('Unable to find a loader');
            }
        }

        try {
            $document = new Document($content);
            $document->setRawContent($content);
            $document->setContent((string) $this->getParsedownBerlioz()->parse($content));
            $document->setMetas($this->getParsedownBerlioz()->getMetas());

            return $document;
        } catch (\Exception $e) {
            throw new ParserException('An error occurred during parsing of content', 0, $e);
        }
    }
}