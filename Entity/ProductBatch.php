<?php

namespace ThemeHouse\InstallAndUpgrade\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;
use XF\Util\File;

/**
 * COLUMNS
 * @property int|null batch_id
 * @property int start_date
 * @property int complete_date
 * @property array product_ids
 * @property array results
 */
class ProductBatch extends Entity
{
    /**
     * @var
     */
    protected $storeFiles;

    /**
     * @param Structure $structure
     * @return Structure
     */
    public static function getStructure(Structure $structure)
    {
        $structure->table = 'xf_th_installupgrade_product_batch';
        $structure->shortName = 'ThemeHouse\InstallAndUpgrade:ProductBatch';
        $structure->primaryKey = 'batch_id';

        $structure->columns = [
            'batch_id' => ['type' => self::UINT, 'autoIncrement' => true, 'nullable' => true],
            'start_date' => ['type' => self::UINT, 'default' => \XF::$time],
            'complete_date' => ['type' => self::UINT, 'default' => 0],
            'product_ids' => ['type' => self::JSON_ARRAY, 'default' => []],
            'results' => ['type' => self::JSON_ARRAY, 'default' => []]
        ];

        $structure->getters = [];
        $structure->relations = [];

        return $structure;
    }

    /**
     * @param Product $product
     * @param null $file
     */
    public function addProduct(Product $product, $file = null)
    {
        $ids = $this->product_ids;
        $ids[] = $product->product_id;
        $this->product_ids = $ids;

        if ($file) {
            $this->storeFile($product, $file);
        }
    }

    /**
     * @param Product $product
     * @param $file
     */
    protected function storeFile(Product $product, $file)
    {
        $this->storeFiles[$product->product_id] = $file;
    }

    /**
     * @param Product $product
     * @return bool|null|string
     */
    public function getFile(Product $product)
    {
        $filePath = $this->getAbstractedProductBatchPath($product->product_id);
        if (File::abstractedPathExists($filePath)) {
            return File::copyAbstractedPathToTempFile($filePath);
        }
        return null;
    }

    /**
     * @param $productId
     * @return string
     */
    public function getAbstractedProductBatchPath($productId)
    {
        return $this->getAbstractedBatchPath() . "/{$productId}.zip";
    }

    /**
     * @return string
     */
    public function getAbstractedBatchPath()
    {
        if (!$this->batch_id) {
            throw new \LogicException("Cannot get batch path until saved");
        }

        return "internal-data://th_iau_product_batch/{$this->batch_id}";
    }

    /**
     * @return \XF\Mvc\Entity\ArrayCollection
     */
    public function getProducts()
    {
        return $this->finder('ThemeHouse\InstallAndUpgrade:Product')
            ->where('product_id', '=', $this->product_ids)
            ->fetch();
    }

    /**
     *
     */
    protected function _postSave()
    {
        if ($this->storeFiles) {
            foreach ($this->storeFiles AS $productId => $tempFile) {
                $abstractedPath = $this->getAbstractedProductBatchPath($productId);
                File::copyFileToAbstractedPath($tempFile, $abstractedPath);
            }
            $this->storeFiles = [];
        }
    }

    /**
     *
     */
    protected function _postDelete()
    {
        File::deleteAbstractedDirectory($this->getAbstractedBatchPath());
    }
}