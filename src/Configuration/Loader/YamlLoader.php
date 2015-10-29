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

namespace CyberSpectrum\PharPiler\Configuration\Loader;

use InvalidArgumentException;
use Symfony\Component\Config\Loader\FileLoader;
use Symfony\Component\Yaml\Parser;

/**
 * This class loads the configuration from a YAML file.
 */
class YamlLoader extends FileLoader
{
    /**
     * {@inheritDoc}
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function load($resource, $type = null)
    {
        $path         = $this->locator->locate($resource);
        $parser       = new Parser();
        $configValues = $parser->parse(file_get_contents($path));

        if (null === $configValues) {
            return [];
        }

        $configValues = $this->parseImports($configValues, $path);

        return $configValues;
    }

    /**
     * {@inheritDoc}
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function supports($resource, $type = null)
    {
        return is_string($resource) && 'yml' === pathinfo($resource, PATHINFO_EXTENSION);
    }

    /**
     * Parses all imports.
     *
     * @param array  $content The current config content.
     *
     * @param string $file    The file to import.
     *
     * @return array
     *
     * @throws InvalidArgumentException When the imports key is not an array or the elements are not arrays.
     */
    private function parseImports($content, $file)
    {
        if (!isset($content['imports'])) {
            return $content;
        }

        if (!is_array($content['imports'])) {
            throw new InvalidArgumentException(
                sprintf('The "imports" key should contain an array in %s. Check your YAML syntax.', $file)
            );
        }

        $imports = [$content];
        unset($imports[0]['imports']);
        foreach ($content['imports'] as $import) {
            if (!is_array($import)) {
                throw new InvalidArgumentException(
                    sprintf('The values in the "imports" key should be arrays in %s. Check your YAML syntax.', $file)
                );
            }

            $this->setCurrentDir(dirname($file));
            $imports[] = $this->import(
                $import['resource'],
                null,
                isset($import['ignore_errors']) ? (bool) $import['ignore_errors'] : false,
                $file
            );
        }

        return call_user_func_array('array_merge_recursive', $imports);
    }
}
