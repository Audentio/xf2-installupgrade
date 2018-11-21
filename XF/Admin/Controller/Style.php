<?php

namespace ThemeHouse\InstallAndUpgrade\XF\Admin\Controller;

use ThemeHouse\InstallAndUpgrade\Entity\Product;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\AbstractHandler;
use ThemeHouse\InstallAndUpgrade\Repository\Handler;
use XF\Mvc\Reply\View;
use XF\Util\File;
use XF\Util\Xml;

class Style extends XFCP_Style
{
    /**
     * @return View
     */
    public function actionIndex()
    {
        $response = parent::actionIndex();

        if ($response instanceof View) {
            $styles = $response->getParam('styleTree')->getFlattened();

            $updates = false;
            foreach ($styles as $style) {
                /** @var \XF\Entity\Style $style */
                if ($style['record']->getRelationOrDefault('THInstallUpgradeData')->update_available) {
                    $updates = true;
                    break;
                }
            }

            $response->setParam('th_iau_updates_available', $updates);
        }

        return $response;
    }

    /**
     * @param \XF\Entity\Style $style
     * @return \XF\Mvc\FormAction
     */
    protected function styleSaveProcess(\XF\Entity\Style $style)
    {
        $form = parent::styleSaveProcess($style);

        $installUpgradeData = $style->getRelationOrDefault('THInstallUpgradeData');

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
                    return $this->downloadStyle();

                case 'upload_zip':
                    /** @var Handler $repository */
                    $repository = $this->repository('ThemeHouse\InstallAndUpgrade:Handler');
                    /** @var \ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Upload $handler */
                    $handler = $repository->getHandlerByName('upload');

                    $files = $handler->installStyle();

                    if (!$files || empty($files)) {
                        return $this->error(\XF::phrase('th_iau_no_xml_files_found_in_package'));
                    }

                    return $this->redirect($this->buildLink('styles/import', null, ['files' => $files]),
                        \XF::phrase('th_iau_style_successfully_uploaded'));

                case 'upload':
                default:
                    return parent::actionImport();
            }

            /** @var \XF\Service\Style\Import $styleImporter */
            $styleImporter = $this->service('XF:Style\Import');

            try {
                $document = Xml::openFile($filePath);
            } catch (\Exception $e) {
                $document = null;
            }

            if (!$styleImporter->isValidXml($document, $error)) {
                return $this->error($error);
            }

            $input = $this->filter([
                'target' => 'str',
                'parent_style_id' => 'uint',
                'overwrite_style_id' => 'uint'
            ]);

            if ($input['target'] == 'overwrite') {
                /** @var \XF\Entity\Style $overwriteStyle */
                $overwriteStyle = $this->assertRecordExists('XF:Style', $input['overwrite_style_id']);
                $styleImporter->setOverwriteStyle($overwriteStyle);
            } else {
                $parentStyle = $input['parent_style_id']
                    ? $this->assertRecordExists('XF:Style', $input['parent_style_id'])
                    : null;
                $styleImporter->setParentStyle($parentStyle);
            }

            $style = $styleImporter->importFromXml($document);

            /** @var Product $product */
            $product = $this->em()->find('ThemeHouse\InstallAndUpgrade:Product', $this->filter([
                'profile_id' => 'uint',
                'product_id' => 'str'
            ]));

            $data = $style->getRelationOrDefault('THInstallUpgradeData');
            $data->extra = array_merge($data->extra, [
                'child' => strpos($file, 'child') !== false
            ]);

            if($product) {
                $product->Profile->handler->convertProductToData($product, $data);
            }
            else {
                $data->save();
            }

            return $this->redirect($this->buildLink('styles'));
        }

        $response = parent::actionImport();

        if (!$this->isPost() && $response instanceof View) {
            $response->setParam('files', $this->filter('files', 'array-array-str'));
            $response->setParam('style_id', $this->filter('style_id', 'uint'));
        }

        return $response;
    }

    /**
     * @throws \Exception
     */
    protected function downloadStyle()
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

        $parentStyleId = $this->filter('parent_style_id', 'int');

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
                            'parent_style_id' => $parentStyleId,
                            'file_type' => 'url',
                            'url' => $url,
                        ],
                        'return_url' => $this->buildLink('styles/import')
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
                        'parent_style_id' => $parentStyleId,
                        'file_type' => 'url',
                        'url' => $url,
                    ],
                    'return_url' => $this->buildLink('styles/import')
                ];
                return $this->view('ThemeHouse\InstallAndUpgrade:TFA', 'th_iau_tfa_key', $viewParams);
            }
        }

        $product = $handler->createProductFromUrl($url, 'style');

        if (!$product) {
            return $this->error(\XF::phrase('th_iau_provider_not_suported_product_from_url_or_url_invalid'));
        }

        if ($product instanceof \Exception) {
            return $this->error($product->getMessage());
        }

        $files = $handler->installStyle($product, $profile);

        if (!$files || empty($files)) {
            return $this->error(\XF::phrase('th_iau_no_xml_files_found_in_package'));
        }

        return $this->redirect($this->buildLink('styles/import', null, ['files' => $files, 'product' => $product]),
            \XF::phrase('th_iau_style_successfully_downloaded'));
    }

    /**
     * @return \XF\Mvc\Reply\Error|\XF\Mvc\Reply\Redirect|View
     * @throws \XF\PrintableException
     */
    public function actionBulkDelete()
    {
        if ($this->isPost()) {
            $styleIds = $this->filter('to_delete', 'array-int');
            $styles = $this->finder('XF:Style')->where('style_id', '=', $styleIds)->fetch();

            foreach ($styles as $style) {
                /** @var \XF\Entity\Style $style */
                if (!$style->preDelete()) {
                    return $this->error($style->getErrors());
                }
            }

            foreach ($styles as $style) {
                $style->delete();
            }

            return $this->redirect($this->buildLink('styles'));
        } else {
            $viewParams = [
                'styles' => $this->getStyleRepo()->getStyleTree()->getFlattened()
            ];

            return $this->view('ThemeHouse\InstallAndUpgrade:Style\BulkDelete', 'th_iau_style_bulk_delete',
                $viewParams);
        }
    }
}