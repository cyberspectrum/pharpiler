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

namespace CyberSpectrum\PharPiler\Configuration;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

/**
 * Generic path following array handler.
 */
class ConfigurationValues
{
    /**
     * Path separator to use.
     */
    const PATH_SEPARATOR = '.';

    /**
     * The data array.
     *
     * @var array
     */
    private $data;

    /**
     * The parameter bag to use for resolving.
     *
     * @var ParameterBagInterface
     */
    private $parameterBag;

    /**
     * Create a new instance.
     *
     * @param array                 $data         The data.
     *
     * @param ParameterBagInterface $parameterBag The parameter bag to use for resolving.
     */
    public function __construct(array $data, ParameterBagInterface $parameterBag)
    {
        $this->data         = (array) $data;
        $this->parameterBag = $parameterBag;
    }

    /**
     * Split the path into chunks.
     *
     * @param string $path The path to split.
     *
     * @return array
     */
    protected function splitPath($path)
    {
        return array_map(
            [$this, 'unescape'],
            preg_split('#(?<!\\\)\\' . static::PATH_SEPARATOR . '#', $this->parameterBag->resolveValue($path))
        );
    }

    /**
     * Escape a string to be used as literal path.
     *
     * @param string $path The string to escape.
     *
     * @return string
     */
    public function unescape($path)
    {
        return str_replace('\\' . static::PATH_SEPARATOR, static::PATH_SEPARATOR, $path);
    }

    /**
     * Escape a string to be used as literal path.
     *
     * @param string $path The string to escape.
     *
     * @return string
     */
    public function escape($path)
    {
        return str_replace(static::PATH_SEPARATOR, '\\' . static::PATH_SEPARATOR, $path);
    }

    /**
     * Retrieve a value.
     *
     * @param string $path The path of the value.
     *
     * @return array|null
     */
    public function get($path)
    {
        // special case, root element.
        if ($path === static::PATH_SEPARATOR) {
            return $this->data;
        }

        $chunks = $this->splitPath($path);
        $scope  = $this->data;

        if (empty($chunks)) {
            return null;
        }

        while (null !== ($sub = array_shift($chunks))) {
            if (isset($scope[$sub])) {
                $scope = $scope[$sub];
            } else {
                return null;
            }
        }

        return $this->parameterBag->resolveValue($scope);
    }

    /**
     * Check if a value exists.
     *
     * @param string $path The path of the value.
     *
     * @return bool
     */
    public function has($path)
    {
        $chunks = $this->splitPath($path);
        $scope  = $this->data;

        if (empty($chunks)) {
            return false;
        }

        while (null !== ($sub = array_shift($chunks))) {
            if (is_array($scope) && array_key_exists($sub, $scope)) {
                $scope = $scope[$sub];
            } else {
                return false;
            }
        }

        return true;
    }

    /**
     * Check if a given path has an empty value (or does not exist).
     *
     * @param string $path The sub path to be checked.
     *
     * @return bool
     */
    public function isEmpty($path)
    {
        return (null === ($value = $this->get($path))) || empty($value);
    }
}
