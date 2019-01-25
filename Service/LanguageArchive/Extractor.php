<?php

namespace ThemeHouse\InstallAndUpgrade\Service\LanguageArchive;

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

    public function getFile($path)
    {
        $zip = $this->zip();
        return $zip->getStream($path);
    }
}