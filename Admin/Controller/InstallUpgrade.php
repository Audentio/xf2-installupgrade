<?php

namespace ThemeHouse\InstallAndUpgrade\Admin\Controller;

use ThemeHouse\InstallAndUpgrade\Entity\AddOn;
use ThemeHouse\InstallAndUpgrade\Entity\Language;
use ThemeHouse\InstallAndUpgrade\Entity\Style;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\AbstractHandler;
use ThemeHouse\InstallAndUpgrade\Repository\Handler;
use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;

class InstallUpgrade extends AbstractController
{
    /**
     * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
     * @throws \Exception
     */
    public function actionAddOn()
    {
        if ($this->isPost()) {
            switch ($this->filter('file_type', 'str')) {
                case 'url':
                    return $this->downloadAddOn();

                case 'upload_zip':
                    /** @var Handler $repository */
                    $repository = $this->repository('ThemeHouse\InstallAndUpgrade:Handler');
                    /** @var \ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Upload $handler */
                    $handler = $repository->getHandlerByName('upload');
                    $addonId = $handler->installAddOn();

                    if (!$addonId) {
                        return $this->error(\XF::phrase('th_iau_addon_id_not_found_in_package'));
                    }

                    $addOn = $this->em()->find('XF:AddOn', $addonId);

                    if (!$addOn) {
                        return $this->redirect($this->buildLink('add-ons/install',
                            ['addon_id_url' => str_replace('/', '-', $addonId)]),
                            \XF::phrase('th_iau_addon_uploaded_successfully'));
                    } else {
                        return $this->redirect($this->buildLink('add-ons/upgrade', $addOn),
                            \XF::phrase('th_iau_addon_uploaded_successfully'));
                    }

                default:
                    return $this->notFound();
            }
        } else {
            return $this->view('ThemeHouse\InstallAndUpgrade:AddOn\Install', 'th_iau_addon_install', []);
        }
    }

    /**
     * @throws \Exception
     */
    protected function downloadAddOn()
    {
        $url = $this->filter('url', 'str');

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return $this->error('No valid URL entered.');
        }

        /** @var Handler $repository */
        $repository = $this->repository('ThemeHouse\InstallAndUpgrade:Handler');

        /** @var AbstractHandler $handler */
        $handler = $repository->getHandler('url', $url);

        if (!$handler) {
            return $this->error(\XF::phrase('th_iau_no_handler_found_for_x', ['item' => $url]));
        }

        $profile = $handler->getProfileForUrl($url);

        if (!$profile) {
            return $this->error(\XF::phrase('th_iau_no_profile_found_for_x', ['item' => $url]));
        }

        $encryptionSecret = null;
        if ($profile->requires_decryption) {
            if (isset(\XF::config('installAndUpgrade')['secrets'][$profile->profile_id])) {
                $encryptionSecret = \XF::config('installAndUpgrade')['secrets'][$profile->profile_id];
                $handler->setEncryptionSecret($encryptionSecret);
            } else {
                if ($encryptionSecret = $this->filter('encryption_secret', 'str')) {
                    $handler->setEncryptionSecret($encryptionSecret);
                } else {
                    $viewParams = [
                        'profile' => $profile,
                        'extra' => [
                            'file_type' => 'url',
                            'url' => $url,
                        ],
                        'return_url' => $this->buildLink('install-upgrade/add-on')
                    ];
                    return $this->view('ThemeHouse\InstallAndUpgrade:Decrypt', 'th_iau_encryption_secret', $viewParams);
                }
            }
        }

        if ($profile->has_tfa) {
            if ($key = $this->filter('tfa_key', 'str')) {
                $handler->setTfaKey($key);
            } else {
                $viewParams = [
                    'profile' => $profile,
                    'encryptionSecret' => $encryptionSecret,
                    'extra' => [
                        'file_type' => 'url',
                        'url' => $url
                    ],
                    'return_url' => $this->buildLink('install-upgrade/add-on')
                ];
                return $this->view('ThemeHouse\InstallAndUpgrade:TFA', 'th_iau_tfa_key', $viewParams);
            }
        }

