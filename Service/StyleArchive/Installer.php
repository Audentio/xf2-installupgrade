<?php

namespace ThemeHouse\InstallAndUpgrade\Service\StyleArchive;

use ThemeHouse\InstallAndUpgrade\Entity\Product;
use ThemeHouse\InstallAndUpgrade\Repository\Log;
use XF\App;
use XF\Entity\Style;
use XF\Service\AbstractService;

// ######## NOTE: SIMILARITY TO XF CORE UPGRADER CODE ############
// Much of this code is similar to the XFUpgraderExtractor class in src/XF/Install/_upgrader/core.php.
// Changes should be mirrored when appropriate.

class Installer extends AbstractService
{
    protected $file;

    /**
     * @var Product
     */
    protected $product;

    /**
     * @var Style
     */
    protected $parentStyle;

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
     * @return array
     * @throws \XF\PrintableException
     */
    public function install(array $xmls, Style $parent = null, array $childXmls = [])
    {
        foreach ($xmls as $xml) {
            $response = $this->installStyle($xml, $parent, $childXmls);

            if($response['status'] == 'error') {
                return $response;
            }
        }

        return $response;
    }

    /**
     * @param $xml
     * @param Style|null $parent
     * @param array $childXmls
     * @return array
     * @throws \XF\PrintableException
     */
    protected function installStyle($xml, Style $parent = null, $childXmls = [])
    {
        /** @var \XF\Service\Style\Import $styleImporter */
        $styleImporter = $this->service('XF:Style\Import');

        $styleImporter->setParentStyle($parent);

        try {
            /** @var Extractor $extractor */
            $extractor = $this->service('ThemeHouse\InstallAndUpgrade:StyleArchive\Extractor', $this->file);
            $xmlContent = simplexml_load_string(stream_get_contents($extractor->getFile($xml)));
        }
        catch(\Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }

        if(!$styleImporter->isValidXml($xmlContent)) {
            return [
                'status' => 'error',
                'message' => \XF::phrase('th_installupgrade_invalid_xml')
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

        foreach($childXmls as $childXml) {
            $this->installStyle($childXml, $child);
        }

        return [
            'status' => 'success',
            'style' => $style,
        ];
    }
}