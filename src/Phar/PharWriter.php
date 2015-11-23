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
 * This class builds a phar.
 */
class PharWriter
{
    /**
     * The pharchive to work on.
     *
     * @var Pharchive
     */
    private $phar;

    /**
     * The stream writer in use for the phar file.
     *
     * @var StreamWriter
     */
    private $file;

    /**
     * The stream writer to use for the binary data.
     *
     * @var StreamWriter
     */
    private $bins;

    /**
     * Save the phar to disk.
     *
     * @param Pharchive $phar     The phar to write.
     *
     * @param string    $filename The file name to write to.
     *
     * @return void
     *
     * @throws \RuntimeException When the stub is illegal.
     */
    public function save($phar, $filename)
    {
        $this->phar = $phar;
        $this->file = new StreamWriter($filename);
        $this->bins = new StreamWriter('php://temp');

        $stub = $phar->getStub();

        if (false === ($pos = strpos($stub, '__HALT_COMPILER();'))) {
            throw new \RuntimeException('Illegal stub');
        }
        // Mimic plain PHP phar writing, it adds the closing tag.
        $this->file->write(substr($stub, 0, ($pos + 18)) . " ?>\r\n");

        $this->buildManifest();
        $this->file->writeStream($this->bins->seek(0));
        unset($this->bins);

        // Now sign the phar.
        if ($phar->isSigned()) {
            $this->file->savePosition();
            $this->file->seek(0);
            $hash = $this->file->hashStream($phar->getSignatureAlgorithm(), true);

            $this->file->loadPosition();
            $this->file->write($hash);
            $this->file->writeUint32le($phar->getSignatureFlags());
            $this->file->write('GBMB');
        }

        unset($this->phar);
        unset($this->file);

    }

    /**
     * Build the manifest and return it.
     *
     * @return void
     */
    private function buildManifest()
    {
        $files    = $this->phar->getFiles();
        $alias    = $this->phar->getAlias();
        $metaData = $this->phar->getMetadata();
        if (!empty($metaData)) {
            $metaData = serialize($metaData);
        }

        $manifest = new StreamWriter('php://temp');
        $manifest
            ->writeUint32le(count($files))
            ->writeUint16be($this->phar->getApiVersion())
            ->writeUint32le($this->phar->getFlags())
            ->writeUint32le(strlen($alias))
            ->write($alias)
            ->writeUint32le(strlen($metaData))
            ->write($metaData);

        // Add files now.
        foreach ($files as $file) {
            $manifest->writeStream($this->serializeFile($file));
        }

        $this->file->writeUint32le($manifest->getLength());
        $this->file->writeStream($manifest->seek(0));
    }

    /**
     * Serialize a single file.
     *
     * @param FileEntry $file The file to serialize.
     *
     * @return StreamWriter
     */
    private function serializeFile(FileEntry $file)
    {
        $result = new StreamWriter('php://temp');

        $fileName = $file->getFilename();
        $metaData = $file->getMetadata();
        if (!empty($metaData)) {
            $metaData = serialize($metaData);
        }

        $result
            ->writeUint32le(strlen($fileName))
            ->write($fileName)
            ->writeUint32le($file->getSizeUncompressed())
            ->writeUint32le($file->getTime()->getTimestamp())
            ->writeUint32le($file->getSizeCompressed())
            ->writeUint32le($file->getCrc())
            ->writeUint32le($file->getFlags())
            ->writeUint32le(strlen($metaData))
            ->write($metaData)
            ->seek(0);

        $this->bins->write($file->getCompressedContent());

        return $result;
    }
}
