<?php

namespace ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Traits;

use ThemeHouse\InstallAndUpgrade\Entity\Product;
use ThemeHouse\InstallAndUpgrade\Entity\ProductBatch;
use ThemeHouse\InstallAndUpgrade\Service\LanguageArchive\Extractor;
use XF\Entity\Language;
use XF\Util\Xml;

trait LanguageHandlerTrait
{
    /**
     * @param ProductBatch $productBatch
     * @return mixed
     */
    public function installLanguageProducts(ProductBatch $productBatch)
    {
        /** @var Product $product */
        $product = $productBatch->getProducts()->first();
        $file = $productBatch->getFile($product);

        $request = \XF::app()->request();
        $xmls = $request->filter('xmls', 'array-str');
        $target = $request->filter('target', 'str');
        $overwrite = $request->filter('overwrite_language_id', 'uint');
        $parent = $request->filter('parent_language_id', 'uint');

        if (!$xmls) {
            $xmls = $this->getLanguageXmls($file);

            if (empty($xmls)) {
                return $this->error(\XF::phrase('th_iau_no_language_xmls_found_in_package'));
            }

            return $this->view('ThemeHouse\InstallAndUpgrade:Language\XMLSelect', 'th_iau_language_xml_select', [
                'xmls' => $xmls,
                'productBatch' => $productBatch,
                'redirectUrl' => $this->buildLink('th-install-upgrade/install-products', $productBatch),
                'parent' => $parent,
                'overwrite' => $overwrite,
                'target' => $target,
                'th_iau_xml' => ''
            ]);
        }

        /** @var Extractor $extractor */
        $extractor = $this->service('ThemeHouse\InstallAndUpgrade:LanguageArchive\Extractor', $file);
        $path = array_pop($xmls);
        $content = stream_get_contents($extractor->getFile($path));

        /** @var \XF\Service\Language\Import $languageImporter */
        $languageImporter = $this->service('XF:Language\Import');

        if ($target == 'overwrite') {
            /** @var Language $language */
            $language = \XF::em()->find('XF:Language', $overwrite);
            $languageImporter->setOverwriteLanguage($language);
        } else {
            if ($target == 'parent' && $parent) {
                /** @var Language $language */
                $language = \XF::em()->find('XF:Language', $parent);
                $languageImporter->setParentLanguage($language);
            }
        }

        $language = $languageImporter->importFromXml(Xml::open($content));

        $product->bulkSet([
            'content_id' => $language->language_id,
            'current_version' => $product->latest_version,
            'installed' => true
        ]);
        $product->saveIfChanged();
        $this->log($product, 'install', [
            'version' => $product->latest_version
        ]);

        return $this->redirect($this->buildLink('languages'));
    }

    /**
     * @param $archive
     * @param bool $parentOnly
     * @return array
     */
    protected function getLanguageXMLs($archive, $parentOnly = false)
    {
        /** @var Extractor $extractor */
        $extractor = $this->service('ThemeHouse\InstallAndUpgrade:LanguageArchive\Extractor', $archive);

        $files = $extractor->getXMLFiles();
        $xmls = array_values(preg_grep('/^(?:.*?\/)?(?:xmls?\/)?language-.*.xml$/', $files));

        $xmls = array_map(function ($xmlFile) use ($extractor) {
            $contents = stream_get_contents($extractor->getFile($xmlFile));
            $object = simplexml_load_string($contents);
            if ($object->getName() !== 'language') {
                return null;
            }

            $attributes = $object->attributes();

            return [
                'path' => $xmlFile,
                'title' => $attributes['title'],
            ];
        }, $xmls);

        return $xmls;
    }

    /**
     * @param Product $language
     */
    public function checkLanguageProductForUpdates(Product $language)
    {
        $latestVersion = $this->getLatestVersion($language);
        $language->latest_version = $latestVersion;
        $language->update_available = $this->compareVersions($language->current_version, $latestVersion);
        $language->saveIfChanged();
        $this->log($language, 'update_check');
    }

    public function downloadLanguageProduct(Product $language)
    {
        $this->log($language, 'download', [
            'version' => $language->latest_version
        ]);
        /** @noinspection PhpUnhandledExceptionInspection */
        return $this->downloadProduct($language);
    }
}