<?php

namespace ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces;

/**
 * Interface EncryptCredentials
 * @package ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces
 *
 * @method decryptCredentials (array $options)
 */
interface EncryptCredentials
{
    /**
     * @param array $input
     * @return mixed
     */
    public function encryptCredentials(array $input);

    /**
     * @param $secret
     * @return mixed
     */
    public function setEncryptionSecret($secret);

    /**
     * @return mixed
     */
    public function getCredentials();
}