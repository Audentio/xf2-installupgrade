<?php

namespace ThemeHouse\InstallAndUpgrade\Setup;

use XF\Db\Schema\Alter;
use XF\Db\Schema\Create;

trait Patch1010130
{
    public function upgrade1010130Step1()
    {
        $this->schemaManager()->alterTable('xf_th_installupgrade_profile', function (Alter $table) {
            $table->addColumn('last_error_messages', 'blob');
        });
        
        $this->db()->update('xf_th_installupgrade_profile', [
            'last_error_messages' => json_encode([])
        ], null);
    }
}