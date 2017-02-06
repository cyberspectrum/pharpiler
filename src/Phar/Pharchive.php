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
class Pharchive
{
    /**
     * The alias.
     *
     * @var string
     */
    private $alias;

    /**
     * The stub.
     *
     * @var string
     */
    private $stub;

    /**
     * The api version (default to 1.1.0).
     *
     * @var int
     */
    private $apiVersion = 0x1100;

    /**
     * The flags for the phar file.
     *
     * 0x00010000 If set, this Phar contains a verification signature.
     * 0x00001000 If set, this Phar contains at least 1 file that is compressed with zlib compression.
     * 0x00002000 If set, this Phar contains at least 1 file that is compressed with bzip compression.
     *
     * @var int
     */
    private $flags;

    /**
     * The flags for the phar signature.
     *
     * 0x0001 is used to define an MD5 signature.
     * 0x0002 is used to define an SHA1 signature.
     * 0x0004 is used to define an SHA256 signature.
     * 0x0008 is used to define an SHA512 signature.
     *
     * SHA256 and SHA512 signature support was introduced with API version 1.1.0.
     *
     * @var int
     */
    private $signatureFlags;

    /**
     * The meta data.
     *
     * @var array
     */
    private $metadata;

    /**
     * The attached files.
     *
     * @var FileEntry[]
     */
    private $files = [];

    /**
     * Retrieve alias.
     *
     * @return string
     */
    public function getAlias()
    {
        return $this->alias;
    }

    /**
     * Set alias.
     *
     * @param string $alias The new value.
     *
     * @return Pharchive
     */
    public function setAlias($alias)
    {
        $this->alias = $alias;

        return $this;
    }

    /**
     * Retrieve stub
     *
     * @return string
     */
    public function getStub()
    {
        return $this->stub;
    }

    /**
     * Set the stub.
     *
     * @param mixed $stub The new value.
     *
     * @return Pharchive
     */
    public function setStub($stub)
    {
        $this->stub = (string) $stub;

        return $this;
    }

    /**
     * Retrieve api version as int.
     *
     * @return int
     */
    public function getApiVersion()
    {
        return $this->apiVersion;
    }

    /**
     * Retrieve api version as string.
     *
     * @return int
     */
    public function getApiVersionString()
    {
        return $this->decodePharVersion($this->getApiVersion());
    }

    /**
     * Set api version.
     *
     * @param int|string|array $apiVersion The version either as 3-element array or dot separated string or encoded int.
     *
     * @return Pharchive
     */
    public function setApiVersion($apiVersion)
    {
        if (!is_int($apiVersion)) {
            $apiVersion = $this->encodePharVersion($apiVersion);
        }
        $this->apiVersion = $apiVersion;

        return $this;
    }

    /**
     * Retrieve flags
     *
     * @return int
     */
    public function getFlags()
    {
        return $this->flags;
    }

    /**
     * Set flags.
     *
     * @param int $flags The new value.
     *
     * @return Pharchive
     */
    public function setFlags($flags)
    {
        $this->flags = $flags;

        return $this;
    }

    /**
     * Check the flags if at least one gz compressed file is contained.
     *
     * @return bool
     */
    public function hasGzCompressedFiles()
    {
        return (bool) ($this->flags & \Phar::GZ);
    }

    /**
     * Check the flags if at least one bz2 compressed file is contained.
     *
     * @return bool
     */
    public function hasBz2CompressedFiles()
    {
        return (bool) ($this->flags & \Phar::BZ2);
    }

    /**
     * Check the flags if the phar is signed.
     *
     * @return bool
     */
    public function isSigned()
    {
        return (bool) ($this->flags & 0x00010000);
    }

    /**
     * Retrieve metadata.
     *
     * @return array
     */
    public function getMetadata()
    {
        return $this->metadata;
    }

    /**
     * Set metadata.
     *
     * @param array $metadata The new value.
     *
     * @return Pharchive
     */
    public function setMetadata($metadata)
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * Retrieve the file list.
     *
     * @return FileEntry[]
     */
    public function getFiles()
    {
        return array_values($this->files);
    }

    /**
     * Add a file entry to the list.
     *
     * @param FileEntry $file The file entry to add.
     *
     * @return Pharchive
     */
    public function addFile(FileEntry $file)
    {
        $this->files[spl_object_hash($file)] = $file;

        return $this;
    }

    /**
     * Remove a file entry from the list.
     *
     * @param FileEntry $file The file entry to add.
     *
     * @return Pharchive
     */
    public function removeFile(FileEntry $file)
    {
        unset($this->files[spl_object_hash($file)]);

        return $this;
    }

