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


class ParsedownBerlioz extends \ParsedownExtra
{
    /** @var array Metas */
    private $metas;

    /**
     * ParsedownBerlioz constructor.
     *
     * @throws \Exception
     */
    public function __construct()
    {
        if (parent::version < '0.7.0') {
            throw new \Exception('ParsedownBerlioz requires a later version of ParsedownExtra');
        }

        parent::__construct();

        $this->BlockTypes['!'] [] = 'Meta';
    }

    /**
     * Get metas.
     *
     * @return array
     */
    public function getMetas(): array
    {
        return $this->metas ?? [];
    }

    /**
     * Block metas.
     *
     * @param $line
     *
     * @return void|array
     */
    protected function blockMeta($line)
    {
        $matches = [];

        if (preg_match('/^!meta \s+ (?<name> [\w\-]+) \s+ (?<value> .+ ) \s*$/xi', $line['text'], $matches) == 1) {
            $name = trim($matches['name']);
            $value = trim($matches['value']);

            switch ($value) {
                case 'true':
                case 'false':
                    $value = boolval($value);
                    break;
            }

            // Add metas
            if (isset($this->metas[$name])) {
                if (is_array($this->metas[$name])) {
                    $this->metas[$name][] = $value;
                } else {
                    $this->metas[$name] = [$this->metas[$name], $value];
                }
            } else {
                $this->metas[$name] = $value;
            }

            return ['hidden' => true];
        }

        return;
    }

    public function text($text): string
    {
        $this->metas = [];

        return parent::text($text);
    }
}