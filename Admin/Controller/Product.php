<?php

namespace ThemeHouse\InstallAndUpgrade\Admin\Controller;

use ThemeHouse\InstallAndUpgrade\Entity\Profile;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\AbstractHandler;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces\AddOnHandler;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces\EncryptCredentials;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces\ProductList;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces\StyleHandler;

use XF;
use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;
use XF\Mvc\Reply\Error;
use XF\Mvc\Reply\Exception;
use XF\Mvc\Reply\Redirect;
use XF\Mvc\Reply\View;
use XF\PrintableException;
use XF\Repository\AddOn;

/**
 * Class Product
 * @package ThemeHouse\InstallAndUpgrade\Admin\Controller
 */
class Product extends AbstractController
{
    /**
     * @return Redirect|View
     * @throws \Exception
     */
    public function actionRefresh()
    {
        $profiles = $this->finder('ThemeHouse\InstallAndUpgrade:Profile')
            ->where('active', '=', 1)->fetch();

        if ($this->isPost()) {
            $this->app->db()->emptyTable('xf_th_installupgrade_product');
            $secrets = $this->filter('secrets', 'array-str');

            foreach ($profiles as $profile) {
                if (isset($secrets[$profile->profile_id]) || !$profile->requires_decryption) {

                    if (isset($secrets[$profile->profile_id])) {
                        /** @var Profile|ProductList $profile */
                        $profile->setEncryptionSecret($secrets[$profile->profile_id]);
                    }

                    $profile->getProducts();
                }
            }

            return $this->redirect($this->buildLink('install-upgrade-products/available'));
        } else {
            $viewParams = [
                'profiles' => $profiles
            ];

            return $this->view('ThemeHouse\InstallAndUpgrade:Product\Refresh', 'th_iau_product_encryption_secrets',
                $viewParams);
        }
    }

    /**
     * @return View
     */
    public function actionAvailable()
    {
        $addOns = $this->finder('ThemeHouse\InstallAndUpgrade:Product')
            ->where('product_type', '=', 'addOn')
            ->order('title')
            ->fetch();
        $styles = $this->finder('ThemeHouse\InstallAndUpgrade:Product')
            ->where('product_type', '=', 'style')
            ->order('title')
            ->fetch();
        $languages = $this->finder('ThemeHouse\InstallAndUpgrade:Product')
            ->where('product_type', '=', 'language')
            ->order('title')
            ->fetch();

        $viewParams = [
            'addOns' => $addOns,
            'styles' => $styles,
            'languages' => $languages
        ];


        return $this->view('ThemeHouse\InstallAndUpgrade:Product\Available\List', 'th_iau_products_available_list',
            $viewParams);
    }

    /**
     * @return View
     */
    public function actionInstalled()
    {
        $addOns = $this->finder('ThemeHouse\InstallAndUpgrade:AddOn')
            ->where('addon_id', '<>', 'XF')
            ->with('AddOn', true)
            ->order('AddOn.title')
            ->fetch();
        $styles = $this->finder('ThemeHouse\InstallAndUpgrade:Style')
            ->with('Style', true)
            ->order('Style.title')
            ->fetch();
        $languages = $this->finder('ThemeHouse\InstallAndUpgrade:Language')
            ->with('Language', true)
            ->order('Language.title')
            ->fetch();

        $viewParams = [
            'addOns' => $addOns,
            'styles' => $styles,
            'languages' => $languages
        ];

        return $this->view('ThemeHouse\InstallAndUpgrade:Product\Installed\List', 'th_iau_products_installed_list',
            $viewParams);
    }

