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

namespace CyberSpectrum\PharPiler\CompileTask;

use CyberSpectrum\PharPiler\Composer\ComposerInformation;
use CyberSpectrum\PharPiler\Iterator\FullPathFilterIterator;
use CyberSpectrum\PharPiler\Project;
use CyberSpectrum\PharPiler\Util\MiscUtils;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;

/**
 * This class is the abstract base for all compile tasks.
 */
class AddPackageTask extends AbstractTask
{
    /**
     * The name of the package.
     *
     * @var string
     */
    private $name;

    /**
     * The list of dependencies to be included or bool to include all/no dependencies.
     *
     * @var bool|string[]
     */
    private $includeRequire;

    /**
     * The list of dependencies from dev section to be included or bool to include all/no dependencies.
     *
     * @var bool|string[]
     */
    private $includeRequireDev;

    /**
     * The dependencies to exclude.
     *
     * @var string[]
     */
    private $excludeDependencies;

    /**
     * File patterns to exclude.
     *
     * @var string[]
     */
    private $excludeFiles;

    /**
     * File patterns to include.
     *
     * @var string[]
     */
    private $includeFiles;

    /**
     * Rewrite the paths.
     *
     * @var string[]
     */
    private $rewritePaths;

    /**
     * Rewrite the paths.
     *
     * @var array[]
     */
    private $packageOverride;

    /**
     * Create a new instance.
     *
     * @param array $config
     */
    public function __construct($config)
    {
        $this->name                = $config['name'];
        $this->includeRequire      = $config['include_require'];
        $this->includeRequireDev   = $config['include_requiredev'];
        $this->excludeDependencies = $config['exclude_dependencies'];
        $this->excludeFiles        = $config['exclude_files'];
        $this->includeFiles        = $config['include_files'];
        $this->rewritePaths        = $config['rewrite_paths'];
        $this->packageOverride     = $config['package_override'];
    }

    /**
     * {@inheritDoc}
     */
    public function execute(Project $project)
    {
        $composer     = $project->getComposer();
        $packages     = $this->filteredPackages($composer);
        $prefixLength = strlen($composer->getPackageDirectory($composer->getRootPackageName()));
        $phar         = $project->getPhar();

        foreach ($packages as $package) {
            $this->info('Adding package <comment>' . $package . '</comment>');
            $packageRoot     = $composer->getPackageDirectory($package);
            $packageRelative = substr($packageRoot, $prefixLength);
            foreach ($this->prepareFinder($packageRoot, $package) as $file) {
                /** @var SplFileInfo $file */
                $filePath  = $file->getPathname();
                $localPath = $this->rewritePath($file->getRelativePathname(), $package, $packageRelative);
                $this->debug(
                    sprintf(
                        'Adding file <comment>%s</comment> (%s)',
                        $localPath,
                        MiscUtils::formatFileSize(filesize($filePath))
                    )
                );

                $phar->addFileFiltered($filePath, $localPath);
            }
        }
    }

    /**
     * Prepare a finder instance to process the passed root.
     *
     * @param string $root    The root to process.
     *
     * @param string $package The package name.
     *
     * @return \Iterator
     */
    private function prepareFinder($root, $package)
    {
        $excludes = $this->excludeFiles;
        if (isset($this->packageOverride[$package]) && isset($this->packageOverride[$package]['exclude_files'])) {
            $excludes = $this->packageOverride[$package]['exclude_files'];
        }

        $includes = $this->includeFiles;
        if (isset($this->packageOverride[$package]) && isset($this->packageOverride[$package]['include_files'])) {
            $includes = $this->packageOverride[$package]['include_files'];
        }

        return new FullPathFilterIterator(
            Finder::create()->ignoreDotFiles(false)->files()->in($root)->exclude('vendor')->getIterator(),
            $includes,
            $excludes
        );
    }

    /**
     * Apply path overrides to the passed path.
     *
     * @param string $path            The path to process.
     *
     * @param string $package         The package name.
     *
     * @param string $packageRelative The relative path to the package root.
     *
     * @return string
     */
    private function rewritePath($path, $package, $packageRelative)
    {
        $rewritePaths = $this->rewritePaths;
        if (isset($this->packageOverride[$package]) && isset($this->packageOverride[$package]['rewrite_paths'])) {
            $rewritePaths = $this->packageOverride[$package]['rewrite_paths'];
        }

        foreach ($rewritePaths as $rewritePath => $newTarget) {
            $len = strlen($rewritePath);
            if (substr($path, 0, $len) === $rewritePath) {
                $destination = $newTarget . substr($path, $len);
                if ($destination[0] !== '/') {
                    return $packageRelative  . DIRECTORY_SEPARATOR . $destination;
                }

                return $destination;
            }
        }

        return $packageRelative . DIRECTORY_SEPARATOR . $path;
    }

    /**
     * Retrieve the packages to obtain.
     *
     * @param ComposerInformation $composer The composer information.
     *
     * @return string[]
     *
     * @throws \LogicException When require-dev shall get included but the current package is not the root package.
     */
    private function filteredPackages(ComposerInformation $composer)
    {
        $packages = [];
        if ($this->includeRequire) {
            if (true === $this->includeRequire) {
                $packages = $composer->getDependencies($this->name, $this->excludeDependencies);
            } else {
                $packages = $this->includeRequire;
            }
        }
        $packages = array_merge($packages, [$this->name]);

        if ($this->includeRequireDev) {
            if ($this->name !== $composer->getRootPackageName()) {
                throw new \LogicException(
                    'Inclusion of require-dev from ' . $this->name . ' requested but it is not the root package.'
                );
            }

            if (true === $this->includeRequireDev) {
                $packages = array_merge($packages, $composer->getDevDependencies());
            }
        }

        $exclude  = $this->excludeDependencies;
        $excTypes = ['platform', 'metapackage'];
        $packages = array_filter($packages, function ($package) use ($exclude, $excTypes, $composer) {
            return !(in_array($package, $exclude) || in_array($composer->getPackageType($package), $excTypes));
        });

        return $packages;
    }
}
