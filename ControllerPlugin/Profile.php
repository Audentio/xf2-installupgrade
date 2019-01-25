<?php

namespace ThemeHouse\InstallAndUpgrade\ControllerPlugin;

use ThemeHouse\InstallAndUpgrade\Entity\Product;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\AbstractHandler;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces\EncryptCredentials;
use ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces\TFA;
use XF\ControllerPlugin\AbstractPlugin;

class Profile extends AbstractPlugin
{
    public function credentialsForm(\ThemeHouse\InstallAndUpgrade\Entity\Profile $profile, array $extra)
    {
        $viewParams = [
            'profile' => $profile,
            'redirectUrl' => $this->app->request()->getFullRequestUri(),
            'extra' => $extra
        ];

        return $this->view('ThemeHouse\InstallAndUpgrade:Profile\CredentialsForm', 'th_iau_credentials_form',
            $viewParams);
    }

    public function tfaForm(\ThemeHouse\InstallAndUpgrade\Entity\Profile $profile, array $extra)
    {
        $viewParams = [
            'profile' => $profile,
            'redirectUrl' => $this->app->request()->getFullRequestUri(),
            'extra' => $extra
        ];

        return $this->view('ThemeHouse\InstallAndUpgrade:Profile\CredentialsForm', 'th_iau_tfa_form', $viewParams);
    }

    public function handleReply(
        AbstractHandler $handler,
        \ThemeHouse\InstallAndUpgrade\Entity\Profile $profile,
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
}