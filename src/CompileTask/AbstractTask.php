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
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerTrait;
use Psr\Log\NullLogger;

/**
 * This class is the abstract base for all compile tasks.
 */
abstract class AbstractTask
{
    use LoggerAwareTrait;
    use LoggerTrait;

    /**
     * Create a new instance.
     */
    public function __construct()
    {
        $this->logger = new NullLogger();
    }

    /**
     * {@inheritDoc}
     */
    public function log($level, $message, array $context = array())
    {
        $this->logger->log($level, $message, $context);
    }

    /**
     * Execute the task.
     *
     * @param Project $project The project being compiled.
     *
     * @return void
     */
    abstract public function execute(Project $project): void;
}
