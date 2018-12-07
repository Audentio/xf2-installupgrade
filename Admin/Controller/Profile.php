<?php

namespace ThemeHouse\InstallAndUpgrade\Admin\Controller;

use XF\Admin\Controller\AbstractController;
use XF\Mvc\FormAction;
use XF\Mvc\ParameterBag;

class Profile extends AbstractController
{
    /**
     * @param $action
     * @param ParameterBag $params
     * @throws \XF\Mvc\Reply\Exception
     */
    protected function preDispatchController($action, ParameterBag $params)
    {
        $this->assertAdminPermission('thaiu_manageProviders');
    }

    public function actionIndex()
    {
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
	 * @param ParameterBag $params
	 *
	 * @return \XF\Mvc\Reply\View
	 * @throws \Exception
	 */
    public function actionAdd(ParameterBag $params)
    {
        if ($this->isPost()) {
            /** @var \ThemeHouse\InstallAndUpgrade\Entity\Profile $profile */
            $profile = $this->em()->create('ThemeHouse\InstallAndUpgrade:Profile');
            $profile->provider_id = $this->filter('provider_id', 'str');
            return $this->profileAddEdit($profile);
        } else {
            $profiles = $this->finder('ThemeHouse\InstallAndUpgrade:Profile')
				->fetch()
				->groupBy('provider_id')
			;
            
            $providers = $this->getHandlerRepo()
				->getProviderHandlers(true)
				->filter(function(\ThemeHouse\InstallAndUpgrade\Provider\AbstractHandler $handler) use ($profiles)
				{
					if ($handler->isUnique && !empty($profiles[$handler->getIdentifier()]))
					{
						return null;
					}
					
					return $handler;
				})
				->pluck(function(\ThemeHouse\InstallAndUpgrade\Provider\AbstractHandler $handler, $key)
				{
					return [$handler->getIdentifier(), $handler->getTitle()];
				}, false);
			;

            $viewParams = [
                'providers' => $providers
            ];

            return $this->view('ThemeHouse\InstallAndUpgrade:Profile\Add', 'th_iau_profiles_add', $viewParams);
        }
    }

    /**
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\View
     * @throws \XF\Mvc\Reply\Exception
     */
    public function actionEdit(ParameterBag $params)
    {
        $profile = $this->assertProfileExists($params->profile_id);
        return $this->profileAddEdit($profile);
    }

    protected function profileAddEdit(\ThemeHouse\InstallAndUpgrade\Entity\Profile $profile)
    {
        $viewParams = [
            'profile' => $profile
        ];

        return $this->view('ThemeHouse\InstallAndUpgrade:Profile\Edit', 'th_iau_profiles_edit', $viewParams);
    }

    /**
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\Redirect
     * @throws \XF\Mvc\Reply\Exception
     * @throws \XF\PrintableException
	 * @throws \Exception
     */
    public function actionSave(ParameterBag $params)
    {
        if ($params->profile_id) {
            $profile = $this->assertProfileExists($params->profile_id);
        } else {
            /** @var \ThemeHouse\InstallAndUpgrade\Entity\Profile $profile */
            $profile = $this->em()->create('ThemeHouse\InstallAndUpgrade:Profile');
        }

        $this->profileSaveProcess($profile)->run();
        
        return $this->redirect($this->buildLink('install-upgrade-profiles'));
    }

    /**
     * @param ParameterBag $params
     * @return \XF\Mvc\Reply\Redirect|\XF\Mvc\Reply\View
     * @throws \XF\Mvc\Reply\Exception
     * @throws \XF\PrintableException
     */
    public function actionDelete(ParameterBag $params)
    {
        $profile = $this->assertProfileExists($params->profile_id);

        if ($this->isPost()) {
            $profile->delete();

            return $this->redirect($this->buildLink('install-upgrade-profiles'));
        } else {

            $viewParams = [
                'profile' => $profile
            ];

            return $this->view('ThemeHouse\InstallAndUpgrade:Profile\Delete', 'th_iau_profiles_delete',
                $viewParams);
        }
    }

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
        
		$form->validate(function(FormAction $form) use ($input, $profile)
		{
			/** @var \ThemeHouse\InstallAndUpgrade\Entity\Profile $testProfile */
			$testProfile = $this->em()->create('ThemeHouse\InstallAndUpgrade:Profile');
			$testProfile->provider_id = $input['provider_id'];
			$handler = $testProfile->getHandler();
			
			if ($handler->canEncryptCredentials())
			{
				$secret = $this->filter('encryption_secret', 'str');
				$handler->setEncryptionSecret($secret);
				
				$options = $handler->encryptCredentials($input['options']);
				$input['options'] = $options;
				
				$profile->requires_decryption = true;
			}
			
			$testProfile->bulkSet($input);
			
			$result = $handler->tryAuthentication($testProfile);
			if ($result !== true)
			{
				if ($result)
				{
					throw $result;
				}
				else
				{
					$form->logError(\XF::phrase('th_iau_authentication_failed'));
				}
			}
			
			$handler->setEncryptionSecret($this->filter('encryption_secret', 'str'));
			$testProfile->getProductsFromProvider();
		});
	
		$form->basicEntitySave($profile, $input);

        return $form;
    }

    public function actionToggle()
    {
        /** @var \XF\ControllerPlugin\Toggle $plugin */
        $plugin = $this->plugin('XF:Toggle');
        return $plugin->actionToggle('ThemeHouse\InstallAndUpgrade:Profile');
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
	 * @return \ThemeHouse\InstallAndUpgrade\Repository\Handler|\XF\Mvc\Entity\Repository
	 */
    protected function getHandlerRepo()
	{
		return $this->repository('ThemeHouse\InstallAndUpgrade:Handler');
	}
}