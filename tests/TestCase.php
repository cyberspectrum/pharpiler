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

namespace CyberSpectrum\PharPiler\Tests;

use Symfony\Component\Filesystem\Filesystem;

/**
 * This class provides easy management of fixtures.
 */
class TestCase extends \PHPUnit_Framework_TestCase
{
    /**
     * Temporary working dir.
     *
     * @var string
     */
    protected $workDir;

    /**
     * {@inheritdoc}
     */
    public function tearDown()
    {
        if (isset($this->workDir)) {
            $filesystem = new Filesystem();
            $filesystem->remove($this->workDir);
        }
    }

    /**
     * Retrieve the path to the fixtures directory.
     *
     * @return string
     */
    protected function getFixturesDirectory()
    {
        return __DIR__ . DIRECTORY_SEPARATOR . 'fixtures';
    }

    /**
     * Create and return the path to a temp dir.
     *
     * @return string
     */
    protected function getTempDir()
    {
        if (!isset($this->workDir)) {
            $temp = sys_get_temp_dir() . DIRECTORY_SEPARATOR . uniqid('pharpiler-test');
            mkdir($temp, 0777, true);
            $this->workDir = $temp;
        }

        return $this->workDir;
    }

    /**
     * Retrieve the path of a temp file within the temp dir of the test.
     *
     * @param string $name             Optional name of the file.
     *
     * @param bool   $forceDirectories Optional flag if the parenting dirs should be created.
     *
     * @return string
     */
    public function getTempFile($name = '', $forceDirectories = true)
    {
        if ('' === $name) {
            $name = uniqid();
        }

        $path = $this->getTempDir() . DIRECTORY_SEPARATOR . $name;

        if ($forceDirectories && !is_dir($dir = dirname($path))) {
            mkdir($dir, 0777, true);
        }

        return $path;
    }

    /**
     * Provide a fixture in the temp directory and return the complete path to the new file.
     *
     * @param string $path    The file name of the fixture.
     *
     * @param string $newPath The new path for the fixture.
     *
     * @return string
     */
    public function provideFixture($path, $newPath = '')
    {
        if ('' === $newPath) {
            $newPath = $path;
        }

        $tempDir  = $this->getTempDir();
        $fullPath = $tempDir . DIRECTORY_SEPARATOR . $newPath;
        if (!is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0777, true);
        }
        copy($this->getFixturesDirectory() . DIRECTORY_SEPARATOR . $path, $fullPath);

        return $fullPath;
    }

    /**
     * Provide a fixture in the temp directory with the passed data and return the complete path to the new file.
     *
     * @param string $path    The file name of the fixture.
     *
     * @param string $content The fixture content.
     *
     * @return string
     */
    public function createFixture($path, $content)
    {
        $tempDir  = $this->getTempDir();
        $fullPath = $tempDir . DIRECTORY_SEPARATOR . $path;
        if (!is_dir(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0777, true);
        }

        file_put_contents($fullPath, $content);

        return $fullPath;
    }

    /**
     * Read the content of a fixture to memory and return it.
     *
     * @param string $path The fixture to read.
     *
     * @return string
     */
    public function readFixture($path)
    {
        return file_get_contents($this->getFixturesDirectory() . DIRECTORY_SEPARATOR . $path);
    }
}
