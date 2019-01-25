<?php

namespace ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Traits;

trait TFATrait
{
    protected $tfaCode;

    public function setTfaCode($key)
    {
        $this->tfaCode = $key;
    }

    /**
     * @return mixed
     */
    public function getTfaCode()
    {
        return $this->tfaCode;
    }
}