    /**
     * @return XF\Mvc\Reply\AbstractReply|Redirect|View
     * @throws PrintableException
     * @throws PrintableException
     * @throws PrintableException
     */
    public function actionInstalledAdd()
    {
        if ($this->isPost()) {
            $contentType = $this->filter('content_type', 'str');
            $contentIds = $this->filter('content_id', 'array-str');

            switch ($contentType) {
                case 'add-on':
                    $content = $this->em()->find('XF:AddOn', $contentIds['add-on']);
                    break;

                case 'style':
                    $content = $this->em()->find('XF:Style', $contentIds['style']);
                    break;

                case 'language':
                    $content = $this->em()->find('XF:Language', $contentIds['language']);
                    break;

                default:
                    return $this->notFound();
            }

            /** @var \ThemeHouse\InstallAndUpgrade\Entity\Product $data */
            $data = $content->getRelationOrDefault('THInstallUpgradeData');
            $extra = $data->extra;
            $extra['download_url'] = $this->filter('download_url', 'str');
            $data->extra = $extra;
            $data->save();

            return $this->redirect($this->buildLink('install-upgrade-products/installed'));

        } else {
            $addOns = $this->finder('XF:AddOn')
                ->with('THInstallUpgradeData')
                ->order('title')
                ->fetch();

            $styles = $this->finder('XF:Style')
                ->with('THInstallUpgradeData')
                ->order('title')
                ->fetch();

            $languages = $this->finder('XF:Language')
                ->with('THInstallUpgradeData')
                ->order('title')
                ->fetch();

            $viewParams = [
                'addOns' => $addOns,
                'styles' => $styles,
                'languages' => $languages
            ];

            return $this->view('ThemeHouse\InstallAndUpgrade:Product\Installed\Add', 'th_iau_products_installed_add',
                $viewParams);
        }
    }

    /**
     * @return View
     */
    public function actionAddOn()
    {
        $addOns = $this->finder('ThemeHouse\InstallAndUpgrade:Product')
            ->where('product_type', '=', 'addOn')
            ->order('title')
            ->fetch()
            ->groupBy('profile_id');

        $profiles = $this->finder('ThemeHouse\InstallAndUpgrade:Profile')
            ->where('profile_id', '=', array_keys($addOns))
            ->order('page_title')
            ->fetch();

        $viewParams = [
            'addOns' => $addOns,
            'profiles' => $profiles
        ];

        return $this->view('ThemeHouse\InstallAndUpgrade:Product\AddOn\List', 'th_iau_products_addon_list',
            $viewParams);
    }

    /**
     * @param ParameterBag $params
     *
     * @return Error|Redirect|View
     * @throws \Exception
     */
    public function actionAddOnInstall(ParameterBag $params)
    {
        /** @var Profile $profile */
        $profile = $this->em()->find('ThemeHouse\InstallAndUpgrade:Profile', $params['profile_id']);

        /** @var AbstractHandler|EncryptCredentials|AddOnHandler $handler */
        $handler = $profile->getHandler();
        if (!$handler) {
            return $this->error(XF::phrase('th_iau_no_handler_found_for_x', ['item' => XF::phrase('add_on')]));
        }

        $encryptionSecret = null;
        if ($profile->requires_decryption) {
            if (isset(XF::config('installAndUpgrade')['secrets'][$profile->profile_id])) {
                $encryptionSecret = XF::config('installAndUpgrade')['secrets'][$profile->profile_id];
                $handler->setEncryptionSecret($encryptionSecret);
            } else {
                if ($encryptionSecret = $this->filter('encryption_secret', 'str')) {
                    $handler->setEncryptionSecret($encryptionSecret);
                } else {
                    $viewParams = [
                        'profile' => $profile,
                        'extra' => [],
                        'return_url' => $this->buildLink('install-upgrade-products/add-ons/install', [
                            'profile_id' => $params['profile_id'],
                            'product_id' => $params['product_id']
                        ])
                    ];

                    return $this->view('ThemeHouse\InstallAndUpgrade:Decrypt', 'th_iau_encryption_secret', $viewParams);
                }
            }
        }

        /** @var \ThemeHouse\InstallAndUpgrade\Entity\Product $product */
        $product = $this->em()->find('ThemeHouse\InstallAndUpgrade:Product',
            [$profile->profile_id, $params['product_id']]);
        $addonId = $handler->installAddOnProduct($product);

        if (!$addonId) {
            return $this->error(XF::phrase('th_iau_addon_id_not_found_in_package'));
        }

        return $this->redirect($this->buildLink('add-ons/install',
            ['addon_id_url' => str_replace('/', '-', $addonId), 'product' => $product]),
            XF::phrase('th_iau_addon_downloaded_successfully'));
    }

