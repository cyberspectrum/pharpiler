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

use CyberSpectrum\PharPiler\Phar\Pharchive;
use PHPUnit\Framework\TestCase;

class PharTest extends TestCase
{
    public function versionNumberProvider()
    {
        return [
            ['1.1.0', 4352],
            ['1.1.0', 0x1100],
            ['1.0.1', 0x1010],
            ['1.0.0', 0x100F],
            ['4.2.1', 0x421F],
            ['1.0.0', '1.0.0'],
            ['1.1.0', '1.1.0'],
            ['1.1.1', '1.1.1'],
            ['1.2.3', '1.2.3'],
            ['1.4.2', 0x1420],
            ['1.0.0', 0x1000],
            ['1.2.3', [1, 2, 3]],
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
     * @dataProvider versionNumberProvider
     */
    public function testVersionParsing($expects, $value)
    {
        $phar = new Pharchive();

        $phar->setApiVersion($value);
        $this->assertEquals($expects, $phar->getApiVersionString());
    }

    public function invalidVersionNumberProvider()
    {
        return [
            ['RuntimeException', 'xxx.yyy.zzz'],
            ['RuntimeException', '0.0.0.0'],
            ['RuntimeException', .5],
            ['RuntimeException', '-1.0.0'],
            ['RuntimeException', '0.-1.0'],
            ['RuntimeException', '0.0.-1'],
            ['OutOfBoundsException', '16.0.0'],
            ['OutOfBoundsException', '0.16.0'],
            ['OutOfBoundsException', '0.0.16'],
        ];
    }

    /**
     * Test that the version parsing works.
     *
     * @param string $expectedException The expected exception.
     *
     * @param mixed  $value             The value to set.
     *
     * @return void
     *
     * @dataProvider invalidVersionNumberProvider
     */
    public function testVersionParsingWithInvalidRaisesException($expectedException, $value)
    {
        if (method_exists($this, 'expectException')) {
            $this->expectException($expectedException);
        } else {
            $this->setExpectedException($expectedException);
        }

        $phar = new Pharchive();

        $phar->setApiVersion($value);
    }
}
