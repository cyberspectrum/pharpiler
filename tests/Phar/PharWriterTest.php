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
use CyberSpectrum\PharPiler\Phar\Pharchive;
use CyberSpectrum\PharPiler\Phar\PharReader;
use CyberSpectrum\PharPiler\Phar\PharWriter;
use CyberSpectrum\PharPiler\Tests\TestCase;

class PharWriterTest extends TestCase
{
    /**
     * Provide a list of flags to create the test phar.
     *
     * @return array
     */
    public function writingFlagsProvider()
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
     * Test that the Writing is successful.
     *
     * @return void
     *
     * @dataProvider writingFlagsProvider
     */
    public function testWriting($compression, $signatureName, $signatureFlag)
    {
        $pharfile = $this->getTempFile('temp.phar');

        $phar = new Pharchive();
        $phar->setStub(
            $stubData=<<<EOF
#!/usr/bin/php
<?php

/*STUB!*/
__HALT_COMPILER();

EOF
            )
            ->setSignatureFlags($signatureFlag);

        $file = new FileEntry();
        $file
            ->setFilename('/bin/script')
            ->setContent($fileData=<<<EOF
#!/usr/bin/php
<?php
echo 'hello world';
EOF
            );

        if ($compression) {
            $file->setCompression($compression);
        }

        $phar->addFile($file);

        $writer = new PharWriter();
        $writer->save($phar, $pharfile);
        unset($writer);

        $phar = new \Phar($pharfile);

        // As we did not set an alias, php defaults to the file name.
        $this->assertEquals($pharfile, $phar->getAlias());

        $signature = $phar->getSignature();
        $this->assertEquals($signatureName, strtolower(str_replace('-', '', $signature['hash_type'])));

        /** @var \PharFileInfo[] $files */
        $files = array_values(iterator_to_array($phar->getChildren()));
        $this->assertEquals('phar://' . $pharfile . '/bin/script', $files[0]->getPathname());
        $this->assertEquals($fileData, $files[0]->getContent());
    }

    /**
     * Test that the writing of a loaded phar results in an equal file.
     *
     * @return void
     *
     * @dataProvider writingFlagsProvider
     */
    public function testWriteLoaded($compression, $signatureFlag)
    {
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
        if (0 !== $signatureFlag) {
            $phar->setSignatureAlgorithm($signatureFlag);
        }

        if ($compression !== \Phar::NONE) {
            $phar->compress($compression);
        }
        unset($phar);

        $reader = new PharReader();
        $phar = $reader->load($pharfile);

        $pharfile2 = $this->getTempFile('temp.phar');

        $writer = new PharWriter();
        $writer->save($phar, $pharfile2);
        unset($writer);

        $this->assertFileEquals($pharfile, $pharfile2);
    }
}
