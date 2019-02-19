<?php

namespace ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Traits;

trait TFATrait
{
    protected $tfaCode;

    /**
     * @param $key
     */
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