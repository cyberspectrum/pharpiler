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
use Iterator;

/**
 * This class mimics the base \Phar class with neat operations.
 */
class Phar
{
    private $fname;

    /**
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
     * @link http://php.net/manual/en/phar.construct.php
     *
     * @param string $fname   Path to an existing Phar archive or to-be-created archive. The file name's extension must
     *                        contain .phar.
     *
     * @param Filter $filters The filters to apply to the files being added.
     *
     * @param string $alias   Alias with which this Phar archive should be referred to in calls to stream functionality.
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
     * @return bool
     */
    public function setStubFromFileFiltered($stubFile)
    {
        $this->pharchive->setStub($this->filters->process($stubFile, file_get_contents($stubFile)));
    }

    /**
     * Add a file from the filesystem to the phar archive.
     *
     * @param string $file Full or relative path to a file on disk to be added to the phar archive.
     *
     * @param string $localname Path that the file will be stored in the archive.
     *
     * @return void
     */
    public function addFileFiltered($file, $localname = null)
    {
        if (!isset($this->filters)) {
            throw new \RuntimeException('No filters available.');
        }

        if (!is_file($file)) {
            throw new \InvalidArgumentException('File not found ' . $file);
        }

        $this->addFromString($localname ?: $file, $this->filters->process($file, file_get_contents($file)));
    }

    /**
     * Add a single file to the phar.
     *
     * @param string $localPath The pathname of the file to use.
     *
     * @param string $content   The file content.
     *
     * @return void
     */
    public function addFromStringFiltered($localPath, $content)
    {
        if (!isset($this->filters)) {
            throw new \RuntimeException('No filters available.');
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
        return array_map(function($file) { return $file->getFilename(); }, $this->pharchive->getFiles());
    }

    /**
     * {@inheritDoc}
     */
    public function addEmptyDir($dirname)
    {
        // FIXME: not implemented.
        parent::addEmptyDir($dirname);
    }

    /**
     * @return FileEntry
     */
    public function addFile($file, $localname = null)
    {
        $add = new FileEntry();
        $add
            ->setContent(file_get_contents($file))
            ->setFilename($localname);

        $this->pharchive->addFile($add);

        return $add;
    }

    /**
     * @return FileEntry
     */
    public function addFromString($localname, $contents)
    {
        $add = new FileEntry();
        $add
            ->setContent($contents)
            ->setFilename($localname);

        $this->pharchive->addFile($add);

        return $add;
    }

    /**
     * {@inheritDoc}
     */
    public function delete($entry)
    {
        foreach ($this->files as $key => $file) {
            if ($entry === $file) {
                unset($this->files[$key]);
                break;
            }
        }

        parent::delete($entry);
    }

    public function compressFiles($algo)
    {
        foreach ($this->pharchive->getFiles() as $file) {
            $file->setCompression($algo);
        }
    }
}
