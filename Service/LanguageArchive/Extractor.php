<?php

namespace ThemeHouse\InstallAndUpgrade\Service\LanguageArchive;

use LogicException;
use XF\App;
use XF\Service\AbstractService;
use ZipArchive;

// ######## NOTE: SIMILARITY TO XF CORE UPGRADER CODE ############
// Much of this code is similar to the XFUpgraderExtractor class in src/XF/Install/_upgrader/core.php.
// Changes should be mirrored when appropriate.

/**
 * Class Extractor
 * @package ThemeHouse\InstallAndUpgrade\Service\LanguageArchive
 */
class Extractor extends AbstractService
{
    /**
     * @var
     */
    protected $fileName;

    /**
     * @var ZipArchive|null
     */
    protected $_zip;

    /**
     * Extractor constructor.
     * @param App $app
     * @param $fileName
     */
    public function __construct(App $app, $fileName)
    {
        parent::__construct($app);

        $this->fileName = $fileName;
    }

    /**
     * @return array
     */
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

    /**
     * @return null|ZipArchive
     */
    protected function zip()
    {
        $this->open();
        return $this->_zip;
    }

    /**
     * @return bool
     */
    public function open()
    {
        if (!$this->_zip) {
            $zip = new ZipArchive();
            $openResult = $zip->open($this->fileName);
            if ($openResult !== true) {
                throw new LogicException("File could not be opened as a zip ($openResult)");
            }

            $this->_zip = $zip;
        }

        return true;
    }

    /**
     * @param $path
     * @return resource
     */
    public function getFile($path)
    {
        $zip = $this->zip();
        return $zip->getStream($path);
    }
}