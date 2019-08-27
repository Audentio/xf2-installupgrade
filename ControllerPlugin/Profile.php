<?php

namespace ThemeHouse\InstallAndUpgrade\ControllerPlugin;

use ThemeHouse\InstallAndUpgrade\Entity\Profile as ProfileEntity;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\AbstractHandler;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces\EncryptCredentials;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces\TFA;
use XF\ControllerPlugin\AbstractPlugin;

/**
 * Class Profile
 * @package ThemeHouse\InstallAndUpgrade\ControllerPlugin
 */
class Profile extends AbstractPlugin
{
    /**
     * @param AbstractHandler $handler
     * @param ProfileEntity $profile
     * @param callable $successFunction
     * @param array $params
     * @return \XF\Mvc\Reply\View
     */
    public function handleReply(
        AbstractHandler $handler,
        ProfileEntity $profile,
        callable $successFunction,
        array $params = []
    ) {
        $session = $this->app->session();

        if ($handler->getCapability('encryptCredentials')) {
            $secret = $this->filter('encryption_secret', 'str');
            if (!$secret) {
                if ($session->offsetExists('th_iau_encryption_secret')) {
                    $secret = $session->offsetGet('th_iau_encryption_secret');
                } else {
                    return $this->credentialsForm($profile, $params);
                }
            }

            $session->offsetSet('th_iau_encryption_secret', $secret);
            $session->offsetSet('th_iau_bounce', 0);
            /** @var EncryptCredentials $handler */
            $handler->setEncryptionSecret($secret);
        }

        if ($handler->getCapability('tfa') && $profile->has_tfa) {
            $tfaCode = $this->filter('tfa_code', 'str');
            if (!$tfaCode) {
                if ($session->offsetExists('th_iau_tfa_code')) {
                    $tfaCode = $session->offsetGet('th_iau_tfa_code');
                } else {
                    return $this->tfaForm($profile, $params);
                }
            }

            $session->offsetSet('th_iau_tfa_code', $tfaCode);
            $session->offsetSet('th_iau_bounce', 0);
            /** @var TFA $handler */
            $handler->setTfaCode($tfaCode);
        }

        return $successFunction();
    }

    /**
     * @param ProfileEntity $profile
     * @param array $extra
     * @return \XF\Mvc\Reply\View
     */
    public function credentialsForm(ProfileEntity $profile, array $extra)
    {
        $viewParams = [
            'profile' => $profile,
            'redirectUrl' => $this->app->request()->getFullRequestUri(),
            'extra' => $extra
        ];

        return $this->view('ThemeHouse\InstallAndUpgrade:Profile\CredentialsForm', 'th_iau_credentials_form',
            $viewParams);
    }

    /**
     * @param ProfileEntity $profile
     * @param array $extra
     * @return \XF\Mvc\Reply\View
     */
    public function tfaForm(ProfileEntity $profile, array $extra)
    {
        $viewParams = [
            'profile' => $profile,
            'redirectUrl' => $this->app->request()->getFullRequestUri(),
            'extra' => $extra
        ];

        return $this->view('ThemeHouse\InstallAndUpgrade:Profile\CredentialsForm', 'th_iau_tfa_form', $viewParams);
    }
}