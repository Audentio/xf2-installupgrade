<?php

namespace ThemeHouse\InstallAndUpgrade\XF\Admin\View\AddOn;

class Listing extends XFCP_Listing
{
    public function renderHtml()
    {
        if (is_callable('parent::renderHtml'))
        {
            parent::renderHtml();
        }
        
        $errors = [];
        $profiles = \XF::repository('ThemeHouse\InstallAndUpgrade:Profile')
            ->findProfiles()
            ->where('last_error_messages', '!=', '[]')
            ->fetch()
        ;
        foreach ($profiles as $profile)
        {
            if (!empty($profile->last_error_messages['addOns']))
            {
                $errors[$profile->profile_id] = [
                    'errors' => $profile->last_error_messages['addOns'],
                    'profile' => $profile
                ];
            }
        }
        
        $this->params['th_iau_errors'] = $errors;
        
        foreach ($this->params['installed'] as $addOnId => $addOn) {
            /** @var \ThemeHouse\InstallAndUpgrade\XF\Entity\AddOn $addOn */
            if ($addOn->THIAUProduct && $addOn->THIAUProduct->update_available) {
                $this->params['upgradeable'][$addOnId] = $addOn;
                unset($this->params['installed'][$addOnId]);
            }
        }
    }
}
