<?php

namespace ThemeHouse\InstallAndUpgrade\Listener;

use XF\App;

class AppSetup
{
    public static function appSetup(App $app)
    {
        $session = $app->session();

        if($session->offsetExists('th_iau_bounce')) {
            $bounce = $session->offsetGet('th_iau_bounce');

            if($bounce) {
                $session->offsetUnset('th_iau_encryption_secret');
                $session->offsetUnset('th_iau_tfa_code');
                $session->offsetUnset('th_iau_bounce');
            }
            else {
                $session->offsetSet('th_iau_bounce', 1);
            }
        }
    }
}
