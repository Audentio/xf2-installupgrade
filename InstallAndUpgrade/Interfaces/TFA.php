<?php

namespace ThemeHouse\InstallAndUpgrade\InstallAndUpgrade\Interfaces;

interface TFA
{
    public function setTfaCode($code);

    public function getTfaCode();
}