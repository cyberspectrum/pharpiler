<?php

/**
 * This file is part of cyberspectrum/pharpiler.
 *
 * (c) Christian Schiffler <c.schiffler@cyberspectrum.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * This project is provided in good faith and hope to be usable by anyone.
 *
 * @package    cyberspectrum/pharpiler
 * @author     Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @copyright  2015 Christian Schiffler <c.schiffler@cyberspectrum.de>
 * @license    https://github.com/cyberspectrum/pharpiler/blob/master/LICENSE MIT
 * @link       https://github.com/cyberspectrum/pharpiler
 * @filesource
 */

namespace CyberSpectrum\PharPiler\Filter;

/**
 * This class is a simple string replacing filter for inserting a timestamp.
 */
class ReplaceStringWithTimestamp extends AbstractFilter
{
    /**
     * The string portion to search.
     *
     * @var string
     */
    private $search;

    /**
     * The template to insert.
     *
     * @var string
     */
    private $format;

    /**
     * How long into the future do we want the timestamp to be.
     *
     * @var int
     */
    private $ahead;

    /**
     * Create a new instance.
     *
     * @param array $config The configuration.
     */
    public function __construct($config)
    {
        $this->search = $config['search'];
        $this->format = $config['format'];
        $this->ahead  = $config['ahead'];
    }

    /**
     * {@inheritDoc}
     */
    public function apply($content)
    {
        $value = str_replace('@@warning_time@@', (time() + $this->ahead), $this->format);

        return str_replace($this->search, $value, $content);
    }
}
