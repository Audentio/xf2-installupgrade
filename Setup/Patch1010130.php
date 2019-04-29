<?php

namespace ThemeHouse\InstallAndUpgrade\Setup;

use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;

trait Patch1010130
{
    public function upgrade1010130Step1()
    {
        $this->installStep1();
    }

    public function upgrade1010130Step2()
    {
        $this->db()->update('xf_th_installupgrade_profile', [
            'last_error_messages' => json_encode([])
        ], null);
    }

    public function upgrade1010130Step3()
    {
        $this->db()->update('xf_th_installupgrade_profile', [
            'provider_id' => 'thiau_fp_themehouse'
        ], 'provider_id = "thiau_themehouse"');
        $this->db()->update('xf_th_installupgrade_profile', [
            'provider_id' => 'thiau_fp_xf2rm'
        ], 'provider_id = "thiau_xf2rm"');
        $this->db()->update('xf_th_installupgrade_profile', [
            'provider_id' => 'thiau_tp_dragonbyte'
        ], 'provider_id = "thiau_dragonbyte"');
    }
}