<?php

namespace ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces;

interface EncryptCredentials
{
    public function encryptCredentials(array $input);

    public function setEncryptionSecret($secret);

    public function getCredentials();
}