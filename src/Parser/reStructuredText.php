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

class reStructuredText implements Parser
{
    use LoaderAwareTrait, GeneratorAwareTrait;
    /** @var \Gregwar\RST\Parser */
    private $rstParser;

    /**
     * Get RST Parser.
     *
     * @return \Gregwar\RST\Parser
     */
    public function getRstParser(): \Gregwar\RST\Parser
    {
        if (is_null($this->rstParser)) {
            $this->rstParser = new \Gregwar\RST\Parser;
        }

        return $this->rstParser;
    }

    /**
     * Set RST Parser.
     *
     * @param \Gregwar\RST\Parser $rstParser
     *
     * @return \Berlioz\WebsiteText\Parser\reStructuredText
     */
    public function setRstParser(\Gregwar\RST\Parser $rstParser): reStructuredText
    {
        $this->rstParser = $rstParser;

        return $this;
    }

    /**
     * Parse content.
     *
     * @param string $content       Html content or filename
     * @param bool   $contentIsFile If content is file
     *
     * @return \Berlioz\WebsiteText\Document Html file
     * @throws \Berlioz\WebsiteText\Exception\WebsiteTextException if loader not declared for second parameter
     * @throws \Berlioz\WebsiteText\Exception\LoaderException if file not exists
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
            $rstDocument = $this->getRstParser()->parse($content);

            $document = new Document($content, $rstDocument->getTitle());
            $document->setRawContent($content);
            $document->setContent((string) $rstDocument->render());

            return $document;
        } catch (\Exception $e) {
            throw new ParserException('An error occurred during parsing of content', 0, $e);
        }
    }
}