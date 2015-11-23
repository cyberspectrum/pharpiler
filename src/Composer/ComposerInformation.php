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
 * This class provides an easy interface to the composer.json and installed.json.
 */
class ComposerInformation
{
    /**
     * The composer root dir.
     *
     * @var string
     */
    private $composerRoot;

    /**
     * The contents of the composer.json.
     *
     * @var array
     */
    private $composerJson;

    /**
     * The contents of the installed.json.
     *
     * @var array
     */
    private $installedJson;

    /**
     * List of packages names of all installed packages.
     *
     * @var string[]
     */
    private $packageNames;

    /**
     * The root directories of the packages.
     *
     * @var string[]
     */
    private $packageRoots = [];

    /**
     * The versions of the packages.
     *
     * @var string[]
     */
    private $packageVersions = [];

    /**
     * The release dates of the packages.
     *
     * @var string[]
     */
    private $packageReleaseDates = [];

    /**
     * The types of the packages.
     *
     * @var string[]
     */
    private $packageTypes = [];

    /**
     * Lookup table of the package information.
     *
     * @var PackageInformation[]
     */
    private $packageArray = [];

    /**
     * Create a new instance.
     *
     * @param string $composerRoot Path to the composer project root.
     *
     * @throws \InvalidArgumentException When the passed directory does not exist or is not a directory.
     */
    public function __construct($composerRoot)
    {
        $composerRoot = realpath($composerRoot) ?: getcwd();

        if (!is_dir($composerRoot)) {
            throw new \InvalidArgumentException('Directory does not exist or is not a directory: ' . $composerRoot);
        }

        $this->composerRoot  = $composerRoot;
        $this->composerJson  = $this->readJson($this->composerRoot . '/composer.json');
        $this->installedJson = $this->readJson($this->composerRoot . '/vendor/composer/installed.json');

        $this->addPackage($this->composerJson, true);

        foreach ($this->installedJson as $package) {
            $this->addPackage($package);
        }
    }

    /**
     * Retrieve the names of all composer packages.
     *
     * @return string[]
     */
    public function getPackageNames()
    {
        if (!isset($this->packageNames)) {
            foreach ($this->packageArray as $package) {
                $this->packageNames[] = $package->getName();
            }
        }

        return $this->packageNames;
    }

    /**
     * Retrieve the name of the root package.
     *
     * @return string
     */
    public function getRootPackageName()
    {
        return $this->composerJson['name'];
    }

    /**
     * Retrieve the directory of a package.
     *
     * @param string $packageName The package to retrieve.
     *
     * @return string|null
     */
    public function getPackageDirectory($packageName)
    {
        if (!isset($this->packageRoots[$packageName])) {
            return $this->packageRoots[$packageName] = $this->getPackageArray($packageName)->getPackageDirectory();
        }

        return $this->packageRoots[$packageName];
    }

    /**
     * Retrieve the package version.
     *
     * @param string $packageName The package to retrieve.
     *
     * @return string
     */
    public function getPackageVersion($packageName)
    {
        if (!isset($this->packageVersions[$packageName])) {
            return $this->packageVersions[$packageName] = $this->getPackageArray($packageName)->getVersion();
        }

        return $this->packageVersions[$packageName];
    }

    /**
     * Retrieve the release date of a package.
     *
     * @param string $packageName The package to retrieve.
     *
     * @return string
     */
    public function getPackageReleaseDate($packageName)
    {
        if (!isset($this->packageReleaseDates[$packageName])) {
            return $this->packageReleaseDates[$packageName] = $this->getPackageArray($packageName)->getReleaseDate();
        }

        return $this->packageReleaseDates[$packageName];
    }

    /**
     * Retrieve the dependencies of a package.
     *
     * @param string|null $package        The package name (or null for the root package).
     *
     * @param string[]    $ignorePackages The names of packages that shall be ignored.
     *
     * @param bool        $recursive      Flag if the dependencies shall be retrieved recursively (default: true).
     *
     * @return string[]
     */
    public function getDependencies($package = null, $ignorePackages = [], $recursive = true)
    {
        // Root package.
        if ((null === $package)) {
            $package = $this->composerJson['name'];
        }
        $dependencies = $this->getPackageArray($package)->getDependencies($ignorePackages);

        if (!$recursive) {
            return $dependencies;
        }

        foreach ($dependencies as $dependency) {
            $dependencies = array_merge(
                $dependencies,
                $this->getDependencies($dependency, $ignorePackages, true)
            );
        }

        return array_unique($dependencies);
    }

    /**
     * Retrieve the dev dependencies of the root package.
     *
     * @return string[]
     */
    public function getDevDependencies()
    {
        return isset($this->composerJson['require-dev']) ? array_keys($this->composerJson['require-dev']) : [];
    }

    /**
     * Retrieve the package type.
     *
     * @param string $packageName The package name.
     *
     * @return string
     */
    public function getPackageType($packageName)
    {
        if (isset($this->packageTypes[$packageName])) {
            return $this->packageTypes[$packageName];
        }

        return $this->packageTypes[$packageName] = $this->getPackageArray($packageName)->getType();
    }

    /**
     * Retrieve the package for the given package name.
     *
     * @param string $packageName The package name.
     *
     * @return PackageInformation
     *
     * @throws \RuntimeException When a package is not installed.
     */
    private function getPackageArray($packageName)
    {
        if (!isset($this->packageArray[$packageName])) {
            throw new \RuntimeException('Package ' . $packageName . ' does not seem to be installed?');
        }

        return $this->packageArray[$packageName];
    }

    /**
     * Read the passed json file into an array.
     *
     * @param string $filename The file to load.
     *
     * @return array
     *
     * @throws \InvalidArgumentException When the file can not be found.
     */
    private function readJson($filename)
    {
        if (!is_file($filename)) {
            throw new \InvalidArgumentException('File not found: ' . $filename);
        }

        return json_decode(file_get_contents($filename), true);
    }

    /**
     * Create a package information from the passed array and add it to the list.
     *
     * @param array $data   The package information.
     *
     * @param bool  $isRoot Flag if this is the root package.
     *
     * @return void
     */
    private function addPackage($data, $isRoot = false)
    {
        $this->packageArray[$data['name']] = new PackageInformation(
            $data['name'],
            $data,
            $this->composerRoot,
            $isRoot
        );

        if (isset($data['provide'])) {
            foreach ($data['provide'] as $packageName => $version) {
                $this->packageArray[$packageName] = new PackageInformation(
                    $packageName,
                    $data,
                    $this->composerRoot,
                    $isRoot
                );
            }
        }
        if (isset($data['replace'])) {
            foreach ($data['replace'] as $packageName => $version) {
                $this->packageArray[$packageName] = new PackageInformation(
                    $packageName,
                    $data,
                    $this->composerRoot,
                    $isRoot
                );
            }
        }

        $this->addRequiredPackagesFrom($data, 'require');
        $this->addRequiredPackagesFrom($data, 'require-dev');
    }

    /**
     * Add the required packages from the specified section.
     *
     * @param array  $data    The json data array.
     *
     * @param string $section The section to read packages from.
     *
     * @return void
     */
    private function addRequiredPackagesFrom($data, $section)
    {
        if (isset($data[$section])) {
            foreach ($data[$section] as $packageName => $version) {
                if (($packageName === 'php') || (substr($packageName, 0, 4) === 'ext-')) {
                    $this->packageArray[$packageName] = new PackageInformation(
                        $packageName,
                        ['version' => $version],
                        $this->composerRoot
                    );
                }
            }
        }
    }
}
