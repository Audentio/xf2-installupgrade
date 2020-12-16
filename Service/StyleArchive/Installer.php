<?php

namespace ThemeHouse\InstallAndUpgrade\Service\StyleArchive;

use Exception;
use ThemeHouse\InstallAndUpgrade\Entity\Product;
use XF;
use XF\App;
use XF\Entity\Style;
use XF\PrintableException;
use XF\Service\AbstractService;
use XF\Service\Style\Import;

// ######## NOTE: SIMILARITY TO XF CORE UPGRADER CODE ############
// Much of this code is similar to the XFUpgraderExtractor class in src/XF/Install/_upgrader/core.php.
// Changes should be mirrored when appropriate.

/**
 * Class Installer
 * @package ThemeHouse\InstallAndUpgrade\Service\StyleArchive
 */
class Installer extends AbstractService
{
    /**
     * @var
     */
    protected $file;

    /**
     * @var Product
     */
    protected $product;

    /**
     * @var Style
     */
    protected $parentStyle;

    /**
     * Installer constructor.
     * @param App $app
     * @param $file
     * @param Product $product
     * @param Style|null $parentStyle
     */
    public function __construct(App $app, $file, Product $product, Style $parentStyle = null)
    {
        parent::__construct($app);

        $this->file = $file;
        $this->product = $product;
        $this->parentStyle = $parentStyle;
    }

    /**
     * @param array $xmls
     * @param Style|null $parent
     * @param array $childXmls
     * @param bool $force
     * @return array
     * @throws PrintableException
     */
    public function install(array $xmls, Style $parent = null, array $childXmls = [], $force = false)
    {
        $response = null;

        foreach ($xmls as $xml) {
            $response = $this->installStyle($xml, $parent, $childXmls, $force);

            if ($response['status'] == 'error') {
                return $response;
            }
        }

        return $response;
    }

    /**
     * @param $xml
     * @param Style|null $parent
     * @param array $childXmls
     * @param bool $force
     * @return array
     * @throws PrintableException
     */
    protected function installStyle($xml, Style $parent = null, $childXmls = [], $force = false)
    {
        /** @var Import $styleImporter */
        $styleImporter = $this->service('XF:Style\Import');

        $styleImporter->setParentStyle($parent);

        try {
            /** @var Extractor $extractor */
            $extractor = $this->service('ThemeHouse\InstallAndUpgrade:StyleArchive\Extractor', $this->file);
            $xmlContent = simplexml_load_string(stream_get_contents($extractor->getFile($xml)));
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }

        if (!$styleImporter->isValidXml($xmlContent, $error)) {
            return [
                'status' => 'error',
                'message' => $error
            ];
        }

        if (!$force && !$styleImporter->isValidConfiguration($xmlContent, $errors)) {
            return [
                'status' => 'error',
                'message' => XF::phrase('import_verification_errors_x_select_skip_checks', [
                    'errors' => implode(' ', $errors
                    )
                ])
            ];
        }

        $style = $styleImporter->importFromXml($xmlContent);
        $style->bulkSet([
            'user_selectable' => false,
            'th_iau_product_id' => $this->product->product_id,
            'th_iau_profile_id' => $this->product->profile_id,
            'th_iau_current_version' => $this->product->latest_version,
            'th_iau_xml' => $xml
        ]);
        $style->saveIfChanged();

        /** @var Style $child */
        $child = $this->em()->create('XF:Style');
        $child->bulkSet([
            'parent_id' => $style->style_id,
            'title' => $style->title . ' (Config)',
            'user_selectable' => true,
        ]);
        $child->save();

        foreach ($childXmls as $childXml) {
            $this->installStyle($childXml, $child);
        }

        return [
            'status' => 'success',
            'style' => $style,
        ];
    }
}