<?php

namespace ThemeHouse\InstallAndUpgrade\XF\Admin\Controller;

use ThemeHouse\InstallAndUpgrade\Entity\Product;
use ThemeHouse\InstallAndUpgrade\Entity\ProductBatch;
use ThemeHouse\InstallAndUpgrade\Entity\Profile;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces\AddOnHandler;
use ThemeHouse\InstallAndUpgrade\Repository\InstallAndUpgrade;
use XF\Mvc\Entity\ArrayCollection;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\Redirect;
use XF\Mvc\Reply\View;

class AddOn extends XFCP_AddOn
{
    public function actionIndex()
    {
        $response = parent::actionIndex();

        if ($response instanceof View) {
            $installed = $response->getParam('installed');
            $upgradeAble = $response->getParam('upgradeable');

            foreach ($installed as $addOnId => $addOn) {
                /** @var \ThemeHouse\InstallAndUpgrade\XF\Entity\AddOn $addOn */
                if ($addOn->THIAUProduct && $addOn->THIAUProduct->update_available) {
                    $upgradeAble[$addOnId] = $addOn;
                    unset($installed[$addOnId]);
                }
            }

            $response->setParam('installed', $installed);
            $response->setParam('upgradeable', $upgradeAble);
        }

        return $response;
    }

    public function actionThInstallUpgrade()
    {
        /** @var InstallAndUpgrade $repo */
        $repo = $this->getInstallUpgradeRepo();

        if (!$repo->canUseInstallUpgrade($error)) {
            return $this->error($error);
        }

        $profiles = $this->finder('ThemeHouse\InstallAndUpgrade:Profile')
            ->fetch();

        $products = $this->finder('ThemeHouse\InstallAndUpgrade:Product')
            ->where('product_type', '=', 'addOn')
            ->fetch()->groupBy('profile_id');

        return $this->view('ThemeHouse\InstallAndUpgrade:AddOn\InstallUpgrade', 'th_iau_addon_install_upgrade', [
            'products' => $products,
            'profiles' => $profiles
        ]);
    }

    /**
     * @param ParameterBag $params
     * @return string
     * @throws \XF\Mvc\Reply\Exception
     * @throws \Exception
     */
    public function actionThInstallUpgradeUpgrade(ParameterBag $params)
    {
        /** @var \ThemeHouse\InstallAndUpgrade\XF\Entity\AddOn $addOn */
        $addOn = $this->assertAddOnAvailable($params->addon_id_url);
        $product = $addOn->THIAUProduct;

        if (empty($product->Profile->getHandler())) {
            return $this->notFound();
        }

        $handler = $product->Profile->getHandler();
        if (!$handler->getCapability('addOn')) {
            return $this->noPermission();
        }

        /** @var AddOnHandler $handler */
        return $handler->installAddOnProduct($product);
    }

    /**
     * @return \XF\Mvc\Reply\Error|Redirect
     * @throws \XF\PrintableException
     * @throws \Exception
     */
    public function actionThInstallUpgradeProducts()
    {
        $productIds = $this->filter('install', 'array-str');

        if (empty($productIds)) {
            return $this->error(\XF::phrase('th_installupgrade_no_product_selected'));
        }

        $profileId = 0;
        $productIdsSplit = [];
        foreach($productIds as $productId) {
            $productId = explode('-', $productId);
            $productIdsSplit[] = $productId[1];

            if ($profileId && $profileId != $productId[0]) {
                return $this->error('th_installupgrade_all_selected_products_must_be_from_same_provider');
            }
            $profileId = $productId[0];
        }

        $products = $this->finder('ThemeHouse\InstallAndUpgrade:Product')
            ->where('profile_id', '=', $profileId)
            ->where('product_id', '=', $productIdsSplit)
            ->fetch();

        $productType = 'addOn';
        foreach ($products as $product) {
            if ($product->product_type != $productType) {
                return $this->error('th_installupgrade_all_selected_products_must_have_same_type');
            }
        }

        /** @var Profile $profile */
        $profile = $this->em()->find('ThemeHouse\InstallAndUpgrade:Profile', $profileId);
        $handler = $profile->getHandler();
        if (!$handler || !$handler->getCapability('productList')) {
            return $this->error('th_installupgrade_provider_not_found_or_cannot_install_from_product_list');
        }

        /** @var ProductBatch $productBatch */
        $productBatch = $this->em()->create('ThemeHouse\InstallAndUpgrade:ProductBatch');
        foreach ($products as $product) {
            $productBatch->addProduct($product);
        }
        $productBatch->save();

        return $this->redirect($this->buildLink('th-install-upgrade/install-products', $productBatch));
    }

    /**
     * @throws \XF\Mvc\Reply\Exception
     * @throws \Exception
     */
    public function actionThInstallUpgradeUrl()
    {
        $this->assertPostOnly();

        $urls = $this->filter('urls', 'array-str');
        $urls = array_filter(array_unique($urls));

        /** @var InstallAndUpgrade $repo */
        $repo = $this->getInstallUpgradeRepo();

        /** @var Profile $lastProfile */
        $lastProfile = null;
        foreach ($urls as $url) {
            if(!$url) {
                continue;
            }

            /** @var Profile $profile */
            $profile = $repo->getProfileFromUrl($url, $error);

            if ($lastProfile && $profile->profile_id != $lastProfile->profile_id) {
                $this->error(\XF::phrase('th_installupgrade_all_urls_must_point_to_same_provider'));
            }

            $lastProfile = $profile;
        }

        if (!$lastProfile && isset($error)) {
            return $this->error($error);
        }

        $handler = $lastProfile->getHandler();

        if (!$handler->getCapability('addOn')) {
            return $this->error(\XF::phrase('th_installupgrade_provider_does_not_support_addons'));
        }
        /** @var \ThemeHouse\InstallAndUpgrade\ControllerPlugin\Profile $controllerPlugin */
        $controllerPlugin = $this->plugin('ThemeHouse\InstallAndUpgrade:Profile');

        return $controllerPlugin->handleReply($handler, $lastProfile, function () use ($handler, $urls) {
            /** @var ProductBatch $productBatch */
            $productBatch = $this->em()->create('ThemeHouse\InstallAndUpgrade:ProductBatch');
            foreach ($urls as $url) {
                /** @var AddOnHandler $handler */
                $product = $handler->createAddOnProductFromUrl($url);
                $productBatch->addProduct($product);
            }
            $productBatch->save();

            return $this->redirect($this->buildLink('th-install-upgrade/install-products', $productBatch));
        }, ['urls' => $urls]);
    }

    /**
     * @return InstallAndUpgrade
     */
    protected function getInstallUpgradeRepo()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->repository('ThemeHouse\InstallAndUpgrade:InstallAndUpgrade');
    }
}