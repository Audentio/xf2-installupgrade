<?php

namespace ThemeHouse\InstallAndUpgrade\XF\Admin\Controller;

use ThemeHouse\InstallAndUpgrade\Entity\Product;
use ThemeHouse\InstallAndUpgrade\Entity\ProductBatch;
use ThemeHouse\InstallAndUpgrade\Entity\Profile;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\AbstractHandler;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces\StyleHandler;
use ThemeHouse\InstallAndUpgrade\Repository\InstallAndUpgrade;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\View;

class Style extends XFCP_Style
{
    public function actionIndex()
    {
        $response = parent::actionIndex();

        if ($response instanceof View) {
            $styles = $response->getParam('styleTree');
            $styles = $styles->getFlattened(0);

            $updates = [];
            foreach ($styles as $style) {
                /** @var Product $product */
                $product = $style['record']->THIAUProduct;

                if ($product && !empty($product->Profile->getHandler())
                    && $product->Profile->getHandler()->compareVersions($style['record']->th_iau_current_version,
                        $product->latest_version)) {
                    $updates[] = $style['record'];
                }
            }

            $response->setParam('updates', $updates);
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
            ->where('product_type', '=', 'style')
            ->fetch()->groupBy('profile_id');

        return $this->view('ThemeHouse\InstallAndUpgrade:Style\InstallUpgrade', 'th_iau_style_install_upgrade', [
            'products' => $products,
            'profiles' => $profiles,
            'styleTree' => $this->repository('XF:Style')->getStyleTree(false)
        ]);
    }

    /**
     * @throws \XF\Mvc\Reply\Exception
     * @throws \Exception
     */
    public function actionThInstallUpgradeProduct()
    {
        $productId = $this->filter('install', 'str');
        /** @var Product $product */
        $product = $this->assertRecordExists('ThemeHouse\InstallAndUpgrade:Product', explode('-', $productId),
            ['Profile']);

        $profile = $product->Profile;
        /** @var AbstractHandler $handler */
        $handler = $profile->getHandler();
        if (!$handler || !$handler->getCapability('productList')) {
            return $this->error('th_installupgrade_provider_not_found_or_cannot_install_from_product_list');
        }

        if ($product->product_type != 'style') {
            return $this->error('th_installupgrade_selected_product_must_be_style');
        }

        /** @var \ThemeHouse\InstallAndUpgrade\ControllerPlugin\Profile $controllerPlugin */
        $controllerPlugin = $this->plugin('ThemeHouse\InstallAndUpgrade:Profile');

        return $controllerPlugin->handleReply($handler, $product->Profile, function () use ($handler, $product) {
            /** @var ProductBatch $productBatch */
            $productBatch = $this->em()->create('ThemeHouse\InstallAndUpgrade:ProductBatch');
            /** @var StyleHandler $handler */
            $productBatch->addProduct($product, $handler->downloadStyleProduct($product));
            $productBatch->save();

            return $this->redirect($this->buildLink('th-install-upgrade/install-products', $productBatch, [
                'target' => $this->filter('target', 'str'),
                'overwrite_style_id' => $this->filter('overwrite_style_id', 'uint'),
                'parent_style_id' => $this->filter('parent_style_id', 'uint')
            ]));
        }, [
            'target' => $this->filter('target', 'str'),
            'overwrite_style_id' => $this->filter('overwrite_style_id', 'uint'),
            'parent_style_id' => $this->filter('parent_style_id', 'uint')
        ]);
    }

    /**
     * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\View
     * @throws \Exception
     */
    public function actionThInstallUpgradeUrl()
    {
        $url = $this->filter('url', 'str');

        /** @var InstallAndUpgrade $repo */
        $repo = $this->getInstallUpgradeRepo();

        /** @var Profile $profile */
        $profile = $repo->getProfileFromUrl($url, $error);

        if (!$profile) {
            return $this->error($error);
        }

        $handler = $profile->getHandler();

        if (!$handler->getCapability('style')) {
            return $this->error(\XF::phrase('th_installupgrade_provider_does_not_support_styles'));
        }

        /** @var \ThemeHouse\InstallAndUpgrade\ControllerPlugin\Profile $controllerPlugin */
        $controllerPlugin = $this->plugin('ThemeHouse\InstallAndUpgrade:Profile');

        return $controllerPlugin->handleReply($handler, $profile, function () use ($handler, $url) {

            /** @var ProductBatch $productBatch */
            $productBatch = $this->em()->create('ThemeHouse\InstallAndUpgrade:ProductBatch');
            /** @var StyleHandler $handler */
            $product = $handler->createStyleProductFromURL($url);
            $productBatch->addProduct($product, $handler->downloadStyleProduct($product));
            $productBatch->save();

            return $this->redirect($this->buildLink('th-install-upgrade/install-products', $productBatch, [
                'target' => $this->filter('target', 'str'),
                'overwrite_style_id' => $this->filter('overwrite_style_id', 'uint'),
                'parent_style_id' => $this->filter('parent_style_id', 'uint')
            ]));
        }, [
            'target' => $this->filter('target', 'str'),
            'overwrite_style_id' => $this->filter('overwrite_style_id', 'uint'),
            'parent_style_id' => $this->filter('parent_style_id', 'uint'),
            'url' => $this->filter('url', 'str')
        ]);
    }

    /**
     * @param ParameterBag $params
     * @return View
     * @throws \Exception
     */
    public function actionThInstallUpgradeUpdate(ParameterBag $params)
    {
        /** @var \ThemeHouse\InstallAndUpgrade\XF\Entity\Style $style */
        $style = $this->assertStyleExists($params->style_id);
        /** @var Product $product */
        $product = $style->THIAUProduct;

        if (!$product->update_available) {
            return $this->notFound();
        }

        if ($this->isPost()) {
            $handler = $product->Profile->getHandler();

            /** @var \ThemeHouse\InstallAndUpgrade\ControllerPlugin\Profile $controllerPlugin */
            $controllerPlugin = $this->plugin('ThemeHouse\InstallAndUpgrade:Profile');

            return $controllerPlugin->handleReply($handler, $product->Profile,
                function () use ($handler, $product, $style) {
                    /** @var ProductBatch $productBatch */
                    $productBatch = $this->em()->create('ThemeHouse\InstallAndUpgrade:ProductBatch');
                    /** @var StyleHandler $handler */
                    $productBatch->addProduct($product, $handler->downloadStyleProduct($product));
                    $productBatch->save();

                    return $this->redirect($this->buildLink('th-install-upgrade/install-products', $productBatch, [
                        'target' => 'overwrite',
                        'overwrite_style_id' => $style->style_id
                    ]));
                }, [
                    'target' => 'overwrite',
                    'overwrite_style_id' => $style->style_id
                ]);
        } else {
            $viewParams = [
                'style' => $style,
                'product' => $product
            ];

            return $this->view('ThemeHouse\InstallAndUpgrade:Style\Upgrade', 'th_iau_style_upgrade', $viewParams);
        }
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