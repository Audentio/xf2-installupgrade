<?php

namespace ThemeHouse\InstallAndUpgrade\Repository;

use League\Flysystem\AdapterInterface;
use League\Flysystem\EventableFilesystem\EventableFilesystem;
use League\Flysystem\Filesystem;
use League\Flysystem\ZipArchive\ZipArchiveAdapter;
use XF\FsMounts;
use XF\Mvc\Entity\Repository;
use XF\Util\File;

class FileHandling extends Repository
{
    /**
     * @param string $zipFilename
     * @param string $prefix
     * @return string
     */
    public function mountZip($zipFilename, $prefix)
    {
        $fs = \XF::fs();

        if (substr($zipFilename, 0, 7) == 'file://')
        {
            $zipFilename = substr($zipFilename, 7);
        }

        $zipAdapter = new ZipArchiveAdapter(
            File::canonicalizePath($zipFilename)
        );
        $zipFiles = new EventableFilesystem($zipAdapter, [
            'visibility' => AdapterInterface::VISIBILITY_PUBLIC
        ]);
        FsMounts::addDefaultWriteListeners($prefix, $zipFiles);
        $fs->mountFilesystem($prefix, $zipFiles);

        return $prefix;
    }

    public function unmountZip($prefix)
    {
        $fs = \XF::fs();

        $filesystem = $fs->getFilesystem($prefix);
        if ($filesystem instanceof  Filesystem)
        {
            $adapter = $filesystem->getAdapter();
            if ($adapter instanceof ZipArchiveAdapter)
            {
                $adapter->getArchive()->close();
            }
        }
    }

    /**
     * @param string   $abstractSrc
     * @param string   $realDest
     * @param callable $closure
     */
    public function copyFiles($abstractSrc, $realDest, callable $closure)
    {
        $dest = File::canonicalizePath($realDest);
        if (!$dest)
        {
            throw new \LogicException('Require a path for realDest');
        }
        $dest = rtrim($dest, '/') . '/';

        $fs = $this->app()->fs();
        $contents = $fs->listContents($abstractSrc . '/upload', true);
        foreach ($contents as $fileNode)
        {
            $destFile = \preg_replace('#^upload\/#i', '', $fileNode['path'], 1);
            $destPath = $dest . $destFile;
            if ($fileNode['type'] == 'dir')
            {
                File::createDirectory($destPath);
                continue;
            }

            $stream = $fs->readStream('addon-zip://' . $fileNode['path']);
            try
            {
                File::writeFile($destPath, $stream);
            }
            finally
            {
                fclose($stream);
            }
            if ($closure)
            {
                $closure($destFile);
            }
        }
    }
}