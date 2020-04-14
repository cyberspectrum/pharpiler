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

use CyberSpectrum\PharPiler\Composer\ComposerInformation;
use CyberSpectrum\PharPiler\Configuration\ConfigurationValues;
use CyberSpectrum\PharPiler\Phar;
use CyberSpectrum\PharPiler\Phar\PharWriter;

/**
 * This class is the project being compiled.
 */
class Project
{
    /**
     * The phar file being built.
     *
     * @var Phar
     */
    private $phar;

    /**
     * The composer information.
     *
     * @var ComposerInformation
     */
    private $composer;

    /**
     * The configuration.
     *
     * @var ConfigurationValues
     */
    private $configuration;

    /**
     * The filters to process.
     *
     * @var Filter
     */
    private $filters;

    /**
     * Create a new instance.
     *
     * @param ConfigurationValues $configuration The configuration.
     *
     * @param Filter              $filters       The filters.
     *
     * @param ComposerInformation $composer      The composer information.
     *
     * @throws \RuntimeException When the configured phar file name is invalid.
     */
    public function __construct(ConfigurationValues $configuration, Filter $filters, ComposerInformation $composer)
    {
        $pharFile = $configuration->get('phar');
        assert(is_string($pharFile));
        if (class_exists('Phar') && !\Phar::isValidPharFilename($pharFile)) {
            throw new \RuntimeException('Phar file name is invalid ' . $pharFile);
        }

        if (file_exists($pharFile)) {
            unlink($pharFile);
        }

        $this->composer      = $composer;
        $this->configuration = $configuration;
        $this->filters       = $filters;
        $this->phar          = new Phar($pharFile, $filters);
        $this->phar->getPharchive()->setSignatureFlags(Phar::MD5);
        $this->phar->getPharchive()->setMetadata(
            array(
                'license' => file_get_contents(dirname($pharFile) . '/LICENSE')
            )
        );
    }

    /**
     * Finalize the project.
     *
     * @return void
     */
    public function finalize(): void
    {
        $this->phar->compressFiles(Phar::GZ);

        $filename = $this->configuration->get('phar');
        assert(is_string($filename));

        $this->phar->getPharchive()->setAlias(basename($filename));

        $writer = new PharWriter();
        $writer->save($this->phar->getPharchive(), $filename);

        unset($this->phar);

        chmod($filename, 0755);
    }

    /**
     * Retrieve phar
     *
     * @return Phar
     */
    public function getPhar(): Phar
    {
        return $this->phar;
    }

    /**
     * Retrieve composer
     *
     * @return ComposerInformation
     */
    public function getComposer(): ComposerInformation
    {
        return $this->composer;
    }

    /**
     * Retrieve configuration
     *
     * @return ConfigurationValues
     */
    public function getConfiguration(): ConfigurationValues
    {
        return $this->configuration;
    }

    /**
     * Retrieve the filters.
     *
     * @return Filter
     */
    public function getFilters(): Filter
    {
        return $this->filters;
    }
}