    /**
     * @param ParameterBag $params
     * @return Redirect|View
     * @throws Exception
     * @throws PrintableException
     */
    public function actionAddOnEdit(ParameterBag $params)
    {
        /** @var AddOn $repo */
        $repo = XF::repository('XF:AddOn');
        $addOnId = $repo->convertAddOnIdUrlVersionToBase($params['product_id']);
        $addOn = $this->assertRecordExists('ThemeHouse\InstallAndUpgrade:AddOn', $addOnId, ['AddOn']);

        if ($this->isPost()) {
            $addOn->bulkSet($this->filter([
                'download_url' => 'str',
                'update_check' => 'bool'
            ]));
            $addOn->save();

            return $this->redirect($this->buildLink('install-upgrade-products/installed'));
        } else {
            $viewParams = [
                'addOn' => $addOn
            ];

            return $this->view('ThemeHouse\InstallAndUpgrade:Product\AddOn\Edit', 'th_iau_product_addOn_edit',
                $viewParams);
        }
    }

    /**
     * @return View
     */
    public function actionStyle()
    {
        $styles = $this->finder('ThemeHouse\InstallAndUpgrade:Product')
            ->where('product_type', '=', 'style')
            ->order('title')
            ->fetch()
            ->groupBy('profile_id');

        $profiles = $this->finder('ThemeHouse\InstallAndUpgrade:Profile')
            ->where('profile_id', '=', array_keys($styles))
            ->order('page_title')
            ->fetch();

        $viewParams = [
            'styles' => $styles,
            'profiles' => $profiles
        ];

        return $this->view('ThemeHouse\InstallAndUpgrade:Product\Style\List', 'th_iau_products_style_list',
            $viewParams);
    }

    /**
     * @param ParameterBag $params
     * @return Error|Redirect|View
     * @throws \Exception
     */
    public function actionStyleInstall(ParameterBag $params)
    {
        /** @var Profile $profile */
        $profile = $this->em()->find('ThemeHouse\InstallAndUpgrade:Profile', $params['profile_id']);

        /** @var AbstractHandler|EncryptCredentials|StyleHandler $handler */
        $handler = $profile->getHandler();
        if (!$handler) {
            return $this->error(XF::phrase('th_iau_no_handler_found_for_x', ['item' => XF::phrase('style')]));
        }

        $encryptionSecret = null;
        if ($profile->requires_decryption) {
            if (isset(XF::config('installAndUpgrade')['secrets'][$profile->profile_id])) {
                $encryptionSecret = XF::config('installAndUpgrade')['secrets'][$profile->profile_id];
                $handler->setEncryptionSecret($encryptionSecret);
            } else {
                if ($encryptionSecret = $this->filter('encryption_secret', 'str')) {
                    $handler->setEncryptionSecret($encryptionSecret);
                } else {
                    $viewParams = [
                        'profile' => $profile,
                        'extra' => [],
                        'return_url' => $this->buildLink('install-upgrade-products/styles/install', [
                            'profile_id' => $params['profile_id'],
                            'product_id' => $params['product_id']
                        ])
                    ];

                    return $this->view('ThemeHouse\InstallAndUpgrade:Decrypt', 'th_iau_encryption_secret', $viewParams);
                }
            }
        }

        $product = $this->em()->find('ThemeHouse\InstallAndUpgrade:Product',
            [$profile->profile_id, $params['product_id']]);
        $files = $handler->installStyle($product, $profile);

        if (!$files || empty($files)) {
            return $this->error(XF::phrase('th_iau_no_xml_files_found_in_package'));
        }

        return $this->redirect($this->buildLink('styles/import', null, ['files' => $files, 'product' => $product]),
            XF::phrase('th_iau_style_successfully_downloaded'));
    }

