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
 * This class is a simple string replacing filter.
 */
class ReplaceStringFilter extends AbstractFilter
{
    /**
     * The string portion to search.
     *
     * @var string
     */
    private $search;

    /**
     * The string portion to replace.
     *
     * @var string
     */
    private $replace;

    /**
     * Create a new instance.
     *
     * @param array $config The configuration.
     */
    public function __construct(array $config)
    {
        $this->search  = $config['search'];
        $this->replace = $config['replace'];
    }

    /**
     * {@inheritDoc}
     */
    public function apply(string $content): string
    {
        return str_replace($this->search, $this->replace, $content);
    }
}
