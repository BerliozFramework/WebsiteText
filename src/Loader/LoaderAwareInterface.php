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


use Berlioz\WebsiteText\Loader;

interface LoaderAwareInterface
{
    /**
     * Get loader.
     *
     * @return \Berlioz\WebsiteText\Loader
     */
    public function getLoader(): ?Loader;

    /**
     * Set loader.
     *
     * @param \Berlioz\WebsiteText\Loader
     */
    public function setLoader(Loader $loader);
}