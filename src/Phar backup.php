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

use Iterator;

/**
 * This class enhances the base \Phar class with neat operations.
 */
class Phar2 extends \Phar
{
    /**
     * @var Filter
     */
    private $filters;

    /**
     * @var string[]
     */
    private $files;

    /**
     * Construct a Phar archive object
     *
     * @link http://php.net/manual/en/phar.construct.php
     *
     * @param string $fname Path to an existing Phar archive or to-be-created archive. The file name's extension must
     *                      contain .phar.
     *
     * @param Filter $filters The filters to apply to the files being added.
     *
     * @param int    $flags Flags to pass to parent class RecursiveDirectoryIterator.
     *
     * @param string $alias Alias with which this Phar archive should be referred to in calls to stream functionality.
     */
    public function __construct($fname, Filter $filters = null, $flags = null, $alias = null)
    {
        parent::__construct($fname, $flags, $alias);

        $this->filters = $filters;

        //$this->compressFiles(\Phar::GZ);
        $this->setSignatureAlgorithm(\Phar::SHA1);
        $this->startBuffering();

        foreach (new \RecursiveIteratorIterator($this) as $file) {
            $this->files[] = preg_replace('#(.*?\.phar)#', '', $file);
        }
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
        parent::setStub($this->filters->process($stubFile, file_get_contents($stubFile)));
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
        return $this->files;
    }

    /**
     * {@inheritDoc}
     */
    public function addEmptyDir($dirname)
    {
        $this->files[] = $dirname;

        parent::addEmptyDir($dirname);
    }

    /**
     * {@inheritDoc}
     */
    public function addFile($file, $localname = null)
    {
        $this->files[] = $localname;

        parent::addFile($file, $localname);
    }

    /**
     * {@inheritDoc}
     */
    public function addFromString($localname, $contents)
    {
        $this->files[] = $localname;

        parent::addFromString($localname, $contents);
    }

    /**
     * {@inheritDoc}
     */
    public function buildFromDirectory($base_dir, $regex = null)
    {
        parent::buildFromDirectory($base_dir, $regex);

        // FIXME: need to update file list here.
    }

    /**
     * {@inheritDoc}
     */
    public function buildFromIterator(Iterator $iter, $base_directory = null)
    {
        parent::buildFromIterator($iter, $base_directory);

        // FIXME: need to update file list here.
    }

    /**
     * {@inheritDoc}
     */
    public function copy($oldfile, $newfile)
    {
        $this->files[] = $newfile;

        parent::copy($oldfile, $newfile);
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
}
