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

use RuntimeException;

/**
 * This class is a simple file writer.
 */
class StreamWriter extends StreamReader
{
    /**
     * Write the given amount of bytes to the stream.
     *
     * @param string|null $string The string to write.
     * @param int|null    $bytes  The amount of bytes to write.
     *
     * @return StreamWriter
     *
     * @throws RuntimeException When not all bytes could be written to the file.
     */
    public function write(?string $string, $bytes = null): self
    {
        if (null === $string || 0 === $bytes) {
            return $this;
        }

        if ($bytes) {
            $desired = $bytes;
            $written = fwrite($this->file, $string, $bytes);
        } else {
            $desired = strlen($string);
            $written = fwrite($this->file, $string);
        }

        if ($desired !== $written) {
            throw new RuntimeException('Failed to write ' . $desired . ' bytes');
        }

        return $this;
    }

    /**
     * Write a 32 bit unsigned integer (little endian).
     *
     * @param int $uint The integer to write.
     *
     * @return StreamWriter
     */
    public function writeUint32le(int $uint): self
    {
        return $this->write(pack('V', $uint));
    }

    /**
     * Write a 16 bit unsigned integer (big endian).
     *
     * @param int $uint The integer to write.
     *
     * @return StreamWriter
     */
    public function writeUint16be(int $uint): self
    {
        return $this->write(pack('n', $uint));
    }

    /**
     * Append all the contents from the passed stream reader.
     *
     * @param StreamReader $stream The stream to write.
     *
     * @return StreamWriter
     */
    public function writeStream(StreamReader $stream): self
    {
        $stream->savePosition();
        while ('' !== ($buffer = $stream->read(1024, true))) {
            $this->write($buffer);
        }
        $stream->loadPosition();

        return $this;
    }

    /**
     * Open the file handle and return it.
     *
     * @param string $filename The file to open.
     *
     * @return resource
     *
     * @throws RuntimeException When the file could not be opened.
     */
    protected function doOpen(string $filename)
    {
        $handle = fopen($filename, 'wb+');
        if (false === $handle) {
            throw new RuntimeException('Could not open file ' . $filename);
        }
        return $handle;
    }
}
