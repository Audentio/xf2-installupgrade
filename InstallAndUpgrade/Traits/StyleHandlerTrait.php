<?php

namespace ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Traits;

use ThemeHouse\InstallAndUpgrade\Entity\Product;
use ThemeHouse\InstallAndUpgrade\Entity\ProductBatch;
use ThemeHouse\InstallAndUpgrade\Service\StyleArchive\Extractor;
use ThemeHouse\InstallAndUpgrade\Service\StyleArchive\Installer;
use XF\Entity\Style;
use XF\Util\Xml;

trait StyleHandlerTrait
{
    /**
     * @param ProductBatch $productBatch
     * @return mixed
     * @throws \XF\PrintableException
     */
    public function installStyleProducts(ProductBatch $productBatch)
    {
        /** @var Product $product */
        $product = $productBatch->getProducts()->first();
        $file = $productBatch->getFile($product);

        $request = \XF::app()->request();
        $xmls = $request->filter('xmls', 'array-str');
        $target = $request->filter('target', 'str');
        $overwrite = $request->filter('overwrite_style_id', 'uint');
        $parent = $request->filter('parent_style_id', 'uint');

        $force = $request->filter('force', 'bool');

        if (!$xmls) {
            $xmls = $this->getStyleXMLs($file);

            if (empty($xmls)) {
                return $this->error(\XF::phrase('th_iau_no_style_xmls_found_in_package'));
            }

            return $this->view('ThemeHouse\InstallAndUpgrade:Style\XMLSelect', 'th_iau_style_xml_select', [
                'xmls' => $xmls,
                'productBatch' => $productBatch,
                'redirectUrl' => $this->buildLink('th-install-upgrade/install-products', $productBatch),
                'single' => $target == 'overwrite',
                'parent' => $parent,
                'overwrite' => $overwrite,
                'target' => $target,
                'selectedXml' => $target == 'overwrite' ? \XF::em()->find('XF:Style', $overwrite)->th_iau_xml : '',
                'force' => $force
            ]);
        }

        $jobManager = \XF::app()->jobManager();

        $jobManager->enqueueUnique("th-iau-extract-" . \XF::$time,
            'ThemeHouse\InstallAndUpgrade:ExtractStyle',
            [
                'batchId' => $productBatch->batch_id,
                'productId' => $product->product_id
            ], true
        );

        if ($target == 'overwrite') {
            /** @var \XF\Service\Style\Import $styleImporter */
            $styleImporter = $this->service('XF:Style\Import');
            /** @var Style $style */
            $style = \XF::em()->find('XF:Style', $overwrite);
            $styleImporter->setOverwriteStyle($style);

            /** @var Extractor $extractor */
            $extractor = $this->service('ThemeHouse\InstallAndUpgrade:StyleArchive\Extractor', $file);
            $path = array_pop($xmls);
            $content = stream_get_contents($extractor->getFile($path));
            $document = Xml::open($content);

            if (!$styleImporter->isValidXml($document, $error)) {
                return $this->error($error);
            }

            if (!$force && !$styleImporter->isValidConfiguration($document, $errors)) {
                return $this->error(\XF::phrase('import_verification_errors_x_select_skip_checks', [
                    'errors' => implode(' ', $errors
                    )
                ]));
            }

            $style = $styleImporter->importFromXml($document);
            $style->bulkSet([
                'th_iau_product_id' => $product->product_id,
                'th_iau_profile_id' => $product->profile_id,
                'th_iau_xml' => $path,
                'th_iau_current_version' => $product->latest_version
            ]);
            $style->saveIfChanged();

        } else {
            $parentXmls = [];
            $childXmls = [];

            foreach ($xmls as $xml) {
                if (strpos($xml, 'child_xml') === 0) {
                    $childXmls[] = $xml;
                } else {
                    $parentXmls[] = $xml;
                }
            }

            if (empty($parentXmls)) {
                if (empty($childXmls)) {
                    return $this->error(\XF::phrase('th_installupgrade_no_xml_selected'));
                }

                $parentXmls = $this->getStyleXMLs($file, true);
                $parentXml = array_pop($parentXmls);
                $parentXmls = [$parentXml['path']];
            }

            $parentStyle = \XF::em()->find('XF:Style', $parent);

            /** @var Installer $service */
            $service = $this->service('ThemeHouse\InstallAndUpgrade:StyleArchive\Installer', $file, $product, $force);
            $result = $service->install($parentXmls, $parentStyle, $childXmls);

            if ($result['status'] == 'error') {
                return $this->error($result['message']);
            }
        }
        $this->log($product, 'install', [
            'version' => $product->latest_version
        ]);

        return $this->redirect($this->buildLink('styles'));
    }

    /**
     * @param Product $style
     */
    public function checkStyleProductForUpdates(Product $style)
    {
        $latestVersion = $this->getLatestVersion($style);
        $style->latest_version = $latestVersion;
        $style->saveIfChanged();
        $this->log($style, 'update_check');
    }


    /**
     * @param Product $style
     * @return mixed
     */
    public function downloadStyleProduct(Product $style)
    {
        $this->log($style, 'download', [
            'version' => $style->latest_version
        ]);
        /** @noinspection PhpUnhandledExceptionInspection */
        return $this->downloadProduct($style);
    }

    /**
     * @param $archive
     * @param bool $parentOnly
     * @return array
     */
    protected function getStyleXMLs($archive, $parentOnly = false)
    {
        /** @var Extractor $extractor */
        $extractor = $this->service('ThemeHouse\InstallAndUpgrade:StyleArchive\Extractor', $archive);

        $files = $extractor->getXMLFiles();
        $xmls = array_values(preg_grep('/^(?!child_xmls)(?:.*?\/)?(?:xmls?\/)?style-[^.]*.xml$/', $files));

        if (!$parentOnly) {
            $children = array_values(preg_grep('/^child_xmls?\/[^.]*.xml$/', $files));
            $xmls = array_merge($xmls, $children);
        }

        $xmls = array_map(function ($xmlFile) use ($extractor) {
            $contents = stream_get_contents($extractor->getFile($xmlFile));
            $object = simplexml_load_string($contents);
            if ($object->getName() !== 'style') {
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
}