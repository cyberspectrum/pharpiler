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

namespace CyberSpectrum\PharPiler;

use CyberSpectrum\PharPiler\Filter\Collection;

/**
 * This class is a simple delegator to filters.
 */
class Filter
{
    /**
     * The filter rule collections.
     *
     * @var Collection[]
     */
    private $collections;

    /**
     * Create a new instance.
     *
     * @param Collection[] $collections The filters to apply.
     */
    public function __construct($collections)
    {
        $this->collections = \SplFixedArray::fromArray($collections);
    }

    /**
     * Process the passed file content through the filters.
     *
     * @param string $fileName The filename to process.
     *
     * @param string $content  The binary file content.
     *
     * @return string
     */
    public function process($fileName, $content)
    {
        foreach ($this->collections as $collection) {
            if ($collection->matches($fileName)) {
                $content = $collection->process($content);
            }
        }

        return $content;
    }
}