    /**
     * Retrieve signature flags.
     *
     * @return int
     */
    public function getSignatureFlags()
    {
        return $this->signatureFlags;
    }

    /**
     * Set signature flags.
     *
     * @param int $signatureType The new value.
     *
     * @return Pharchive
     */
    public function setSignatureFlags($signatureType)
    {
        $this->signatureFlags = $signatureType;

        // Implicitly mark as signed now.
        $this->flags |= 0x00010000;

        // Ensure the algorithm matches the API version.
        $this->getSignatureAlgorithm();

        return $this;
    }

    /**
     * Determine the type of the signature and return the hash algorithm to use.
     *
     * @return string
     *
     * @throws \RuntimeException When the phar is not signed.
     * @throws \RuntimeException When the signature flags are not understood.
     */
    public function getSignatureAlgorithm()
    {
        if (!$this->isSigned()) {
            throw new \RuntimeException('Phar is not signed.');
        }

        switch ($this->signatureFlags) {
            case \Phar::MD5:
                // MD5 signature
                return 'md5';

            case \Phar::SHA1:
                // SHA1 signature
                return 'sha1';

            case \Phar::SHA256:
                // SHA256 signature (introduced with API version 1.1.0$).
                $this->checkHashApiVersion('sha256', '1.1.0');
                return 'sha256';

            case \Phar::SHA512:
                // SHA512 signature (introduced with API version 1.1.0).
                $this->checkHashApiVersion('sha512', '1.1.0');
                return 'sha512';
            default:
        }

        throw new \RuntimeException('Unknown signature algorithm in flags ' . dechex($this->signatureFlags));
    }

    /**
     * Determine the length of the signature.
     *
     * @return int
     *
     * @throws \RuntimeException When the phar is not signed.
     * @throws \RuntimeException When the signature flags are not understood.
     */
    public function getSignatureLength()
    {
        if (!$this->isSigned()) {
            throw new \RuntimeException('Phar is not signed.');
        }

        switch ($this->signatureFlags) {
            case \Phar::MD5:
                // MD5 signature
                return 16;

            case \Phar::SHA1:
                // SHA1 signature
                return 20;

            case \Phar::SHA256:
                // SHA256 signature (introduced with API version 1.1.0).
                $this->checkHashApiVersion('sha256', '1.1.0');
                return 32;

            case \Phar::SHA512:
                // SHA512 signature (introduced with API version 1.1.0).
                $this->checkHashApiVersion('sha512', '1.1.0');
                return 64;
            default:
        }

        throw new \RuntimeException('Unknown signature algorithm in flags ' . dechex($this->signatureFlags));
    }

    /**
     * Check that the API version matches against the minimum version.
     *
     * @param string $hash    The hash algorithm name.
     *
     * @param string $version The api version where the algorithm got introduced.
     *
     * @return void
     *
     * @throws \RuntimeException When the current api version is lower than the introducing api version.
     */
    private function checkHashApiVersion($hash, $version)
    {
        $apiVersion = $this->getApiVersionString();
        if (version_compare($apiVersion, $version, '<')) {
            throw new \RuntimeException(
                sprintf(
                    'Phar API version is %s but phar indicates to be signed with %s which got introduced in %s.',
                    $apiVersion,
                    $hash,
                    $version
                )
            );
        }
    }

    /**
     * Encode the passed phar version.
     *
     * @param array|string $version The version either as 3-element array or dot separated string.
     *
     * @return int
     *
     * @throws \RuntimeException When the version string could not be parsed or is neither array nor string.
     */
    private function encodePharVersion($version)
    {
        if (is_string($version)) {
            $chunks = explode('.', $version, 3);
            foreach ($chunks as $nibble) {
                if (!preg_match('#^[0-9]+$#', $nibble)) {
                    throw new \RuntimeException('Invalid version string ' . $version);
                }
            }

            $version = $chunks;
        }

        if (!is_array($version)) {
            throw new \RuntimeException('Version must be either a string or array.');
        }

        $nibbles = array_map(function ($nibble) {
            $nibble = intval($nibble);
            if ($nibble > 15) {
                throw new \OutOfBoundsException('Invalid version field ' . $nibble . ' must be between 0 and 15');
            }

            return $nibble;
        }, $version);

        return (($nibbles[2] << 4) | ($nibbles[1] << 8) | ($nibbles[0] << 12));
    }

    /**
     * Decode the passed version.
     *
     * @param int $version The version number as 16 bit unsigned integer.
     *
     * @return string
     */
    private function decodePharVersion($version)
    {
        $nibbles[0] = (($version >> 12) & 0xF);
        $nibbles[1] = (($version >> 8) & 0xF);
        $nibbles[2] = (($version >> 4) & 0xF);

        return implode('.', $nibbles);
    }
}
