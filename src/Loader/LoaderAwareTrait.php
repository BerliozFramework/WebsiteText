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

trait LoaderAwareTrait
{
    /** @var \Berlioz\WebsiteText\Loader */
    private $loader;

    /**
     * Get loader.
     *
     * @return \Berlioz\WebsiteText\Loader
     */
    public function getLoader(): ?Loader
    {
        return $this->loader;
    }

    /**
     * Set loader.
     *
     * @param \Berlioz\WebsiteText\Loader
     */
    public function setLoader(Loader $loader)
    {
        $this->loader = $loader;
    }
}