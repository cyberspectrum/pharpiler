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

use CyberSpectrum\PharPiler\Phar;
use DateTime;
use InvalidArgumentException;
use LogicException;
use RuntimeException;

/**
 * This class is the abstraction over phar file entries.
 *
 * @psalm-suppress PropertyNotSetInConstructor
 */
class FileEntry
{
    /**
     * The filename.
     *
     * @var string
     */
    private $filename;

    /**
     * The file flags.
     *
     * 0x000001FF These bits are reserved for defining specific file permissions of a file.
     *            Permissions are used for fstat() and can be used to recreate desired permissions upon extraction.
     * 0x00001000 If set, this file is compressed with zlib compression.
     * 0x00002000 If set, this file is compressed with bzip compression.
     *
     * @var int
     */
    private $flags;

    /**
     * The meta data or null when no meta data present.
     *
     * @var array|null
     */
    private $metadata;

    /**
     * The uncompressed file size.
     *
     * @var int
     */
    private $sizeUncompressed;

    /**
     * The compressed file size.
     *
     * @var int
     */
    private $sizeCompressed;

    /**
     * The crc32 hash.
     *
     * @var int|null
     */
    private $crc;

    /**
     * The timestamp.
     *
     * @var DateTime
     */
    private $timestamp;

    /**
     * Buffer holding the compressed content.
     *
     * @var string|null
     */
    private $content;

    /**
     * Buffer holding the uncompressed content.
     *
     * @var string|null
     */
    private $uncompressedContent;

    /**
     * Create a new instance.
     */
    public function __construct()
    {
        $this->timestamp = new DateTime();
        $this->setPermissions(0666);
    }

    /**
     * Create a new instance with values originating from a phar archive.
     *
     * @param string     $filename             The filename.
     * @param int        $fileSizeUncompressed The uncompressed file size.
     * @param int        $fileSizeCompressed   The compressed file size.
     * @param DateTime   $timestamp            The timestamp.
     * @param int|null   $crc                  The crc32 hash.
     * @param int        $flags                The file flags.
     * @param array|null $metadata             The meta data or null when no meta data present.
     * @param string     $content              The file content (compressed or uncompressed according to flags).
     *
     * @return FileEntry
     *
     * @throws RuntimeException When the CRC is invalid.
     * @throws RuntimeException When the real file size does not match the passed file size.
     */
    public static function createFromPhar(
        string $filename,
        int $fileSizeUncompressed,
        int $fileSizeCompressed,
        DateTime $timestamp,
        ?int $crc,
        int $flags,
        ?array $metadata,
        string $content
    ) {
        $instance                   = new static();
        $instance->filename         = $filename;
        $instance->sizeUncompressed = $fileSizeUncompressed;
        $instance->sizeCompressed   = $fileSizeCompressed;
        $instance->timestamp        = $timestamp;
        $instance->crc              = $crc;
        $instance->flags            = $flags;
        $instance->metadata         = $metadata;
        $instance->filename         = $filename;

        if ($instance->getCompression() === Phar::NONE) {
            $instance->uncompressedContent = $content;
            $desiredFileSize               = $fileSizeUncompressed;

            // Check the crc now.
            if (crc32($content) !== $crc) {
                throw new RuntimeException('CRC mismatch for ' . $filename);
            }
        } else {
            $instance->content = $content;
            $desiredFileSize   = $fileSizeCompressed;
        }

        if (strlen($content) !== $desiredFileSize) {
            throw new RuntimeException(
                sprintf(
                    'Compressed file size does not match specified file length %d !== %d',
                    strlen($content),
                    $desiredFileSize
                )
            );
        }

        return $instance;
    }

    /**
     * Retrieve filename.
     *
     * @return string
     */
    public function getFilename(): string
    {
        return $this->filename;
    }

    /**
     * Set filename.
     *
     * @param string $filename The new value.
     *
     * @return FileEntry
     */
    public function setFilename(string $filename): self
    {
        $this->filename = ltrim($filename, '/');

        return $this;
    }

    /**
     * Retrieve metadata
     *
     * @return array|null
     */
    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    /**
     * Set metadata.
     *
     * @param array|null $metadata The new value.
     *
     * @return FileEntry
     */
    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * Set the permissions.
     *
     * @param int $permissions The permissions.
     *
     * @return FileEntry
     */
    public function setPermissions($permissions): self
    {
        $this->flags = (($this->flags & 0xFFFFFE00) | ($permissions & 0x000001FF));

        return $this;
    }

    /**
     * Retrieve the permissions.
     *
     * @return int
     */
    public function getPermissions(): int
    {
        return ($this->flags & 0x000001FF);
    }

    /**
     * Retrieve the compression in use.
     *
     * @return int
     */
    public function getCompression(): int
    {
        return ($this->flags & (Phar::GZ | Phar::BZ2));
    }

    /**
     * Check the flags if at least one gz compressed file is contained.
     *
     * @return bool
     */
    public function isGzCompressed(): bool
    {
        return (bool) ($this->flags & Phar::GZ);
    }

    /**
     * Check the flags if at least one bz2 compressed file is contained.
     *
     * @return bool
     */
    public function isBz2Compressed(): bool
    {
        return (bool) ($this->flags & Phar::BZ2);
    }

