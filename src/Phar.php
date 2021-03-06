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

/**
 * This class mimics the base \Phar class with neat operations.
 */
class Phar
{
    /**
     * The file name.
     *
     * @var string
     */
    private $fname;

    /**
     * The filter handler to use.
     *
     * @var Filter
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
    public function __construct($fname, Filter $filters = null, $alias = null)
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
    public function getPharchive()
    {
        return $this->pharchive;
    }

    /**
     * Used to set the PHP loader or bootstrap stub of a Phar archive.
     *
     * @param string $stubFile Full or relative path to a file on disk to be added to the phar archive.
     *
     * @return void
     */
    public function setStubFromFileFiltered($stubFile)
    {
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
     * @throws \LogicException When no filters are present.
     *
     * @throws \InvalidArgumentException When the source file could not be found.
     */
    public function addFileFiltered($file, $localName = null)
    {
        if (!isset($this->filters)) {
            throw new \LogicException('No filters available.');
        }

        if (!is_file($file)) {
            throw new \InvalidArgumentException('File not found ' . $file);
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
     * @throws \LogicException When no filters are present.
     */
    public function addFromStringFiltered($localPath, $content)
    {
        if (!isset($this->filters)) {
            throw new \LogicException('No filters available.');
        }

        $this->addFromString($localPath, $this->filters->process($localPath, $content));
    }

    /**
     * Retrieve a list of contents.
     *
     * @return string[]
     */
    public function getFileList()
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
     * @param string      $file      The path to the file.
     *
     * @param null|string $localName The local name of the file to use.
     *
     * @return FileEntry
     */
    public function addFile($file, $localName = null)
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
    public function addFromString($localName, $contents)
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
    public function delete($localName)
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
    public function compressFiles($algorithm)
    {
        foreach ($this->pharchive->getFiles() as $file) {
            $file->setCompression($algorithm);
        }
    }
}
