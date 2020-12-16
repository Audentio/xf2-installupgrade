<?php

namespace ThemeHouse\InstallAndUpgrade\XF\Admin\Controller;

use Exception;
use ThemeHouse\InstallAndUpgrade\Entity\Product;
use ThemeHouse\InstallAndUpgrade\Entity\ProductBatch;
use ThemeHouse\InstallAndUpgrade\Entity\Profile;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\AbstractHandler;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces\StyleHandler;
use ThemeHouse\InstallAndUpgrade\Repository\InstallAndUpgrade;
use XF;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\Error;
use XF\Mvc\Reply\Redirect;
use XF\Mvc\Reply\View;

/**
 * Class Style
 * @package ThemeHouse\InstallAndUpgrade\XF\Admin\Controller
 */
class Style extends XFCP_Style
{
    /**
     * @return Error|View
     */
    public function actionThInstallUpgrade()
    {
        $repo = $this->getInstallUpgradeRepo();

        if (!$repo->canUseInstallUpgrade($error)) {
            return $this->error($error);
        }

        /** @var \ThemeHouse\InstallAndUpgrade\Repository\Profile $profileRepo */
        $profileRepo = $this->repository('ThemeHouse\InstallAndUpgrade:Profile');
        $profiles = $profileRepo->getProductListProfiles();

        /** @var \ThemeHouse\InstallAndUpgrade\Repository\Product $productRepo */
        $productRepo = $this->repository('ThemeHouse\InstallAndUpgrade:Product');
        $products = $productRepo->findProductListProductsForProfiles($profiles, 'style')
            ->fetch()->groupBy('profile_id');

        /** @var \ThemeHouse\InstallAndUpgrade\XF\Repository\Style $styleRepo */
        $styleRepo = $this->repository('XF:Style');

        return $this->view('ThemeHouse\InstallAndUpgrade:Style\InstallUpgrade', 'th_iau_style_install_upgrade', [
            'products' => $products,
            'profiles' => $profiles,
            'styleTree' => $styleRepo->getStyleTree(false)
        ]);
    }

    /**
     * @return InstallAndUpgrade
     */
    protected function getInstallUpgradeRepo()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->repository('ThemeHouse\InstallAndUpgrade:InstallAndUpgrade');
    }

    /**
     * @return Redirect
     */
    public function actionThInstallUpgradeDismiss()
    {
        /** @var \ThemeHouse\InstallAndUpgrade\Repository\Profile $profileRepo */
        $profileRepo = XF::repository('ThemeHouse\InstallAndUpgrade:Profile');

        $profiles = $profileRepo
            ->findProfiles()
            ->where('last_error_messages', '!=', '[]')
            ->fetch();

        foreach ($profiles as $profile) {
            /** @var Profile $profile */
            $errorMessages = $profile->last_error_messages;

            if (!empty($errorMessages['styles'])) {
                unset($errorMessages['styles']);

                $profile->fastUpdate('last_error_messages', $errorMessages);
            }
        }

        return $this->redirect($this->buildLink('styles'));
    }

    /**
     * @throws XF\Mvc\Reply\Exception
     * @throws Exception
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
            'parent_style_id' => $this->filter('parent_style_id', 'uint'),
            'force' => $this->filter('force', 'bool')
        ]);
    }

    /**
     * @return Error|View
     * @throws Exception
     */
    public function actionThInstallUpgradeUrl()
    {
        $url = $this->filter('url', 'str');

        $repo = $this->getInstallUpgradeRepo();

        /** @var Profile $profile */
        $profile = $repo->getProfileFromUrl($url, $error);

        if (!$profile) {
            return $this->error($error);
        }

        $handler = $profile->getHandler();

        if (!$handler->getCapability('style')) {
            return $this->error(XF::phrase('th_installupgrade_provider_does_not_support_styles'));
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
            'url' => $this->filter('url', 'str'),
            'force' => $this->filter('force', 'bool')
        ]);
    }

    /**
     * @param ParameterBag $params
     * @return XF\Mvc\Reply\AbstractReply|View
     * @throws Exception
     */
    public function actionThInstallUpgradeUpdate(ParameterBag $params)
    {
        /** @var \ThemeHouse\InstallAndUpgrade\XF\Entity\Style $style */
        $style = $this->assertStyleExists($params['style_id']);
        $product = $style->THIAUProduct;

        if (!$product->Profile->getHandler()->compareVersions($style->th_iau_current_version,
                    $product->latest_version)) {
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
}