    /**
     * @param ParameterBag $params
     * @return Redirect|View
     * @throws Exception
     * @throws PrintableException
     */
    public function actionStyleEdit(ParameterBag $params)
    {
        $style = $this->assertRecordExists('ThemeHouse\InstallAndUpgrade:Style', $params['profile_id'], ['Style']);

        if ($this->isPost()) {
            $style->bulkSet($this->filter([
                'download_url' => 'str',
                'update_check' => 'bool'
            ]));
            $style->save();

            return $this->redirect($this->buildLink('install-upgrade-products/installed'));
        } else {
            $viewParams = [
                'style' => $style
            ];

            return $this->view('ThemeHouse\InstallAndUpgrade:Product\Style\Edit', 'th_iau_product_style_edit',
                $viewParams);
        }
    }

    /**
     * @return View
     */
    public function actionLanguage()
    {
        $languages = $this->finder('ThemeHouse\InstallAndUpgrade:Product')
            ->where('product_type', '=', 'language')
            ->order('title')
            ->fetch()
            ->groupBy('profile_id');

        $profiles = $this->finder('ThemeHouse\InstallAndUpgrade:Profile')
            ->where('profile_id', '=', array_keys($languages))
            ->order('page_title')
            ->fetch();

        $viewParams = [
            'languages' => $languages,
            'profiles' => $profiles
        ];

        return $this->view('ThemeHouse\InstallAndUpgrade:Product\Language\List', 'th_iau_products_language_list',
            $viewParams);
    }

    /**
     * @param ParameterBag $params
     * @return Error|Redirect|View
     * @throws \Exception
     */
    public function actionLanguageInstall(ParameterBag $params)
    {
        /** @var Profile $profile */
        $profile = $this->em()->find('ThemeHouse\InstallAndUpgrade:Profile', $params['profile_id']);

        /** @var AbstractHandler|EncryptCredentials $handler */
        $handler = $profile->getHandler();

        if (!$handler) {
            return $this->error(XF::phrase('th_iau_no_handler_found_for_x', ['item' => XF::phrase('language')]));
        }

        $encryptionSecret = null;
        if ($profile->requires_decryption) {
            if (isset(XF::config('installAndUpgrade')['secrets'][$profile->profile_id])) {
                $encryptionSecret = XF::config('installAndUpgrade')['secrets'][$profile->profile_id];
                $handler->setEncryptionSecret($encryptionSecret);
            } else {
                if ($encryptionSecret = $this->filter('encryption_secret', 'str')) {
                    $handler->setEncryptionSecret($encryptionSecret);
                } else {
                    $viewParams = [
                        'profile' => $profile,
                        'extra' => [],
                        'return_url' => $this->buildLink('install-upgrade-products/languages/install', [
                            'profile_id' => $params['profile_id'],
                            'product_id' => $params['product_id']
                        ])
                    ];

                    return $this->view('ThemeHouse\InstallAndUpgrade:Decrypt', 'th_iau_encryption_secret', $viewParams);
                }
            }
        }


        $product = $this->em()->find('ThemeHouse\InstallAndUpgrade:Product',
            [$profile->profile_id, $params['product_id']]);

        $files = $handler->installLanguage($product, $profile);

        if (!$files || empty($files)) {
            return $this->error(XF::phrase('th_iau_no_xml_files_found_in_package'));
        }

        return $this->redirect($this->buildLink('languages/import', null, ['files' => $files, 'product' => $product]),
            XF::phrase('th_iau_language_successfully_downloaded'));
    }

    /**
     * @param ParameterBag $params
     * @return Redirect|View
     * @throws Exception
     * @throws PrintableException
     */
    public function actionLanguageEdit(ParameterBag $params)
    {
        $language = $this->assertRecordExists('ThemeHouse\InstallAndUpgrade:Language', $params['profile_id'],
            ['Language']);

        if ($this->isPost()) {
            $language->bulkSet($this->filter([
                'download_url' => 'str',
                'update_check' => 'bool'
            ]));
            $language->save();

            return $this->redirect($this->buildLink('install-upgrade-products/installed'));
        } else {
            $viewParams = [
                'language' => $language
            ];

            return $this->view('ThemeHouse\InstallAndUpgrade:Product\Language\Edit', 'th_iau_product_language_edit',
                $viewParams);
        }
    }
}