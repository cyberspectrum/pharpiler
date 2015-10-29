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
 * This class is a simple file reader.
 */
class StreamReader
{
    /**
     * @var resource
     */
    protected $file;

    /**
     * The position stack.
     *
     * @var int[]
     */
    private $positions = [];

    /**
     * Create a new instance.
     *
     * @param string $filename The file to read.
     */
    public function __construct($filename)
    {
        $this->file = $this->doOpen($filename);

        if (!is_resource($this->file)) {
            throw new \RuntimeException('Could not open file ' . $filename);
        }
    }

    /**
     * Destroy the instance.
     */
    function __destruct()
    {
        fflush($this->file);
        fclose($this->file);
        unset($this->file);
    }

    /**
     * Read the given amount of bytes from the stream.
     *
     * @param int $bytes The amount of bytes to read.
     *
     * @return string
     */
    public function read($bytes, $allowFail = false)
    {
        if (0 === $bytes) {
            throw new \InvalidArgumentException('Can not read zero bytes.');
        }

        $data = fread($this->file, $bytes);

        if (!$allowFail && (strlen($data) !== $bytes)) {
            throw new \LengthException('Failed to read ' . $bytes . ' bytes.');
        }

        return (string) $data;
    }

    /**
     * Read a 32 bit unsigned integer (little endian).
     *
     * @return mixed
     */
    public function readUint32le()
    {
        $res = unpack('V', $this->read(4));

        return $res[1];
    }

    /**
     * Read a 16 bit unsigned integer (big endian).
     *
     * @return StreamWriter
     */
    public function readUint16be()
    {
        $res = unpack('n', $this->read(2));

        return $res[1];
    }

    /**
     * Save the current position on the stack.
     *
     * @return StreamReader
     */
    public function savePosition()
    {
        array_push($this->positions, $this->tell());

        return $this;
    }

    /**
     * Restore the position from the stack.
     *
     * @return StreamReader
     */
    public function loadPosition()
    {
        $position = array_pop($this->positions);

        if (null === $position) {
            throw new \RuntimeException('Position stack is empty.');
        }

        return $this->seek($position);
    }

    /**
     * Seeks on the file.
     *
     * To move to a position before the end-of-file, you need to pass a negative value in offset and set whence to
     * SEEK_END.
     *
     * Valid whence values are:
     * SEEK_SET - Set position equal to offset bytes.
     * SEEK_CUR - Set position to current location plus offset.
     * SEEK_END - Set position to end-of-file plus offset.
     *
     * Upon success, returns 0; otherwise, returns -1. Note that seeking past EOF is not considered an error.
     *
     * @param int $offset The offset.
     * @param int $whence If whence is not specified, it is assumed to be SEEK_SET.
     *
     * @return StreamReader
     */
    public function seek($offset, $whence = SEEK_SET)
    {
        if (0 !== fseek($this->file, $offset, $whence)) {
            throw new \RuntimeException('Could not seek.');
        }

        return $this;
    }

    /**
     * Tell the current position in the file.
     *
     * @return int
     */
    public function tell()
    {
      return ftell($this->file);
    }

    /**
     * Get all the contents.
     *
     * @return string
     */
    public function getContents()
    {
        $this->savePosition()->seek(0);

        $result = '';
        while ('' !== ($buffer = $this->read(64*1024, true))) {
            $result .= $buffer;
        }

        $this->loadPosition();

        return $result;
    }

    /**
     * Retrieve the amount of data in the stream.
     *
     * @return int
     */
    public function getLength()
    {
        $this->savePosition()->seek(0);
        $length = 0;
        while ('' !== ($buffer = $this->read(1024, true))) {
            $length += strlen($buffer);
        }
        $this->loadPosition();

        return $length;
    }

    /**
     * Hash the whole stream from this position on.
     *
     * @param string $hash The hash algorithm to use.
     *
     * @param bool $raw_output When set to TRUE, outputs raw binary data. FALSE outputs lowercase hexits.
     *
     * @return string
     */
    public function hashStream($hash, $raw_output = false)
    {
        $hash = hash_init($hash);

        $this->hashUpdate($hash);

        return hash_final($hash, $raw_output);
    }

    /**
     * Update the hash with the whole stream from this position on.
     *
     * @param resource $context The hash context.
     *
     * @return StreamWriter
     */
    public function hashUpdate($context)
    {
        $this->savePosition();
        while ('' !== ($buffer = $this->read(1024, true))) {
            if (!hash_update($context, $buffer)) {
                throw new \RuntimeException('Failed to update hash');
            }
        }
        $this->loadPosition();

        return $this;
    }

    /**
     * Open the file handle and return it.
     *
     * @param string $filename The file to open.
     *
     * @return resource|bool
     */
    protected function doOpen($filename)
    {
        return fopen($filename, 'rb');
    }
}
