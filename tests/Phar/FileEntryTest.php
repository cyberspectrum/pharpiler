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

namespace CyberSpectrum\PharPiler\Tests\Phar;

use CyberSpectrum\PharPiler\Phar\FileEntry;

class AddPackageTaskTest extends \PHPUnit_Framework_TestCase
{
    public function permissionNumberProvider()
    {
        return [
            ['rw-rw-rw-', octdec('0666')],
            ['rwxrwxrwx', octdec('0777')],
            ['---------', octdec('0000')],
            ['r--r--r--', octdec('0444')],
        ];
    }

    /**
     * Test that the version parsing works.
     *
     * @param string $expects The expected value.
     *
     * @param mixed $value    The value to set.
     *
     * @return void
     *
     * @dataProvider permissionNumberProvider
     */
    public function testPermissionParsing($expects, $value)
    {
        $phar = new FileEntry();

        $phar->setPermissions($value);
        $this->assertEquals($expects, $phar->getPermissionString());
    }


}
