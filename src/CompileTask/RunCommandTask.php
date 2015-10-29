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
use Symfony\Component\Process\Process;

/**
 * This class is the abstract base for all compile tasks.
 */
class RunCommandTask extends AbstractTask
{
    /**
     * The command to execute.
     *
     * @var string
     */
    private $command;

    /**
     * The working directory.
     *
     * @var string
     */
    private $workingDir;

    /**
     * The environment variables or null to inherit.
     *
     * @var string[]|null
     */
    private $env;

    /**
     * Create a new instance.
     *
     * @param array $config
     */
    public function __construct($config)
    {
        $this->command = $config['command'];

        if (isset($config['working_dir'])) {
            $this->workingDir = $config['working_dir'];
        }

        if (isset($config['env'])) {
            $this->env = $config['env'];
        }
    }

    /**
     * {@inheritDoc}
     */
    public function execute(Project $project)
    {
        $process = new Process($this->command, $this->workingDir, $this->env);
        $process->mustRun();

        if ($output = $process->getOutput()) {
            foreach ($this->formatOutput($output) as $line) {
                $this->info($line);
            }
        }

        if ($output = $process->getErrorOutput()) {
            foreach ($this->formatOutput($output) as $line) {
                $this->notice($line);
            }
        }
    }

    /**
     * @param string $lines The output to format.
     *
     * @return string[]
     */
    private function formatOutput($lines)
    {
        $processed = [];
        foreach (explode(PHP_EOL, $lines) as $line) {
            $processed[] = sprintf('<comment>%s</comment>: %s', $this->command, $line);
        }

        return $processed;
    }
}
