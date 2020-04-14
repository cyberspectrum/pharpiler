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

use CyberSpectrum\PharPiler\Phar\PharReader;
use CyberSpectrum\PharPiler\Tests\TestCase;

class PharReaderTest extends TestCase
{
    /**
     * Provide a list of flags to create the test phar.
     *
     * @return array
     */
    public function readingFlagsProvider()
    {
        $compressions = [\Phar::NONE, \Phar::GZ, \Phar::BZ2];
        $signatures   = [
            'md5'    => \Phar::MD5,
            'sha1'   => \Phar::SHA1,
            'sha256' => \Phar::SHA256,
            'sha512' => \Phar::SHA512
            /* 'openssl' => \Phar::OPENSSL*/
        ];
        $result       = [];
        foreach ($compressions as $compression) {
            foreach ($signatures as $name => $flag) {
                $result[] = [$compression, $name, $flag];
            }
        }

        return $result;
    }

    /**
     * Test that the reading is successful.
     *
     * @return void
     *
     * @dataProvider readingFlagsProvider
     */
    public function testReading($compression, $signatureName, $signatureFlag)
    {
        if (ini_get('phar.readonly')) {
            $this->markTestSkipped('Test disabled by the php.ini setting phar.readonly');
        }
        $pharfile = $this->getTempFile('temp.phar');

        $phar = new \Phar($pharfile, 0, 'temp.phar');
        $phar->startBuffering();

        $phar->addFromString('/bin/script', $fileData=<<<EOF
#!/usr/bin/php
<?php
echo 'hello world';
EOF
            );

        $phar->setDefaultStub('/bin/script', '/web/index');

        $phar->stopBuffering();
        $phar->setSignatureAlgorithm($signatureFlag);
        if ($compression !== \Phar::NONE) {
            $phar->compressFiles($compression);
        }
        unset($phar);

        $reader = new PharReader();
        $phar = $reader->load($pharfile);

        $this->assertEquals('temp.phar', $phar->getAlias());
        $this->assertTrue($phar->isSigned());
        $this->assertEquals($signatureName, $phar->getSignatureAlgorithm());

        $files = $phar->getFiles();
        $this->assertEquals('bin/script', $files[0]->getFilename());
        $this->assertEquals($fileData, $files[0]->getContent());
    }

    /**
     * Test that the reading is successful.
     *
     * @return void
     *
     * @dataProvider readingFlagsProvider
     */
    public function testReadingEntirePharCompressed($compression, $signatureName, $signatureFlag)
    {
        if (ini_get('phar.readonly')) {
            $this->markTestSkipped('Test disabled by the php.ini setting phar.readonly');
        }
        $pharfile = $this->getTempFile('temp.phar');

        $phar = new \Phar($pharfile, 0, 'temp.phar');
        $phar->startBuffering();

        $phar->addFromString('/bin/script', $fileData=<<<EOF
#!/usr/bin/php
<?php
echo 'hello world';
EOF
        );

        $phar->setStub(<<<EOF
#!/usr/bin/php
<?php

/*STUB!*/
__HALT_COMPILER();
?>
EOF
        );

        $phar->stopBuffering();
        if (0 !== $signatureFlag) {
            $phar->setSignatureAlgorithm($signatureFlag);
        }

        if ($compression !== \Phar::NONE) {
            $phar->compress($compression);
        }
        unset($phar);

        $reader = new PharReader();
        $phar = $reader->load($pharfile);

        $this->assertEquals('temp.phar', $phar->getAlias());
        $this->assertTrue($phar->isSigned());
        $this->assertEquals($signatureName, $phar->getSignatureAlgorithm());

        $files = $phar->getFiles();
        $this->assertEquals('bin/script', $files[0]->getFilename());
        $this->assertEquals($fileData, $files[0]->getContent());
    }
}