    /**
     * Set the compression flag.
     *
     * @param int $flag The flag to set.
     *
     * @return void
     *
     * @throws InvalidArgumentException When the compression flag does match neither \Phar::GZ nor \Phar::BZ2.
     */
    public function setCompression($flag): void
    {
        if ((0 !== $flag) && (0 === ($flag & (Phar::GZ | Phar::BZ2)))) {
            throw new InvalidArgumentException('Invalid compression value passed.');
        }

        $this->flags = (($this->flags & 0xFFFFCFFF) | $flag);
    }

    /**
     * Retrieve the permissions as string lik 'rwxrwxrwx'.
     *
     * @return string
     */
    public function getPermissionString(): string
    {
        $permissions = $this->getPermissions();

        return
            $this->decodePermissionNibble(($permissions & 0xF)) .
            $this->decodePermissionNibble((($permissions >> 3) & 0xF)) .
            $this->decodePermissionNibble((($permissions >> 6) & 0xF));
    }

    /**
     * Retrieve flags.
     *
     * @return int
     */
    public function getFlags(): int
    {
        return $this->flags;
    }

    /**
     * Retrieve uncompressed size.
     *
     * @return int
     */
    public function getSizeUncompressed(): int
    {
        return $this->sizeUncompressed;
    }

    /**
     * Retrieve compressed size.
     *
     * @return int
     */
    public function getSizeCompressed(): int
    {
        if (0 === $this->getCompression()) {
            return $this->getSizeUncompressed();
        }

        // Update file info.
        if (empty($this->sizeCompressed)) {
            $this->getCompressedContent();
        }

        return $this->sizeCompressed;
    }

    /**
     * Retrieve Crc32 checksum.
     *
     * @return int
     */
    public function getCrc(): int
    {
        if (!isset($this->crc)) {
            $this->crc = crc32($this->getContent());
        }

        return $this->crc;
    }

    /**
     * Retrieve timestamp.
     *
     * @return DateTime
     */
    public function getTime(): DateTime
    {
        return $this->timestamp;
    }

    /**
     * Retrieve the uncompressed file content.
     *
     * @return string
     *
     * @throws LogicException When the Compression algorithm is invalid.
     *
     * @throws RuntimeException When the CRC is invalid.
     */
    public function getContent(): string
    {
        if (!isset($this->uncompressedContent)) {
            assert(is_string($this->content));
            // Decompress the content now.
            if ($this->isBz2Compressed()) {
                $this->assertExtBzip2Loaded();
                $content = bzdecompress($this->content);
            } elseif ($this->isGzCompressed()) {
                $this->assertExtGzLoaded();
                $content = gzinflate($this->content, $this->sizeUncompressed);
            }

            if (!isset($content)) {
                throw new LogicException('Unable to determine uncompressed content of file ' . $this->getFilename());
            }

            // Check the crc now.
            if (isset($this->crc) && (crc32($content) !== $this->crc)) {
                throw new RuntimeException('CRC mismatch while decompressing ' . $this->getFilename());
            }

            return $this->uncompressedContent = $content;
        }

        return $this->uncompressedContent;
    }

    /**
     * Retrieve the compressed file content.
     *
     * @return string
     *
     * @throws LogicException When the Compression algorithm is invalid.
     */
    public function getCompressedContent(): string
    {
        assert(is_string($this->uncompressedContent));
        if (0 === $this->getCompression()) {
            return $this->uncompressedContent;
        }

        if (!isset($this->content)) {
            // Compress the content now.
            if ($this->isBz2Compressed()) {
                $this->assertExtBzip2Loaded();
                $content = bzcompress($this->uncompressedContent);
            }
            if ($this->isGzCompressed()) {
                $this->assertExtGzLoaded();
                $content = gzdeflate($this->uncompressedContent, 9);
            }

            if (!isset($content)) {
                throw new LogicException('Compressed file but it has no content.');
            }

            $this->sizeCompressed = strlen($content);
            $this->content        = $content;
        }

        return $this->content;
    }

    /**
     * Set the uncompressed content.
     *
     * @param string $content The content to use.
     *
     * @return FileEntry
     */
    public function setContent($content): self
    {
        $this->uncompressedContent = $content;
        $this->sizeUncompressed    = strlen($content);
        unset($this->content);
        unset($this->sizeCompressed);
        unset($this->crc);

        return $this;
    }

    /**
     * Decode a single permission nibble to 'rwx' string notation.
     *
     * @param int $nibble The nibble to decode.
     *
     * @return string
     */
    private function decodePermissionNibble($nibble): string
    {
        return
            (($nibble & 0x4) ? 'r' : '-') .
            (($nibble & 0x2) ? 'w' : '-') .
            (($nibble & 0x1) ? 'x' : '-');
    }

    /**
     * Assert that the bz2 extension is loaded in php.
     *
     * @return void
     *
     * @throws RuntimeException If the extension is not loaded.
     */
    private function assertExtBzip2Loaded(): void
    {
        if (!function_exists('bzcompress')) {
            throw new RuntimeException('Need extension bz2 for gzip compression in phars');
        }
    }

    /**
     * Assert that the gzip extension is loaded in php.
     *
     * @return void
     *
     * @throws RuntimeException If the extension is not loaded.
     */
    private function assertExtGzLoaded(): void
    {
        if (!function_exists('gzdeflate')) {
            throw new RuntimeException('Need extension zlib for gzip compression in phars');
        }
    }
}
