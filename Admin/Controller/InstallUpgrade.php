<?php

namespace ThemeHouse\InstallAndUpgrade\Admin\Controller;

use ThemeHouse\InstallAndUpgrade\Entity\Product;
use ThemeHouse\InstallAndUpgrade\Entity\ProductBatch;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\AbstractHandler;
use XF;
use XF\Admin\Controller\AbstractController;
use XF\Mvc\Entity\Entity;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\Error;
use XF\Mvc\Reply\Exception;
use XF\Mvc\Reply\Redirect;
use XF\Mvc\Reply\View;

/**
 * Class InstallUpgrade
 * @package ThemeHouse\InstallAndUpgrade\Admin\Controller
 */
class InstallUpgrade extends AbstractController
{
    /**
     * @param ParameterBag $params
     * @return Error|Redirect|View
     * @throws Exception
     * @throws \Exception
     */
    public function actionInstallProducts(ParameterBag $params)
    {
        /** @var ProductBatch $productBatch */
        /** @noinspection PhpUndefinedFieldInspection */
        $productBatch = $this->assertBatchExists($params->batch_id);

        $products = $productBatch->getProducts();

        $profile = null;
        foreach($products as $product) {
            if($product->Profile) {
                /** @var \ThemeHouse\InstallAndUpgrade\Entity\Profile $profile */
                $profile = $product->Profile;
                break;
            }
        }

        if(!$profile) {
            return $this->error(XF::phrase('th_installupgrade_no_installupgrade_profile_found'));
        }

        /** @var AbstractHandler $handler */
        $handler = $profile->getHandler();

        /** @var Product $product */
        $product = $products->last();

        if (!$handler->getCapability($product->product_type)) {
            return $this->error(XF::phrase('th_installupgrade_provider_does_not_support_' . $product->product_type . 's'));
        }

        /** @var \ThemeHouse\InstallAndUpgrade\ControllerPlugin\Profile $controllerPlugin */
        $controllerPlugin = $this->plugin('ThemeHouse\InstallAndUpgrade:Profile');

        return $controllerPlugin->handleReply($handler, $product->Profile,
            function () use ($handler, $product, $productBatch) {
                $method = "install" . ucfirst($product->product_type) . "Products";
                $errors = null;
                return $handler->$method($productBatch, $errors);
            });
    }

    /**
     * @param $id
     * @param null $with
     * @param null $phraseKey
     * @return Entity
     * @throws Exception
     */
    protected function assertBatchExists($id, $with = null, $phraseKey = null)
    {
        return $this->assertRecordExists('ThemeHouse\InstallAndUpgrade:ProductBatch', $id, $with, $phraseKey);
    }

    /**
     * @param ParameterBag $params
     * @return Error|Redirect|View
     * @throws Exception
     * @throws \Exception
     */
    public function actionInstallProduct(ParameterBag $params)
    {
        /** @var Product $product */
        /** @noinspection PhpUndefinedFieldInspection */
        $product = $this->assertProductExists([$params->profile_id, $params->product_id]);

        /** @var AbstractHandler $handler */
        $handler = $product->Profile->getHandler();

        if (!$handler->getCapability($product->product_type)) {
            return $this->error(XF::phrase('th_installupgrade_provider_does_not_support_' . $product->product_type . 's'));
        }

        /** @var \ThemeHouse\InstallAndUpgrade\ControllerPlugin\Profile $controllerPlugin */
        $controllerPlugin = $this->plugin('ThemeHouse\InstallAndUpgrade:Profile');

        return $controllerPlugin->handleReply($handler, $product->Profile, function () use ($handler, $product) {
            $method = "install" . ucfirst($product->product_type) . "Product";
            $errors = null;
            return $handler->$method($product, $errors);
        });
    }

    /**
     * @param $id
     * @param null $with
     * @param null $phraseKey
     * @return Entity
     * @throws Exception
     */
    protected function assertProductExists($id, $with = null, $phraseKey = null)
    {
        return $this->assertRecordExists('ThemeHouse\InstallAndUpgrade:Product', $id, $with, $phraseKey);
    }
}