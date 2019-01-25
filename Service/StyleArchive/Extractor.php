<?php

namespace ThemeHouse\InstallAndUpgrade\Service\StyleArchive;

use XF\App;
use XF\Service\AbstractService;
use XF\Util\File;

// ######## NOTE: SIMILARITY TO XF CORE UPGRADER CODE ############
// Much of this code is similar to the XFUpgraderExtractor class in src/XF/Install/_upgrader/core.php.
// Changes should be mirrored when appropriate.

class Extractor extends AbstractService
{
    protected $fileName;

    /**
     * @var \ZipArchive|null
     */
    protected $_zip;

    public function __construct(App $app, $fileName)
    {
        parent::__construct($app);

        $this->fileName = $fileName;
    }

    public function open()
    {
        if (!$this->_zip) {
            $zip = new \ZipArchive();
            $openResult = $zip->open($this->fileName);
            if ($openResult !== true) {
                throw new \LogicException("File could not be opened as a zip ($openResult)");
            }

            $this->_zip = $zip;
        }

        return true;
    }

    protected function zip()
    {
        $this->open();
        return $this->_zip;
    }

    public function getXMLFiles()
    {
        $zip = $this->zip();

        $xmls = [];
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $zipFileName = $zip->getNameIndex($i);

            if (pathinfo($zipFileName, PATHINFO_EXTENSION) == 'xml') {
                $xmls[] = $zipFileName;
            }
        }

        return $xmls;
    }

    public function checkWritable(array $changeset = null, &$failures = [])
    {
        $zip = $this->zip();
        $failures = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $zipFileName = $zip->getNameIndex($i);
            $fsFileName = $this->getFsFileNameFromZipName($zipFileName);
            if ($fsFileName === null) {
                continue;
            }

            if (is_array($changeset) && !isset($changeset[$fsFileName])) {
                // we're not changing this file
                continue;
            }

            if (!File::isWritable($this->getFinalFsFileName($fsFileName))) {
                $failures[] = $fsFileName;
            }
        }

        return $failures ? false : true;
    }

    public function copyFiles(array $changeset = null, $start = 0, \XF\Timer $timer = null)
    {
        $zip = $this->zip();
        $lastComplete = $start;

        for ($i = $start; $i < $zip->numFiles; $i++) {
            $lastComplete = $i;

            $zipFileName = $zip->getNameIndex($i);
            $fsFileName = $this->getFsFileNameFromZipName($zipFileName);
            if ($fsFileName === null) {
                continue;
            }

            $finalFileName = $this->getFinalFsFileName($fsFileName);

            $dataStream = $zip->getStream($zipFileName);
            $targetWritten = @File::writeFile($finalFileName, $dataStream, false);

            if (!$targetWritten) {
                return [
                    'status' => 'error',
                    'error' => "Failed write to {$fsFileName}"
                ];
            }

            if ($timer && $timer->limitExceeded()) {
                break;
            }
        }

        $complete = ($i >= $zip->numFiles);

        return [
            'status' => ($complete ? 'complete' : 'incomplete'),
            'last' => $lastComplete
        ];
    }

    public function getFile($path)
    {
        $zip = $this->zip();
        return $zip->getStream($path);
    }

    protected function getFsFileNameFromZipName($fileName)
    {
        if (substr($fileName, -1) === '/') {
            // this is a directory we can just skip this
            return null;
        }

        if (!preg_match("#^(?:.*?\/)?uploads?/.#", $fileName)) {
            // file outside of "upload" so we can just skip this
            return null;
        }

        return preg_replace("#^((?:.*?\/)?uploads?/)#", '', $fileName); // remove "upload/"
    }

    protected function getFinalFsFileName($fileName)
    {
        return \XF::getRootDirectory() . \XF::$DS . $fileName;
    }
}