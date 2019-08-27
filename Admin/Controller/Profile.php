<?php

namespace ThemeHouse\InstallAndUpgrade\Admin\Controller;

use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\AbstractHandler;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces\EncryptCredentials;
use XF\Admin\Controller\AbstractController;
use XF\Mvc\ParameterBag;

/**
 * Class Profile
 * @package ThemeHouse\InstallAndUpgrade\Admin\Controller
 */
class Profile extends AbstractController
{
    /**
     * @return \XF\Mvc\Reply\View
     * @throws \Exception
     */
    public function actionIndex()
    {
        $profileRepo = $this->getProfileRepository();
        $profileRepo->getHandlers();

        $profileFinder = $this->finder('ThemeHouse\InstallAndUpgrade:Profile');
        $totals = $profileFinder->total();
        $profiles = $profileFinder->fetch();

        $viewParams = [
            'profiles' => $profiles,
            'profileTotal' => $totals
        ];

        return $this->view('ThemeHouse\InstallAndUpgrade:Profile\List', 'th_iau_profiles_list', $viewParams);
    }

    /**
     * @return \ThemeHouse\InstallAndUpgrade\Repository\Profile
     */
    protected function getProfileRepository()
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->repository('ThemeHouse\InstallAndUpgrade:Profile');
    }

    /**
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\View
     * @throws \Exception
     */
    public function actionAdd(ParameterBag $params)
    {
        $profileRepo = $this->getProfileRepository();

        if ($this->isPost()) {
            $key = $this->filter('provider_id', 'str');

            /** @var \ThemeHouse\InstallAndUpgrade\Entity\Profile $profile */
            $profile = $this->em()->create('ThemeHouse\InstallAndUpgrade:Profile');
            $profile->provider_id = $key;

            $handler = $profileRepo->getHandler($key);
            $profile->bulkSet($handler->getProfileDefaultOptions());

            return $this->profileAddEdit($profile);
        } else {
            $providers = $profileRepo->getHandlers();

            $profileRepo = $this->getProfileRepository();
            $profiles = $profileRepo->findProfiles()->fetch();

            foreach ($profiles as $profile) {
                /** @var \ThemeHouse\InstallAndUpgrade\Entity\Profile $profile */
                $handler = $providers[$profile->provider_id];
                /** @var AbstractHandler $handler */
                if (!$handler->getCapability('multiple')) {
                    unset($providers[$profile->provider_id]);
                }
            }

            $viewParams = [
                'providers' => $providers
            ];

            return $this->view('ThemeHouse\InstallAndUpgrade:Profile\Add', 'th_iau_profiles_add', $viewParams);
        }
    }

    /**
     * @param \ThemeHouse\InstallAndUpgrade\Entity\Profile $profile
     * @return \XF\Mvc\Reply\View
     * @throws \Exception
     */
    protected function profileAddEdit(\ThemeHouse\InstallAndUpgrade\Entity\Profile $profile)
    {
        $viewParams = [
            'profile' => $profile,
            'handler' => $profile->getHandler()
        ];

        return $this->view('ThemeHouse\InstallAndUpgrae:Profile\Edit', 'th_iau_profiles_edit', $viewParams);
    }

    /**
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\View
     * @throws \XF\Mvc\Reply\Exception
     * @throws \Exception
     */
    public function actionEdit(ParameterBag $params)
    {
        $profile = $this->assertProfileExists($params['profile_id']);
        return $this->profileAddEdit($profile);
    }

    /**
     * @param $id
     * @param null $with
     * @param null $phraseKey
     * @return \ThemeHouse\InstallAndUpgrade\Entity\Profile
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function assertProfileExists($id, $with = null, $phraseKey = null)
    {
        /** @noinspection PhpIncompatibleReturnTypeInspection */
        return $this->assertRecordExists('ThemeHouse\InstallAndUpgrade:Profile', $id, $with, $phraseKey);
    }

    /**
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\Redirect
     * @throws \XF\Mvc\Reply\Exception
     * @throws \XF\PrintableException
     * @throws \Throwable
     */
    public function actionSave(ParameterBag $params)
    {
        if ($params['profile_id']) {
            $profile = $this->assertProfileExists($params['profile_id']);
        } else {
            /** @var \ThemeHouse\InstallAndUpgrade\Entity\Profile $profile */
            $profile = $this->em()->create('ThemeHouse\InstallAndUpgrade:Profile');
        }

        $this->profileSaveProcess($profile)->run();

        return $this->redirect($this->buildLink('th-install-upgrade-profiles'));
    }

    /**
     * @param \ThemeHouse\InstallAndUpgrade\Entity\Profile $profile
     * @return \XF\Mvc\FormAction
     * @throws \Exception
     * @throws \Throwable
     */
    protected function profileSaveProcess(\ThemeHouse\InstallAndUpgrade\Entity\Profile $profile)
    {
        $form = $this->formAction();

        $input = $this->filter([
            'provider_id' => 'str',
            'base_url' => 'str',
            'page_title' => 'str',
            'has_tfa' => 'bool',
            'options' => 'array-str',
        ]);

        $profileRepo = $this->getProfileRepository();

        $handler = $profileRepo->getHandler($input['provider_id']);

        $result = $handler->verifyOptions($input['options']);

        if ($result !== true) {
            if ($result instanceof \Throwable) {
                throw $result;
            } else {
                if ($result) {
                    $form->logError($result);
                } else {
                    $form->logError(\XF::phrase('th_iau_authentication_failed'));
                }
            }
        }

        if ($handler->getCapability('encryptCredentials')) {
            /** @var EncryptCredentials $handler */
            $handler->setEncryptionSecret($this->filter('encryption_secret', 'str'));
            $input['options'] = $handler->encryptCredentials($input['options']);
        }

        $form->basicEntitySave($profile, $input);

        return $form;
    }

    /**
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
     * @throws \XF\Mvc\Reply\Exception
     * @throws \XF\PrintableException
     */
    public function actionDelete(ParameterBag $params)
    {
        $profile = $this->assertProfileExists($params['profile_id']);

        if ($this->isPost()) {
            $profile->delete();

            return $this->redirect($this->buildLink('th-install-upgrade-profiles'));
        } else {

            $viewParams = [
                'profile' => $profile
            ];

            return $this->view('ThemeHouse\InstallAndUpgrade:Profile\Delete', 'th_iau_profiles_delete',
                $viewParams);
        }
    }

    /**
     * @return \XF\Mvc\Reply\Message
     */
    public function actionToggle()
    {
        /** @var \XF\ControllerPlugin\Toggle $plugin */
        $plugin = $this->plugin('XF:Toggle');
        return $plugin->actionToggle('ThemeHouse\InstallAndUpgrade:Profile');
    }

    /**
     * @param $action
     * @param ParameterBag $params
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function preDispatchController($action, ParameterBag $params)
    {
        $this->assertAdminPermission('thaiu_manageProviders');
    }
}