<?php

namespace ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Traits;

/**
 * Trait TFATrait
 * @package ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Traits
 */
trait TFATrait
{
    /**
     * @var
     */
    protected $tfaCode;

    /**
     * @return mixed
     */
    public function getTfaCode()
    {
        return $this->tfaCode;
    }

    /**
     * @param $key
     */
    public function setTfaCode($key)
    {
        $this->tfaCode = $key;
    }
}