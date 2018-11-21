<?php

namespace ThemeHouse\InstallAndUpgrade\XF\Admin\Controller;

use ThemeHouse\InstallAndUpgrade\Entity\Product;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\AbstractHandler;
use ThemeHouse\InstallAndUpgrade\Repository\Handler;
use XF\Mvc\Reply\View;
use XF\Util\File;
use XF\Util\Xml;

class Language extends XFCP_Language
{
    /**
     * @return View
     */
    public function actionIndex()
    {
        $response = parent::actionIndex();

        if ($response instanceof View) {
            $languages = $response->getParam('languageTree')->getFlattened();

            $updates = false;
            foreach ($languages as $language) {
                /** @var \XF\Entity\Language $language */
                if ($language['record']->getRelationOrDefault('THInstallUpgradeData')->update_available) {
                    $updates = true;
                    break;
                }
            }

            $response->setParam('th_iau_updates_available', $updates);
        }

        return $response;
    }

    /**
     * @param \XF\Entity\Language $language
     * @return \XF\Mvc\FormAction
     */
    protected function languageSaveProcess(\XF\Entity\Language $language)
    {
        $form = parent::languageSaveProcess($language);

        $installUpgradeData = $language->getRelationOrDefault('THInstallUpgradeData');

        $dataInput = $this->filter([
            'current_version' => 'str',
            'download_url' => 'str',
            'extra' => 'array-str'
        ]);

        $form->basicEntitySave($installUpgradeData, $dataInput);

        return $form;
    }

    /**
     * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|View
     * @throws \XF\Mvc\Reply\Exception
     * @throws \Exception
     */
    public function actionImport()
    {
        if ($this->isPost()) {

            switch ($this->filter('file_type', 'str')) {
                case 'local':
                    $file = $this->filter('file', 'str');

                    $abstractPath = 'internal-data://install-upgrade/xmls/' . $file;

                    if (!File::abstractedPathExists($abstractPath)) {
                        return $this->error(\XF::phrase('th_iau_file_x_not_found', ['file' => $abstractPath]));
                    }

                    $filePath = File::copyAbstractedPathToTempFile($abstractPath);
                    break;

                case 'url':
                    return $this->downloadLanguage();

                case 'upload_zip':
                    /** @var Handler $repository */
                    $repository = $this->repository('ThemeHouse\InstallAndUpgrade:Handler');
                    /** @var \ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Upload $handler */
                    $handler = $repository->getHandlerByName('upload');

                    $files = $handler->installLanguage();

                    if (!$files || empty($files)) {
                        return $this->error(\XF::phrase('th_iau_no_xml_files_found_in_package'));
                    }

                    return $this->redirect($this->buildLink('languages/import', null, ['files' => $files]),
                        \XF::phrase('th_iau_language_successfully_uploaded'));

                case 'upload':
                default:
                    return parent::actionImport();
            }

            /** @var \XF\Service\Language\Import $languageImporter */
            $languageImporter = $this->service('XF:Language\Import');

            try {
                $document = Xml::openFile($filePath);
            } catch (\Exception $e) {
                $document = null;
            }

            if (!$languageImporter->isValidXml($document, $error)) {
                return $this->error($error);
            }

            $input = $this->filter([
                'target' => 'str',
                'parent_language_id' => 'uint',
                'overwrite_language_id' => 'uint'
            ]);

            if ($input['target'] == 'overwrite') {
                /** @var \XF\Entity\Language $overwriteLanguage */
                $overwriteLanguage = $this->assertRecordExists('XF:Language', $input['overwrite_language_id']);
                $languageImporter->setOverwriteLanguage($overwriteLanguage);
            } else {
                $parentLanguage = $input['parent_language_id']
                    ? $this->assertRecordExists('XF:Language', $input['parent_language_id'])
                    : null;
                $languageImporter->setParentLanguage($parentLanguage);
            }

            $language = $languageImporter->importFromXml($document);

            /** @var Product $product */
            $product = $this->em()->find('ThemeHouse\InstallAndUpgrade:Product', $this->filter([
                'profile_id' => 'uint',
                'product_id' => 'str'
            ]));

            if($product) {
                $product->Profile->handler->convertProductToData($product, $language);
            }

            return $this->redirect($this->buildLink('languages'));
        }

        $response = parent::actionImport();

        if (!$this->isPost() && $response instanceof View) {
            $response->setParam('files', $this->filter('files', 'array-array-str'));
        }

        return $response;
    }

    /**
     * @throws \Exception
     */
    protected function downloadLanguage()
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
                            'parent_language_id' => $this->filter('parent_language_id', 'int'),
                            'file_type' => 'url',
                            'url' => $url,
                        ],
                        'return_url' => $this->buildLink('languages/import')
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
                        'parent_language_id' => $this->filter('parent_language_id', 'int'),
                        'file_type' => 'url',
                        'url' => $url,
                    ],
                    'return_url' => $this->buildLink('languages/import')
                ];
                return $this->view('ThemeHouse\InstallAndUpgrade:TFA', 'th_iau_tfa_key', $viewParams);
            }
        }

        $product = $handler->createProductFromUrl($url, 'language');

        if (!$product) {
            return $this->error(\XF::phrase('th_iau_provider_not_suported_product_from_url_or_url_invalid'));
        }

        if ($product instanceof \Exception) {
            return $this->error($product->getMessage());
        }

        $files = $handler->installLanguage($product, $profile);

        if (!$files || empty($files)) {
            return $this->error(\XF::phrase('th_iau_no_xml_files_found_in_package'));
        }

        return $this->redirect($this->buildLink('languages/import', null, ['files' => $files, 'product' => $product]),
            \XF::phrase('th_iau_language_successfully_downloaded'));
    }

    /**
     * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|View
     * @throws \XF\PrintableException
     */
    public function actionBulkDelete()
    {
        if ($this->isPost()) {
            $languageIds = $this->filter('to_delete', 'array-int');
            $languages = $this->finder('XF:Language')->where('language_id', '=', $languageIds)->fetch();

            foreach ($languages as $language) {
                /** @var \XF\Entity\Language $language */
                if (!$language->preDelete()) {
                    return $this->error($language->getErrors());
                }
            }

            foreach ($languages as $language) {
                $language->delete();
            }

            return $this->redirect($this->buildLink('languages'));
        } else {
            $viewParams = [
                'languages' => $this->getLanguageRepo()->getLanguageTree()->getFlattened()
            ];

            return $this->view('ThemeHouse\InstallAndUpgrade:Language\BulkDelete', 'th_iau_language_bulk_delete',
                $viewParams);
        }
    }
}