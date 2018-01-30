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


use Berlioz\WebsiteText\Loader\LoaderAwareInterface;

interface Parser extends LoaderAwareInterface, GeneratorAwareInterface
{
    /**
     * Parse content.
     *
     * @param string $content       Html content or filename
     * @param bool   $contentIsFile If content is file
     *
     * @return \Berlioz\WebsiteText\Document Document
     * @throws \Berlioz\WebsiteText\Exception\WebsiteTextException if loader not declared for second parameter
     * @throws \Berlioz\WebsiteText\Exception\LoaderException if file not exists
     * @throws \Berlioz\WebsiteText\Exception\ParserException if an error occurred during parsing
     */
    public function parse(string $content, bool $contentIsFile = false): Document;
}