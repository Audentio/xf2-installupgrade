<?php

namespace ThemeHouse\InstallAndUpgrade\Setup;

use XF;

/**
 * Trait Patch1010130
 * @package ThemeHouse\InstallAndUpgrade\Setup
 */
trait Patch1010130
{
    /**
     *
     */
    public function upgrade1010130Step1()
    {
        $this->installStep1();
    }

    /**
     *
     */
    public function upgrade1010130Step2()
    {
        XF::db()->update('xf_th_installupgrade_profile', [
            'last_error_messages' => json_encode([])
        ], null);
    }

    /**
     *
     */
    public function upgrade1010130Step3()
    {
        XF::db()->update('xf_th_installupgrade_profile', [
            'provider_id' => 'thiau_fp_themehouse'
        ], 'provider_id = "thiau_themehouse"');
        XF::db()->update('xf_th_installupgrade_profile', [
            'provider_id' => 'thiau_fp_xf2rm'
        ], 'provider_id = "thiau_xf2rm"');
        XF::db()->update('xf_th_installupgrade_profile', [
            'provider_id' => 'thiau_tp_dragonbyte'
        ], 'provider_id = "thiau_dragonbyte"');
    }
}