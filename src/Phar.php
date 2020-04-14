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

namespace CyberSpectrum\PharPiler;

use CyberSpectrum\PharPiler\Phar\FileEntry;
use CyberSpectrum\PharPiler\Phar\Pharchive;
use InvalidArgumentException;
use LogicException;

/**
 * This class mimics the base \Phar class with neat operations.
 */
class Phar
{
    const BZ2        = 8192;
    const GZ         = 4096;
    const NONE       = 0;
    const PHAR       = 1;
    const TAR        = 2;
    const ZIP        = 3;
    const COMPRESSED = 61440;
    const PHP        = 0;
    const PHPS       = 1;
    const MD5        = 1;
    const OPENSSL    = 16;
    const SHA1       = 2;
    const SHA256     = 3;
    const SHA512     = 4;

    /**
     * The file name.
     *
     * @var string
     */
    private $fname;

    /**
     * The filter handler to use.
     *
     * @var Filter|null
     */
    private $filters;

    /**
     * The pharchive being built.
     *
     * @var Pharchive
     */
    private $pharchive;

    /**
     * Construct a Phar archive object
     *
     * @param string $fname   Path to an existing Phar archive or to-be-created archive. The file name's extension must
     *                        contain .phar.
     *
     * @param Filter $filters The filters to apply to the files being added.
     *
     * @param string $alias   Alias with which this Phar archive should be referred to in calls to stream functionality.
     *
     * @link http://php.net/manual/en/phar.construct.php
     */
    public function __construct(string $fname, Filter $filters = null, string $alias = null)
    {
        $this->fname = $fname;

        $this->filters = $filters;

        $this->pharchive = new Pharchive();
        $this->pharchive->setAlias($alias);
    }

    /**
     * Retrieve the internal pharchive instance.
     *
     * @return Pharchive
     */
    public function getPharchive(): Pharchive
    {
        return $this->pharchive;
    }

    /**
     * Used to set the PHP loader or bootstrap stub of a Phar archive.
     *
     * @param string $stubFile Full or relative path to a file on disk to be added to the phar archive.
     *
     * @return void
     *
     * @throws LogicException When no filters are present.
     */
    public function setStubFromFileFiltered(string $stubFile): void
    {
        if (null === $this->filters) {
            throw new LogicException('No filters available.');
        }

        $this->pharchive->setStub($this->filters->process($stubFile, file_get_contents($stubFile)));
    }

    /**
     * Add a file from the filesystem to the phar archive.
     *
     * @param string $file      Full or relative path to a file on disk to be added to the phar archive.
     *
     * @param string $localName Path that the file will be stored in the archive.
     *
     * @return void
     *
     * @throws LogicException When no filters are present.
     *
     * @throws InvalidArgumentException When the source file could not be found.
     */
    public function addFileFiltered(string $file, string $localName = null): void
    {
        if (null === $this->filters) {
            throw new LogicException('No filters available.');
        }

        if (!is_file($file)) {
            throw new InvalidArgumentException('File not found ' . $file);
        }

        $this->addFromString($localName ?: $file, $this->filters->process($file, file_get_contents($file)));
    }

    /**
     * Add a single file to the phar.
     *
     * @param string $localPath The pathname of the file to use.
     *
     * @param string $content   The file content.
     *
     * @return void
     *
     * @throws LogicException When no filters are present.
     */
    public function addFromStringFiltered(string $localPath, string $content): void
    {
        if (null === $this->filters) {
            throw new LogicException('No filters available.');
        }

        $this->addFromString($localPath, $this->filters->process($localPath, $content));
    }

    /**
     * Retrieve a list of contents.
     *
     * @return string[]
     */
    public function getFileList(): array
    {
        return array_map(
            function (FileEntry $file) {
                return $file->getFilename();
            },
            $this->pharchive->getFiles()
        );
    }

    /**
     * Add a file to the phar.
     *
     * @param string $file      The path to the file.
     *
     * @param string $localName The local name of the file to use.
     *
     * @return FileEntry
     */
    public function addFile(string $file, string $localName): FileEntry
    {
        $add = new FileEntry();
        $add
            ->setContent(file_get_contents($file))
            ->setFilename($localName);

        $this->pharchive->addFile($add);

        return $add;
    }

    /**
     * Add a file from the passed string.
     *
     * @param string $localName The local name of the file.
     *
     * @param string $contents  The file contents to use.
     *
     * @return FileEntry
     */
    public function addFromString(string $localName, string $contents)
    {
        $add = new FileEntry();
        $add
            ->setContent($contents)
            ->setFilename($localName);

        $this->pharchive->addFile($add);

        return $add;
    }

    /**
     * Delete a file.
     *
     * @param string $localName The local name of the file.
     *
     * @return void
     */
    public function delete(string $localName)
    {
        foreach ($this->pharchive->getFiles() as $file) {
            if ($localName === $file->getFilename()) {
                $this->pharchive->removeFile($file);
                break;
            }
        }
    }

    /**
     * Compress all files using the passed algorithm.
     *
     * @param int $algorithm The algoithm to use (either \Phar::GZ or \Phar::BZ2).
     *
     * @return void
     */
    public function compressFiles(int $algorithm)
    {
        foreach ($this->pharchive->getFiles() as $file) {
            $file->setCompression($algorithm);
        }
    }
}
