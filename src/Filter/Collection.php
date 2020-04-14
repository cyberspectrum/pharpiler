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

use SplFixedArray;
use Symfony\Component\Finder\Glob;

/**
 * This class is a simple collection over filters.
 */
class Collection
{
    /**
     * The regexps to match.
     *
     * @var string[]
     */
    private $matchRegexps = [];

    /**
     * The regexps not to match.
     *
     * @var string[]
     */
    private $noMatchRegexps = [];

    /**
     * The filters.
     *
     * @var SplFixedArray|AbstractFilter[]
     */
    private $filters;

    /**
     * Create a new instance.
     *
     * @param array            $matchRegexps The regexes to match files.
     *
     * @param AbstractFilter[] $filters      The filters to apply.
     */
    public function __construct(array $matchRegexps, array $filters)
    {
        foreach ($matchRegexps as $pattern) {
            $this->matchRegexps[] = $this->toRegex($pattern);
        }

        $this->filters = SplFixedArray::fromArray($filters);
    }

    /**
     * Check if the regex match against the passed filename.
     *
     * @param string $file The filename.
     *
     * @return bool
     */
    public function matches(string $file): bool
    {
        // should at least not match one rule to exclude
        foreach ($this->noMatchRegexps as $regex) {
            if (preg_match($regex, $file)) {
                return false;
            }
        }

        if (!$this->matchRegexps) {
            return true;
        }

        // should at least match one rule
        foreach ($this->matchRegexps as $regex) {
            if (preg_match($regex, $file)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Process the passed content.
     *
     * @param string $content The content.
     *
     * @return string
     */
    public function process(string $content): string
    {
        foreach ($this->filters as $filter) {
            $content = $filter->apply($content);
        }

        return $content;
    }

    /**
     * Converts glob to regexp.
     *
     * PCRE patterns are left unchanged.
     * Glob strings are transformed with Glob::toRegex().
     *
     * @param string $str Pattern: glob or regexp.
     *
     * @return string regexp corresponding to a given glob or regexp
     */
    protected function toRegex(string $str): string
    {
        return $this->isRegex($str) ? $str : Glob::toRegex($str, false, false);
    }

    /**
     * Checks whether the string is a regex.
     *
     * @param string $str The string to match.
     *
     * @return bool Whether the given string is a regex
     */
    protected function isRegex(string $str): bool
    {
        if (preg_match('/^(.{3,}?)[imsxuADU]*$/', $str, $matches)) {
            $start = substr($matches[1], 0, 1);
            $end   = substr($matches[1], -1);

            if ($start === $end) {
                return !preg_match('/[*?[:alnum:] \\\\]/', $start);
            }

            foreach (array(array('{', '}'), array('(', ')'), array('[', ']'), array('<', '>')) as $delimiters) {
                if ($start === $delimiters[0] && $end === $delimiters[1]) {
                    return true;
                }
            }
        }

        return false;
    }
}
