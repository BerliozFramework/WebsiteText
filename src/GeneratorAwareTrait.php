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


trait GeneratorAwareTrait
{
    /** @var \Berlioz\WebsiteText\Generator Generator */
    private $generator;

    /**
     * Get generator.
     *
     * @return \Berlioz\WebsiteText\Generator|null
     */
    public function getGenerator(): ?Generator
    {
        return $this->generator;
    }

    /**
     * Set generator.
     *
     * @param \Berlioz\WebsiteText\Generator $generator
     */
    public function setGenerator(Generator $generator)
    {
        $this->generator = $generator;
    }
}