        $product = $handler->createProductFromUrl($url, 'addOn');

        if (!$product) {
            return $this->error(\XF::phrase('th_iau_provider_not_suported_product_from_url_or_url_invalid'));
        }

        if ($product instanceof \Exception) {
            return $this->error($product->getMessage());
        }

        $addonId = $handler->installAddOn($product, $profile);

        if (!$addonId) {
            return $this->error(\XF::phrase('th_iau_addon_id_not_found_in_package'));
        }

        /** @var \XF\Repository\AddOn $addonRepo */
        $addonRepo = $this->repository('XF:AddOn');

        return $this->redirect($this->buildLink('add-ons/install', [
            'addon_id_url' => $addonRepo->convertAddOnIdToUrlVersion($addonId)
        ], [
            'profile_id' => $product->profile_id,
            'product_id' => $product->product_id
        ]),
            \XF::phrase('th_iau_addon_downloaded_successfully'));
    }

    /**
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
     * @throws \Exception
     */
    public function actionUpdate(ParameterBag $params)
    {
        switch ($params->content_type) {
            case 'language':
                /** @var Language $language */
                $language = $this->em()->find('ThemeHouse\InstallAndUpgrade:Language',
                    $params->content_id);

                if (!$language) {
                    return $this->error(\XF::phrase('th_iau_language_not_found'));
                }

                $handler = $language->handler;

                if (!$handler) {
                    return $this->error(\XF::phrase('th_iau_no_handler_found_for_x',
                        ['item' => \XF::phrase('language')]));
                }

                $profile = $language->getProfile();

                $encryptionSecret = null;
                if ($profile->requires_decryption) {
                    if (isset(\XF::config('installAndUpgrade')['secrets'][$profile->profile_id])) {
                        $encryptionSecret = \XF::config('installAndUpgrade')['secrets'][$profile->profile_id];
                        $handler->setEncryptionSecret($encryptionSecret);
                    } else {
                        if ($encryptionSecret = $this->filter('encryption_secret', 'str')) {
                            $handler->setEncryptionSecret($encryptionSecret);
                        } else {
                            $viewParams = [
                                'profile' => $profile,
                                'encryptionSecret' => $encryptionSecret,
                                'return_url' => $this->buildLink('install-upgrade/update',
                                    ['content_type' => 'language', 'content_id' => $language->language_id])
                            ];
                            return $this->view('ThemeHouse\InstallAndUpgrade:Decrypt', 'th_iau_encryption_secret',
                                $viewParams);
                        }
                    }
                }

                if ($profile->has_tfa) {
                    if ($key = $this->filter('tfa_key', 'str')) {
                        $handler->setTfaKey($key);
                    } else {
                        $viewParams = [
                            'profile' => $profile,
                            'encryptionSecret' => $encryptionSecret,
                            'return_url' => $this->buildLink('install-upgrade/update',
                                ['content_type' => 'language', 'content_id' => $language->language_id])
                        ];
                        return $this->view('ThemeHouse\InstallAndUpgrade:TFA', 'th_iau_tfa_key', $viewParams);
                    }
                }

                $xmls = $handler->upgradeLanguage($language);

                return $this->redirect($this->buildLink('languages/import'), null,
                    ['files' => $xmls, 'style_id' => $language->language_id]);

            case 'addOn':
                /** @var \XF\Repository\AddOn $repo */
                $repo = \XF::repository('XF:AddOn');
                $addonId = $repo->convertAddOnIdUrlVersionToBase($params->content_id);
                /** @var AddOn $addOn */
                $addOn = $this->em()->find('ThemeHouse\InstallAndUpgrade:AddOn',
                    $addonId, ['AddOn']);

                $handler = $addOn->handler;

                if (!$handler) {
                    return $this->error(\XF::phrase('th_iau_no_handler_found_for_x',
                        ['item' => \XF::phrase('add_on')]));
                }

                $profile = $addOn->getProfile();

                $encryptionSecret = null;
                if ($profile->requires_decryption) {
                    if (isset(\XF::config('installAndUpgrade')['secrets'][$profile->profile_id])) {
                        $encryptionSecret = \XF::config('installAndUpgrade')['secrets'][$profile->profile_id];
                        $handler->setEncryptionSecret($encryptionSecret);
                    } else {
                        if ($encryptionSecret = $this->filter('encryption_secret', 'str')) {
                            $handler->setEncryptionSecret($encryptionSecret);
                        } else {
                            $viewParams = [
                                'profile' => $profile,
                                'return_url' => $this->buildLink('install-upgrade/update',
                                    ['content_type' => 'addOn', 'content_id' => $addOn->addon_id_url])
                            ];
                            return $this->view('ThemeHouse\InstallAndUpgrade:Decrypt', 'th_iau_encryption_secret',
                                $viewParams);
                        }
                    }
                }

                if ($profile->has_tfa) {
                    if ($key = $this->filter('tfa_key', 'str')) {
                        $handler->setTfaKey($key);
                    } else {
                        $viewParams = [
                            'profile' => $profile,
                            'encryptionSecret' => $encryptionSecret,
                            'return_url' => $this->buildLink('install-upgrade/update',
                                ['content_type' => 'addOn', 'content_id' => $addOn->addon_id_url])
                        ];
                        return $this->view('ThemeHouse\InstallAndUpgrade:TFA', 'th_iau_tfa_key', $viewParams);
                    }
                }

                $handler->upgradeAddOn($addOn);

                if (!$addOn) {
                    return $this->error(\XF::phrase('th_iau_addon_not_found'));
                }


                return $this->redirect($this->buildLink('add-ons/upgrade', $addOn->AddOn));

            case 'style':
                /** @var Style $style */
                $style = $this->em()->find('ThemeHouse\InstallAndUpgrade:Style',
                    $params->content_id);

                if (!$style) {
                    return $this->error(\XF::phrase('th_iau_style_not_found'));
                }

                $handler = $style->handler;

                if (!$handler) {
                    return $this->error(\XF::phrase('th_iau_no_handler_found_for_x', ['item' => \XF::phrase('style')]));
                }

                $profile = $style->getProfile();

                $encryptionSecret = null;
                if ($profile->requires_decryption) {
                    if (isset(\XF::config('installAndUpgrade')['secrets'][$profile->profile_id])) {
                        $encryptionSecret = \XF::config('installAndUpgrade')['secrets'][$profile->profile_id];
                        $handler->setEncryptionSecret($encryptionSecret);
                    } else {
                        if ($encryptionSecret = $this->filter('encryption_secret', 'str')) {
                            $handler->setEncryptionSecret($encryptionSecret);
                        } else {
                            $viewParams = [
                                'profile' => $profile,
                                'return_url' => $this->buildLink('install-upgrade/update',
                                    ['content_type' => 'style', 'content_id' => $style->style_id])
                            ];
                            return $this->view('ThemeHouse\InstallAndUpgrade:Decrypt', 'th_iau_encryption_secret',
                                $viewParams);
                        }
                    }
                }

                if ($profile->has_tfa) {
                    if ($key = $this->filter('tfa_key', 'str')) {
                        $handler->setTfaKey($key);
                    } else {
                        $viewParams = [
                            'profile' => $profile,
                            'encryptionSecret' => $encryptionSecret,
                            'return_url' => $this->buildLink('install-upgrade/update',
                                ['content_type' => 'style', 'content_id' => $style->style_id])
                        ];
                        return $this->view('ThemeHouse\InstallAndUpgrade:TFA', 'th_iau_tfa_key', $viewParams);
                    }
                }

                $xmls = $handler->upgradeStyle($style);

                return $this->redirect($this->buildLink('styles/import'), null,
                    ['files' => $xmls, 'style_id' => $style->style_id]);

            default:
                return $this->error(\XF::phrase('th_iau_content_type_not_supported'));
        }
    }

    public function actionUpdateCheck(ParameterBag $params)
    {
        switch ($params->content_type) {
            case 'language':
                $language = $this->em()->find('XF:Language', $params->content_id);

                if (!$language) {
                    return $this->error(\XF::phrase('th_iau_language_not_found'));
                }

                /** @var Language $installUpgradeDataLanguage */
                $installUpgradeDataLanguage = $language->getRelationOrDefault('THInstallUpgradeData');

                $handler = $installUpgradeDataLanguage->handler;

                if (!$handler) {
                    return $this->error(\XF::phrase('th_iau_no_handler_found_for_x',
                        ['item' => \XF::phrase('language')]));
                }

                $handler->checkLanguageForUpdates($installUpgradeDataLanguage);
                return $this->redirect($this->buildLink('languages'));

            case 'addOn':
                /** @var \XF\Repository\AddOn $repo */
                $repo = \XF::repository('XF:AddOn');
                $addonId = $repo->convertAddOnIdUrlVersionToBase($params->content_id);
                $addOn = $this->em()->find('XF:AddOn', $addonId);

                if (!$addOn) {
                    return $this->error(\XF::phrase('th_iau_addon_not_found'),
                        \XF::phrase('th_iau_update_check_complete'));
                }

                /** @var AddOn $installUpgradeDataAddOn */
                $installUpgradeDataAddOn = $addOn->getRelationOrDefault('THInstallUpgradeData');

                $handler = $installUpgradeDataAddOn->handler;

                if (!$handler) {
                    return $this->error(\XF::phrase('th_iau_no_handler_found_for_x',
                        ['item' => \XF::phrase('add_on')]));
                }

                $handler->checkAddOnForUpdates($installUpgradeDataAddOn);
                return $this->redirect($this->buildLink('add-ons'), \XF::phrase('th_iau_update_check_complete'));

            case 'style':
                $style = $this->em()->find('XF:Style', $params->content_id);

                if (!$style) {
                    return $this->error(\XF::phrase('th_iau_style_not_found'));
                }

                /** @var Style $installUpgradeDataStyle */
                $installUpgradeDataStyle = $style->getRelationOrDefault('THInstallUpgradeData');

                $handler = $installUpgradeDataStyle->handler;

                if (!$handler) {
                    return $this->error(\XF::phrase('th_iau_no_handler_found_for_x', ['item' => \XF::phrase('style')]));
                }

                $handler->checkStyleForUpdates($installUpgradeDataStyle);
                return $this->redirect($this->buildLink('styles'), \XF::phrase('th_iau_update_check_complete'));

            default:
                return $this->error(\XF::phrase('Content type not supported.'));
        }
    }

    public function actionUpdateCheckAll(ParameterBag $params)
    {
        switch ($params->content_type) {
            case 'language':
                $finder = \XF::finder('ThemeHouse\InstallAndUpgrade:Language');
                $finder->where('update_check', '=', 1);
                $languages = $finder->fetch();

                foreach ($languages as $language) {
                    /** @var Language $language */
                    $handler = $language->handler;
                    if (!$handler) {
                        continue;
                    }
                    $handler->checkLanguageForUpdates($language);

                }
                return $this->redirect($this->buildLink('languages'), \XF::phrase('th_iau_update_check_complete'));

            case 'addOn':
                $finder = \XF::finder('ThemeHouse\InstallAndUpgrade:AddOn');
                $finder->where('update_check', '=', 1);
                $addOns = $finder->fetch();

                foreach ($addOns as $addOn) {
                    /** @var AddOn $addOn */
                    $handler = $addOn->handler;
                    if (!$handler) {
                        continue;
                    }
                    $handler->checkAddOnForUpdates($addOn);
                }
                return $this->redirect($this->buildLink('add-ons'), \XF::phrase('th_iau_update_check_complete'));

            case 'style':
                $finder = \XF::finder('ThemeHouse\InstallAndUpgrade:Style');
                $finder->where('update_check', '=', 1);
                $styles = $finder->fetch();

                foreach ($styles as $style) {
                    /** @var Style $style */
                    $handler = $style->handler;
                    if (!$handler) {
                        continue;
                    }
                    $handler->checkStyleForUpdates($style);
                }
                return $this->redirect($this->buildLink('styles'), \XF::phrase('th_iau_update_check_complete'));

            default:
                return $this->error(\XF::phrase('Content type not supported.'));
        }
    }
}