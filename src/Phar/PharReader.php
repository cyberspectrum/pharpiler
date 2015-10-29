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

namespace CyberSpectrum\PharPiler\Phar;

/**
 * This class is the real abstraction over the phar.
 */
class PharReader
{
    const MAX_API_VERSION = 0x1100;

    /**
     * The file to read from.
     *
     * @var StreamReader
     */
    private $file;

    /**
     * @var Pharchive
     */
    private $phar;

    /**
     * The offset in the file where the binaries start.
     *
     * @var int
     */
    private $binOffset;

    /**
     * @var int
     */
    private $numFiles;

    /**
     * Save the phar to disk.
     *
     * @param string $filename The file name to write to.
     *
     * @return Pharchive
     */
    public function load($filename)
    {
        $this->file = new StreamReader($filename);
        $this->phar = new Pharchive();
        $this->readHead();
        $this->checkSignature();

        // files
        $fileStart = $this->binOffset;
        while ($this->file->tell() < $this->binOffset) {
            $fileStart += $this->readFile($fileStart)->getSizeCompressed();
        }

        $phar = $this->phar;
        unset($this->phar);
        unset($this->file);
        unset($this->numFiles);

        return $phar;
    }

    /**
     * Read the header from the file.
     *
     * @return void
     *
     * @throws \RuntimeException When the stub is invalid.
     */
    private function readHead()
    {
        $buffer = '';
        do {
            if ('' === ($chunk = $this->file->read(1024, true))) {
                throw new \RuntimeException('Could not detect the stub\'s end in the phar');
            }
            $buffer .= $chunk;
            unset($chunk);
        } while (!preg_match('{__HALT_COMPILER\(\);(?: \?>)?\r?\n}', $buffer, $match, PREG_OFFSET_CAPTURE));

        // detect manifest offset / end of stub
        $stubEnd = $match[0][1] + strlen($match[0][0]);
        $this->file->seek($stubEnd);
        $this->phar->setStub(substr($buffer, 0, $stubEnd));
        unset($buffer);
        unset($match);

        // Check header.
        $manifestLength = $this->file->readUint32le();

        if ($manifestLength > 1048576 * 100) {
            // Prevent serious memory issues by limiting manifest to at most 100 MB in length.
            // See also: https://github.com/php/php-src/blob/12ff95/ext/phar/phar.c#L719
            throw new \RuntimeException('manifest cannot be larger than 100 MB');
        }

        $this->file->savePosition();
        if ($manifestLength < 10 || $manifestLength != strlen($this->file->read($manifestLength))) {
            throw new \UnexpectedValueException('internal corruption of phar (truncated manifest header)');
        }
        // Back to where we took off.
        $this->file->loadPosition();

        $this->numFiles = $this->file->readUint32le();
        $apiVersion     = $this->file->readUint16be();

        if (($apiVersion & 0xFFF0) > self::MAX_API_VERSION) {
            throw new \RuntimeException(
                sprintf(
                    'Unable to process phar file, unsupported API version ',
                    $apiVersion >> 12,
                    ($apiVersion >> 8) & 0xF,
                    ($apiVersion >> 4) & 0x0F
                )
            );
        }
        $this->phar->setApiVersion($apiVersion);

        $this->phar->setFlags($this->file->readUint32le());
        if (0 < ($aliasLength = $this->file->readUint32le())) {
            $this->phar->setAlias($this->file->read($aliasLength));
        }
        if (0 < ($metadataLength = $this->file->readUint32le())) {
            $this->phar->setMetadata(unserialize($this->file->read($metadataLength)));
        }

        $this->binOffset = $stubEnd + $manifestLength + 4;

        $fileLength = $this->file->getLength();
        if ($this->file->getLength() < $this->binOffset) {
            throw new \UnexpectedValueException('internal corruption of phar (truncated manifest header)');
        }
    }

    private function checkSignature()
    {
        // Validate the signature if any.
        if (!$this->phar->isSigned()) {
            return;
        }

        // Remember the cursor.
        $this->file->savePosition();

        // Hail Greg Beaver and Marcus Bürger.
        if ('GBMB' !== $this->file->seek(-4, SEEK_END)->read(4)) {
            throw new \RuntimeException('Phar signature does not contain magic value.');
        }

        $this->phar->setSignatureFlags($this->file->seek(-8, SEEK_END)->readUint32le());
        $algorithm = $this->phar->getSignatureAlgorithm();
        $length    = $this->phar->getSignatureLength();
        $signature = $this->file->seek(-($length + 8), SEEK_END)->read($length);
        $dataLength = $this->file->getLength();
        $data = $this->file->seek(0)->read($dataLength - ($length + 8));

        // Now validate the signature.
        if (hash($algorithm, $data, true) !== $signature) {
            throw new \RuntimeException('Invalid signature.');
        }

        // Back to where we took off.
        $this->file->loadPosition();
    }

    /**
     * Read a file entry from the file.
     *
     * @param $fileBinaryOffset
     *
     * @return FileEntry
     */
    private function readFile($fileBinaryOffset)
    {
        $filenameLength = $this->file->readUint32le();
        if (0 === $filenameLength) {
            throw new \RuntimeException('Unnamed file found.');
        }
        $filename             = $this->file->read($filenameLength);
        $fileSizeUncompressed = $this->file->readUint32le();
        $timestamp            = new \DateTime('@' . $this->file->readUint32le());
        $fileSizeCompressed   = $this->file->readUint32le();
        $crc                  = $this->file->readUint32le();
        $flags                = $this->file->readUint32le();

        if (0 < ($fileMetadataLength = $this->file->readUint32le())) {
            $metadata = unserialize($this->file->read($fileMetadataLength));
        }

        $this->file->savePosition();
        $this->file->seek($fileBinaryOffset);
        $content = $this->file->read($fileSizeCompressed);
        $this->file->loadPosition();

        $file = FileEntry::createFromPhar(
            $filename,
            $fileSizeUncompressed,
            $fileSizeCompressed,
            $timestamp,
            $crc,
            $flags,
            isset($metadata) ? $metadata : null,
            $content
        );

        // Ensure the crc is correct and the data can be decompressed.
        $file->getContent();

        $this->phar->addFile($file);

        return $file;
    }
}
