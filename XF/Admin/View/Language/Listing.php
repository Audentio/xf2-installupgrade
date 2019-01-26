<?php

namespace ThemeHouse\InstallAndUpgrade\XF\Admin\View\Language;

use ThemeHouse\InstallAndUpgrade\Entity\Product;

class Listing extends XFCP_Listing
{
    public function renderHtml()
    {
        if (is_callable('parent::renderHtml'))
        {
            parent::renderHtml();
        }
        
        $languages = $this->params['languageTree'];
        $languages = $languages->getFlattened(0);
    
        $updates = [];
        foreach ($languages as $language) {
            /** @var Product $product */
            $product = $language['record']->THIAUProduct;
        
            if ($product && !empty($product->Profile->getHandler())
                && $product->update_available) {
                $updates[] = $language['record'];
            }
        }
    
        $this->params['updates'] = $updates;
    }
}
