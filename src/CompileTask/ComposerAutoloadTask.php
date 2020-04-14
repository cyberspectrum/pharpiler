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

use CyberSpectrum\PharPiler\AutoloadOptimizer\AutoloadDumper;
use CyberSpectrum\PharPiler\AutoloadOptimizer\AutoloadInformationOptimizer;
use CyberSpectrum\PharPiler\Project;
use CyberSpectrum\PharPiler\Util\MiscUtils;
use Symfony\Component\Finder\Finder;

/**
 * This class is responsible for adding the composer autoload information.
 */
class ComposerAutoloadTask extends AbstractTask
{
    /**
     * The Flag if the autoload information should be optimized.
     *
     * @var bool
     */
    private $optimize;

    /**
     * Create a new instance.
     *
     * @param array $config
     */
    public function __construct($config)
    {
        parent::__construct();
        $this->optimize = isset($config['optimize']) && $config['optimize'];
    }

    /**
     * {@inheritDoc}
     */
    public function execute(Project $project): void
    {
        if (!$this->optimize) {
            $this->addUnoptimized($project);
        }

        $this->addOptimized($project);
    }

    /**
     * Add the autoloader without optimizations.
     *
     * @param Project $project The project.
     *
     * @return void
     */
    private function addUnoptimized(Project $project): void
    {
        $this->warning('Autoloader not optimized...');
        $root   = $project->getComposer()->getPackageDirectory($project->getComposer()->getRootPackageName());
        $phar   = $project->getPhar();
        $finder = new Finder();
        foreach ($finder->files()->in($root . '/vendor/composer')->name('*.php')->depth('0') as $filename) {
            /** @var \SplFileInfo $filename */
            $path = '/vendor/composer/' . $filename->getFilename();
            $this->debug(
                sprintf(
                    'Adding file <comment>%s</comment> (%s)',
                    $path,
                    MiscUtils::formatFileSize(filesize($root . $path))
                )
            );
            $phar->addFileFiltered($root . $path, $path);
        }

        $phar->addFileFiltered($root . '/vendor/autoload.php', '/vendor/autoload.php');
    }

    /**
     * Optimize the autoloader and add it then.
     *
     * @param Project $project The project.
     *
     * @return void
     */
    private function addOptimized(Project $project): void
    {
        $this->info('Optimizing autoloader...');

        $root = $project->getComposer()->getPackageDirectory($project->getComposer()->getRootPackageName());

        $this->debug(
            sprintf(
                'Adding file <comment>/vendor/composer/ClassLoader.php</comment> (%s)',
                MiscUtils::formatFileSize(filesize($root . '/vendor/composer/ClassLoader.php'))
            )
        );

        $phar = $project->getPhar();
        $phar->addFileFiltered($root . '/vendor/composer/ClassLoader.php', '/vendor/composer/ClassLoader.php');

        $optimizer = AutoloadInformationOptimizer::create($phar->getFileList(), $root);
        $dumper    = new AutoloadDumper($optimizer);

        $phar->addFromString('/vendor/composer/autoload_real.php', $dumper->getAutoloadReal());
        $phar->addFromString('/vendor/autoload.php', $dumper->getAutoload());
    }
}
