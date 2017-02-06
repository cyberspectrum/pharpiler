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

namespace CyberSpectrum\PharPiler\Composer;

use Symfony\Component\Process\Process;

/**
 * This class holds basic information about a composer package.
 */
class PackageInformation
{
    /**
     * The package name.
     *
     * @var string
     */
    private $name;

    /**
     * The composer.json data.
     *
     * @var array
     */
    private $data;

    /**
     * The installation dir.
     *
     * @var string
     */
    private $installDir;

    /**
     * Flag if this is the root package.
     *
     * @var bool
     */
    private $isRoot;

    /**
     * Create a new instance.
     *
     * @param string $name        The name.
     *
     * @param string $data        The package data.
     *
     * @param string $installRoot The path to the composer project root dir.
     *
     * @param bool   $isRoot      Boolean flag if the package is the root package.
     */
    public function __construct($name, $data, $installRoot, $isRoot = false)
    {
        $this->name   = $name;
        $this->data   = $data;
        $this->isRoot = $isRoot;

        if ($this->isRoot) {
            $this->installDir = $installRoot;
        } elseif ($this->isReplaced() || $this->isProvided()) {
            $this->installDir = $installRoot . '/vendor/' . $data['name'];
        } else {
            if ('platform' === $this->getType()) {
                $this->installDir = null;
                return;
            }
            $this->installDir = $installRoot . '/vendor/' . $name;
        }
    }

    /**
     * Retrieve the name.
     *
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * This retrieves the version.
     *
     * @return string
     *
     * @throws \LogicException When the package version can not be determined.
     */
    public function getVersion()
    {
        if (!isset($this->data['version_normalized'])) {
            return $this->loadVersionInformationFromGit();
        }

        $normalized = $this->data['version_normalized'];
        if ('dev-' === substr($normalized, 0, 4)) {
            if (isset($this->data['extra']['branch-alias'][$normalized])) {
                $normalized = $this->data['extra']['branch-alias'][$normalized];
            }

            if (isset($this->data['source']['reference'])) {
                $normalized .= $this->data['source']['reference'];
            } elseif (isset($this->data['dist']['reference'])) {
                $normalized .= $this->data['dist']['reference'];
            }
        }

        if ($this->isProvided()) {
            return $this->makeVersion($this->data['provide'][$this->name], $normalized);
        }
        if ($this->isReplaced()) {
            return $this->makeVersion($this->data['replace'][$this->name], $normalized);
        }

        if ($this->name === $this->data['name']) {
            return $normalized;
        }

        throw new \LogicException('Unable to determine package version of ' . $this->name);
    }

    /**
     * This retrieves the release date.
     *
     * @return string
     */
    public function getReleaseDate()
    {
        if (!isset($this->data['time'])) {
            return $this->loadReleaseDateInformationFromGit();
        }

        return $this->data['time'];
    }

    /**
     * Retrieve the package type.
     *
     * @return string
     */
    public function getType()
    {
        if (($this->name === 'php') || (substr($this->name, 0, 4) === 'ext-')) {
            return 'platform';
        }

        if (isset($this->data['type'])) {
            return $this->data['type'];
        }

        return 'library';
    }

    /**
     * Retrieve the dependencies of a package.
     *
     * @param string[] $ignorePackages The names of packages that shall be ignored.
     *
     * @return string[]
     */
    public function getDependencies($ignorePackages = [])
    {
        return array_diff(array_keys(isset($this->data['require']) ? $this->data['require'] : []), $ignorePackages);
    }

    /**
     * Retrieve the package root directory of a package.
     *
     * @return string|null
     */
    public function getPackageDirectory()
    {
        return $this->installDir;
    }

    /**
     * Check if this package is a replacement.
     *
     * @return string
     */
    public function isReplaced()
    {
        return isset($this->data['replace'][$this->name]);
    }

    /**
     * Check if this package is a replacement.
     *
     * @return string
     */
    public function isProvided()
    {
        return isset($this->data['provide'][$this->name]);
    }

    /**
     * Try to look up the version information for a given package.
     *
     * @return array
     *
     * @throws \RuntimeException When the git repository is invalid or git executable can not be run.
     */
    private function loadVersionInformationFromGit()
    {
        $process = new Process('git describe --tags --exact-match HEAD', $this->getPackageDirectory());
        if ($process->run() == 0) {
            return trim($process->getOutput());
        }

        $process = new Process('git log --pretty="%h" -n1 HEAD', $this->getPackageDirectory());
        if ($process->run() != 0) {
            throw new \RuntimeException(
                'Can\'t run git log in ' . $this->getPackageDirectory() . '. ' .
                'Ensure to run compile from git repository clone and that git binary is available.'
            );
        }

        $version = trim($process->getOutput());

        $process = new Process('git rev-parse --abbrev-ref HEAD', $this->getPackageDirectory());
        if ($process->run() == 0) {
            $branch = 'dev-' . trim($process->getOutput());
            if (isset($this->data['extra']['branch-alias'][$branch])) {
                $version = $this->data['extra']['branch-alias'][$branch] . '#' . $version;
            }
        }

        return $version;
    }

    /**
     * Try to look up the version information for a given package.
     *
     * @return string
     *
     * @throws \RuntimeException When the git repository is invalid or git executable can not be run.
     */
    private function loadReleaseDateInformationFromGit()
    {
        $process = new Process('git log -n1 --pretty=%ci HEAD', $this->getPackageDirectory());
        if ($process->run() != 0) {
            throw new \RuntimeException(
                'Can\'t run git log in ' . $this->getPackageDirectory() . '. ' .
                'Ensure to run compile from git repository clone and that git binary is available.'
            );
        }

        $date = new \DateTime(trim($process->getOutput()));
        $date->setTimezone(new \DateTimeZone('UTC'));

        return $date->format('Y-m-d H:i:s');
    }

    /**
     * Ensure this is a proper version.
     *
     * @param string $version  The version that might contain "self.version".
     *
     * @param string $fallback The fallback version to return when the real version is "self.version".
     *
     * @return string
     */
    private function makeVersion($version, $fallback)
    {
        if ($version === 'self.version') {
            return $fallback;
        }

        return $version;
    }
}
