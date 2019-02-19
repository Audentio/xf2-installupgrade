<?php

namespace ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Traits;

use ThemeHouse\InstallAndUpgrade\Entity\Product;
use ThemeHouse\InstallAndUpgrade\Entity\ProductBatch;
use XF\Http\Upload;

trait AddonHandlerTrait
{
    /**
     * @param Product $addOn
     * @return mixed
     */
    public function installAddOnProduct(Product $addOn)
    {
        /** @var ProductBatch $batch */
        $batch = \XF::em()->create('ThemeHouse\InstallAndUpgrade:ProductBatch');
        $batch->addProduct($addOn);
        return $this->installAddOnProducts($batch);
    }

    /**
     * @param ProductBatch $productBatch
     * @return mixed
     */
    public function installAddOnProducts(ProductBatch $productBatch)
    {
        /** @var \ThemeHouse\InstallAndUpgrade\XF\Service\AddOnArchive\InstallBatchCreator $creator */
        $creator = $this->service('XF:AddOnArchive\InstallBatchCreator', \XF::app()->addOnManager());

        foreach ($productBatch->getProducts() as $addOn) {
            /** @var Product $addOn */
            $zipFile = $this->downloadAddOnProduct($addOn);

            /** @var Product $addOn */
            $upload = new Upload($zipFile, 'temp-' . $addOn->product_id . '.zip');
            $upload->setAllowedExtensions(['zip']);

            $creator->addUpload($upload);
            $addOn->fastUpdate('content_id', $creator->thLastAddedAddOnId());
        }

        /** @var \XF\Entity\AddOnInstallBatch $addOnBatch */
        $addOnBatch = $creator->save();

        foreach ($productBatch->getProducts() as $product) {
            $this->log($product, 'install', [
                'version' => $product->latest_version
            ]);
        }

        return $this->redirect($this->buildLink('add-ons/install-from-archive-confirm', null,
            ['batch_id' => $addOnBatch->batch_id]));
    }

    /**
     * @param Product $addOn
     */
    public function checkAddOnProductForUpdates(Product $addOn)
    {
        $latestVersion = $this->getLatestVersion($addOn);
        $addOn->latest_version = $latestVersion;
        $addOn->update_available = $this->compareVersions($addOn->current_version, $latestVersion);
        $addOn->saveIfChanged();
        $this->log($addOn, 'update_check');
    }

    /**
     * @param Product $addOn
     * @return mixed
     */
    public function downloadAddOnProduct(Product $addOn)
    {
        $this->log($addOn, 'download', [
            'version' => $addOn->latest_version
        ]);
        return $this->downloadProduct($addOn);
    }
}