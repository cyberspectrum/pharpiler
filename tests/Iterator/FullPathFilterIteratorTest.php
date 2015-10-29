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

namespace CyberSpectrum\PharPiler\Tests\Iterator;


use CyberSpectrum\PharPiler\Iterator\FullPathFilterIterator;

class FullPathFilterIteratorTest extends \PHPUnit_Framework_TestCase
{
    /**
     * Test the matches.
     *
     * @return void
     */
    public function testMatches()
    {
        $candidates = [
            '/vendor/xxx/yyy/Foo/Bar/FooTrait.php',
            '/vendor/xxx/yyy/Foo/Bar/InvalidArgumentException.php',
            '/vendor/xxx/yyy/Foo/Bar/Test/FooInterfaceTest.php',
            '/vendor/xxx/yyy/Foo/Bar/FooTrait.php',
            '/vendor/xxx/yyy/Foo/Bar/AbstractFoo.php',
            '/vendor/xxx/yyy/Foo/Bar/Foo.php',
            '/vendor/xxx/yyy/Foo/Bar/FooAwareInterface.php',
            '/vendor/xxx/yyy/Foo/Bar/FooInterface.php',
            '/vendor/xxx/zzz/Tests/SomeTest.php',
            '/vendor/xxx/zzz/Tests/fixtures/SomeFixture.php'
        ];
        $matches    = [
            '*.php'
        ];
        $noMatches  = [
            '(.*[Tt]ests?\/.*/*.php)'
        ];

        $iterator = new FullPathFilterIterator(new \ArrayIterator($candidates), $matches, $noMatches);

        $this->assertEquals(
            [
                '/vendor/xxx/yyy/Foo/Bar/FooTrait.php',
                '/vendor/xxx/yyy/Foo/Bar/InvalidArgumentException.php',
                '/vendor/xxx/yyy/Foo/Bar/FooTrait.php',
                '/vendor/xxx/yyy/Foo/Bar/AbstractFoo.php',
                '/vendor/xxx/yyy/Foo/Bar/Foo.php',
                '/vendor/xxx/yyy/Foo/Bar/FooAwareInterface.php',
                '/vendor/xxx/yyy/Foo/Bar/FooInterface.php',
            ],
            array_values(iterator_to_array($iterator))
        );
    }

    /**
     * Test the matches by glob.
     *
     * @return void
     */
    public function testMatchesGlob()
    {
        $candidates = [
            '/vendor/xxx/yyy/Foo/Bar/FooTrait.php',
            '/vendor/xxx/yyy/Foo/Bar/InvalidArgumentException.php',
            '/vendor/xxx/yyy/Foo/Bar/Test/FooInterfaceTest.php',
            '/vendor/xxx/yyy/Foo/Bar/FooTrait.php',
            '/vendor/xxx/yyy/Foo/Bar/AbstractFoo.php',
            '/vendor/xxx/yyy/Foo/Bar/Foo.php',
            '/vendor/xxx/yyy/Foo/Bar/FooAwareInterface.php',
            '/vendor/xxx/yyy/Foo/Bar/FooInterface.php',
            '/vendor/xxx/yyy/Foo/Bar/Tester/FooTester.php',
            '/vendor/xxx/zzz/Tests/SomeTest.php',
        ];
        $matches    = [
            '*.php'
        ];
        $noMatches  = [
            '*Test*/*Test.php'
        ];

        $iterator = new FullPathFilterIterator(new \ArrayIterator($candidates), $matches, $noMatches);

        $this->assertEquals(
            [
                '/vendor/xxx/yyy/Foo/Bar/FooTrait.php',
                '/vendor/xxx/yyy/Foo/Bar/InvalidArgumentException.php',
                '/vendor/xxx/yyy/Foo/Bar/FooTrait.php',
                '/vendor/xxx/yyy/Foo/Bar/AbstractFoo.php',
                '/vendor/xxx/yyy/Foo/Bar/Foo.php',
                '/vendor/xxx/yyy/Foo/Bar/FooAwareInterface.php',
                '/vendor/xxx/yyy/Foo/Bar/FooInterface.php',
                '/vendor/xxx/yyy/Foo/Bar/Tester/FooTester.php',
            ],
            array_values(iterator_to_array($iterator))
        );
    }
}
