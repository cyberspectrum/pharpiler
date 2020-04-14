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

use CyberSpectrum\PharPiler\Project;
use CyberSpectrum\PharPiler\Util\MiscUtils;

/**
 * This class is responsible for building a stub.
 */
class SetStubTask extends AbstractTask
{
    /**
     * The name of the stub file.
     *
     * @var string
     */
    private $name;

    /**
     * Create a new instance.
     *
     * @param array $config
     *
     * @throws \RuntimeException When the stub file does not exist.
     */
    public function __construct($config)
    {
        parent::__construct();
        if (!(isset($config['stub_file']) && is_file($config['stub_file']))) {
            throw new \RuntimeException('Stub file does not exist.');
        }

        $this->name = $config['stub_file'];
    }

    /**
     * {@inheritDoc}
     */
    public function execute(Project $project): void
    {
        $this->debug(
            sprintf(
                'Using stub file <comment>%s</comment> (%s)',
                $this->name,
                MiscUtils::formatFileSize(filesize($this->name))
            )
        );
        $project->getPhar()->setStubFromFileFiltered($this->name);
    }
}
