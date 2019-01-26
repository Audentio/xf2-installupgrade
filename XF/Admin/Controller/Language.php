<?php

namespace ThemeHouse\InstallAndUpgrade\XF\Admin\Controller;

use ThemeHouse\InstallAndUpgrade\Entity\Product;
use ThemeHouse\InstallAndUpgrade\Entity\ProductBatch;
use ThemeHouse\InstallAndUpgrade\Entity\Profile;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\AbstractHandler;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces\LanguageHandler;
use ThemeHouse\InstallAndUpgrade\Repository\InstallAndUpgrade;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\View;

class Language extends XFCP_Language
{
    /**
     * @param ParameterBag $params
     * @return View
     * @throws \Exception
     */
    public function actionThInstallUpgradeUpdate(ParameterBag $params)
    {
        /** @var \ThemeHouse\InstallAndUpgrade\XF\Entity\Language $language */
        $language = $this->assertLanguageExists($params->language_id);
        /** @var Product $product */
        $product = $language->THIAUProduct;

        if (!$product->update_available) {
            return $this->notFound();
        }

        if ($this->isPost()) {
            $handler = $product->Profile->getHandler();

            /** @var \ThemeHouse\InstallAndUpgrade\ControllerPlugin\Profile $controllerPlugin */
            $controllerPlugin = $this->plugin('ThemeHouse\InstallAndUpgrade:Profile');

            return $controllerPlugin->handleReply($handler, $product->Profile,
                function () use ($handler, $product, $language) {
                    /** @var ProductBatch $productBatch */
                    $productBatch = $this->em()->create('ThemeHouse\InstallAndUpgrade:ProductBatch');
                    /** @var LanguageHandler $handler */
                    $productBatch->addProduct($product, $handler->downloadLanguageProduct($product));
                    $productBatch->save();

                    return $this->redirect($this->buildLink('th-install-upgrade/install-products', $productBatch, [
                        'target' => 'overwrite',
                        'overwrite_language_id' => $language->language_id
                    ]));
                }, [
                    'target' => 'overwrite',
                    'overwrite_language_id' => $language->language_id
                ]);
        } else {
            $viewParams = [
                'language' => $language,
                'product' => $product
            ];

            return $this->view('ThemeHouse\InstallAndUpgrade:Language\Upgrade', 'th_iau_language_upgrade', $viewParams);
        }
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
            ->where('product_type', '=', 'language')
            ->fetch()->groupBy('profile_id');

        /** @var \XF\Repository\Language $languageRepo */
        $languageRepo = $this->repository('XF:Language');
        $languageTree = $languageRepo->getLanguageTree(false);

        return $this->view('ThemeHouse\InstallAndUpgrade:Style\InstallUpgrade', 'th_iau_language_install_upgrade', [
            'products' => $products,
            'profiles' => $profiles,
            'languageTree' => $languageTree
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

        if ($product->product_type != 'language') {
            return $this->error('th_installupgrade_selected_product_must_be_language');
        }

        /** @var \ThemeHouse\InstallAndUpgrade\ControllerPlugin\Profile $controllerPlugin */
        $controllerPlugin = $this->plugin('ThemeHouse\InstallAndUpgrade:Profile');

        return $controllerPlugin->handleReply($handler, $product->Profile, function () use ($handler, $product) {
            /** @var ProductBatch $productBatch */
            $productBatch = $this->em()->create('ThemeHouse\InstallAndUpgrade:ProductBatch');
            /** @var LanguageHandler $handler */
            $productBatch->addProduct($product, $handler->downloadLanguageProduct($product));
            $productBatch->save();

            return $this->redirect($this->buildLink('th-install-upgrade/install-products', $productBatch, [
                'target' => $this->filter('target', 'str'),
                'overwrite_language_id' => $this->filter('overwrite_language_id', 'uint'),
                'parent_language_id' => $this->filter('parent_language_id', 'uint')
            ]));
        }, [
            'target' => $this->filter('target', 'str'),
            'overwrite_language_id' => $this->filter('overwrite_language_id', 'uint'),
            'parent_language_id' => $this->filter('parent_language_id', 'uint')
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

        if (!$handler->getCapability('language')) {
            return $this->error(\XF::phrase('th_installupgrade_provider_does_not_support_languages'));
        }

        /** @var \ThemeHouse\InstallAndUpgrade\ControllerPlugin\Profile $controllerPlugin */
        $controllerPlugin = $this->plugin('ThemeHouse\InstallAndUpgrade:Profile');

        return $controllerPlugin->handleReply($handler, $profile, function () use ($handler, $url) {

            /** @var ProductBatch $productBatch */
            $productBatch = $this->em()->create('ThemeHouse\InstallAndUpgrade:ProductBatch');
            /** @var LanguageHandler $handler */
            $product = $handler->createLanguageProductFromUrl($url);
            $productBatch->addProduct($product, $handler->downloadLanguageProduct($product));
            $productBatch->save();

            return $this->redirect($this->buildLink('th-install-upgrade/install-products', $productBatch, [
                'target' => $this->filter('target', 'str'),
                'overwrite_language_id' => $this->filter('overwrite_language_id', 'uint'),
                'parent_language_id' => $this->filter('parent_language_id', 'uint')
            ]));
        }, [
            'target' => $this->filter('target', 'str'),
            'overwrite_language_id' => $this->filter('overwrite_language_id', 'uint'),
            'parent_language_id' => $this->filter('parent_language_id', 'uint'),
            'url' => $this->filter('url', 'str')
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
}