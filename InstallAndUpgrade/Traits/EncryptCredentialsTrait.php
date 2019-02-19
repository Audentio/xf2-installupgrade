<?php

namespace ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Traits;

use ThemeHouse\InstallAndUpgrade\Util\Encryption;

trait EncryptCredentialsTrait
{
    protected $secret;

    protected $credentials;

    /**
     * @return array
     */
    public function getEncryptedOptions()
    {
        return [
            'password'
        ];
    }

    /**
     * @param $secret
     */
    public function setEncryptionSecret($secret)
    {
        $this->secret = $secret;
    }

    /**
     * @return array
     */
    public function getCredentials()
    {
        return $this->decryptCredentials();
    }

    /**
     * @param array $input
     * @return array
     */
    public function encryptCredentials(array $input)
    {
        $encryptedOptions = $this->getEncryptedOptions();
        if ($this->secret) {
            foreach ($input as $key => &$value) {
                if (in_array($key, $encryptedOptions)) {
                    $value = Encryption::encryptString($value, $this->secret);
                    $input[$key . '_encrypted'] = true;
                }
            }
        }

        return $input;
    }

    /**
     * @param array $options
     * @return array
     */
    protected function decryptCredentials(array $options = [])
    {
        $options = $this->profile->options;
        $encryptedOptions = $this->getEncryptedOptions();
        if ($this->secret) {
            foreach ($options as $key => &$value) {
                if (in_array($key, $encryptedOptions) && isset($options[$key . '_encrypted'])) {
                    $value = Encryption::decryptString($value, $this->secret);
                    unset($options[$key . '_encrypted']);
                }
            }
        }

        return $options;
    }
}