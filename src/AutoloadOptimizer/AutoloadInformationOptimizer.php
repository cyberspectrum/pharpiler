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

namespace CyberSpectrum\PharPiler\AutoloadOptimizer;

use Composer\Autoload\ClassLoader;

/**
 * This class optimizes the information of the composer autoloader for the contents of a phar file.
 */
class AutoloadInformationOptimizer
{
    /**
     * The list of psr0 autoload information.
     *
     * @var array
     */
    private $psr0;

    /**
     * The list of psr4 autoload information.
     *
     * @var array
     */
    private $psr4;

    /**
     * The class maps.
     *
     * @var string[]
     */
    private $classMap;

    /**
     * The include paths.
     *
     * @var string[]
     */
    private $includePaths;

    /**
     * The additional autoload files.
     *
     * @var string[]
     */
    private $autoloadFiles;

    /**
     * The whitelist of classes.
     *
     * @var string[string]
     */
    private $whitelist;

    /**
     * The root path to strip from file names.
     *
     * @var string
     */
    private $strip;

    /**
     * The length of the root path to strip.
     *
     * @var string
     */
    private $stripLength;

    /**
     * Create a new instance.
     *
     * @param string[] $whiteList   The file whitelist.
     *
     * @param string   $projectRoot The composer project root.
     *
     * @return AutoloadInformationOptimizer
     */
    public static function create($whiteList, $projectRoot)
    {
        $vendorDir = $projectRoot . '/vendor';
        /** @var ClassLoader $projectLoader */
        $projectLoader = require $vendorDir . '/autoload.php';
        if (\Phar::running()) {
            $projectLoader->unregister();
        }

        return new self(
            $projectLoader->getPrefixes(),
            $projectLoader->getPrefixesPsr4(),
            $projectLoader->getClassMap(),
            (file_exists($file1 = $vendorDir . '/composer/include_paths.php') ? (require $file1) : []),
            (file_exists($file2 = $vendorDir . '/composer/autoload_files.php') ? (require $file2) : []),
            $whiteList,
            $projectRoot
        );
    }

    /**
     * Create a new instance.
     *
     * @param array  $psr0          The list of psr0 autoload information.
     *
     * @param array  $psr4          The list of psr4 autoload information.
     *
     * @param array  $classMap      The list of classmap autoload information.
     *
     * @param array  $includePaths  The list of additional include paths.
     *
     * @param array  $autoloadFiles The list of additional custom autoload files.
     *
     * @param array  $whiteList     The list of whitelisted entries (files embedded into the phar).
     *
     * @param string $projectRoot   The project root path.
     */
    public function __construct($psr0, $psr4, $classMap, $includePaths, $autoloadFiles, $whiteList, $projectRoot)
    {
        $this->psr0          = $psr0;
        $this->psr4          = $psr4;
        $this->classMap      = $classMap;
        $this->includePaths  = $includePaths;
        $this->autoloadFiles = $autoloadFiles;
        $this->strip         = $projectRoot;
        $this->stripLength   = strlen($this->strip);

        foreach ($whiteList as $entry) {
            if ($entry[0] !== '/') {
                $entry = '/' . $entry;
            }
            while ($entry !== '.' && $entry !== '/') {
                $this->whitelist[$entry] = $entry;

                $entry = dirname($entry);

                if (isset($this->whitelist[$entry])) {
                    break;
                }
            }
        }
    }

    /**
     * Retrieve the cleaned psr-0 list.
     *
     * @return string[]
     */
    public function getPsr0()
    {
        return $this->removePrefix($this->psr0);
    }

    /**
     * Retrieve the cleaned psr-0 list.
     *
     * @return string[]
     */
    public function getPsr4()
    {
        return $this->removePrefix($this->psr4);
    }

    /**
     * Retrieve the cleaned psr-0 list.
     *
     * @return string[]
     */
    public function getClassmap()
    {
        return $this->removePrefix($this->classMap);
    }

    /**
     * Retrieve the include paths.
     *
     * @return string[]
     */
    public function getIncludePaths()
    {
        return $this->filterPaths($this->includePaths);
    }

    /**
     * Retrieve the include paths.
     *
     * @return string[]
     */
    public function getAutoloadFiles()
    {
        return $this->filterPaths($this->autoloadFiles);
    }

    /**
     * Clean the paths of an autoload array.
     *
     * @param string[]|array[] $array The array to clean.
     *
     * @return array
     */
    private function removePrefix($array)
    {
        $result = [];
        foreach ($array as $key => $value) {
            if (is_array($value)) {
                if ($subResult = $this->removePrefix($value)) {
                    $result[$key] = $subResult;
                }
                continue;
            }

            $value = realpath($value);
            if (empty($value)) {
                continue;
            }

            if (substr($value, 0, $this->stripLength) === $this->strip) {
                $value = substr($value, $this->stripLength);
                if (!isset($this->whitelist[$value])) {
                    continue;
                }

                $result[$key] = '@@BASEDIR@@' . $value;
                continue;
            }

            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * Filter the passed path list against the white list.
     *
     * @param string[] $paths The paths to filter.
     *
     * @return array
     */
    private function filterPaths($paths)
    {
        $result = [];
        foreach ($paths as $value) {
            $value = realpath($value);
            if (empty($value)) {
                continue;
            }

            if (!isset($this->whitelist[$value])) {
                continue;
            }

            $result[] = $value;
        }

        return $result;
    }
}
