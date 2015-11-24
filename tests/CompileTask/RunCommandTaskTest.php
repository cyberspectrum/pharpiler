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

namespace CyberSpectrum\PharPiler\Tests\CompileTask;

use CyberSpectrum\PharPiler\CompileTask\RunCommandTask;
use Symfony\Component\Console\Logger\ConsoleLogger;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * This class tests the RunCommandTask class
 */
class RunCommandTaskTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test that the execution is successful.
     *
     * @return void
     */
    public function testExecution()
    {
        $project = $this
            ->getMockBuilder('CyberSpectrum\PharPiler\Project')
            ->disableOriginalConstructor()
            ->getMock();

        $task = new RunCommandTask(
            [
                'command'     => 'echo "hello world"',
                'working_dir' => getcwd(),
                'timeout'     => null
            ]
        );

        $task->setLogger(new ConsoleLogger($output = new BufferedOutput()));
        $output->setVerbosity(BufferedOutput::VERBOSITY_DEBUG);

        $task->execute($project);

        $this->assertEquals(
            '[info] echo "hello world": hello world' . "\n" .
            '[info] echo "hello world": ' . "\n",
            $output->fetch()
        );
    }
}
