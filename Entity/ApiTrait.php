<?php

namespace ThemeHouse\InstallAndUpgrade\Entity;

use XF\Mvc\Entity\Entity;
use XF\Mvc\Entity\Structure;

trait ApiTrait
{
    protected function setupApiResultData(
        \XF\Api\Result\EntityResult $result, $verbosity = self::VERBOSITY_NORMAL, array $options = []
    )
    {
        // try to call parent method (if any)
        try
        {
            // parent::setupApiResultData($result, $verbosity, $options);
        }
        catch(\LogicException $e) {}

        $config = [];

        $config['XF:Style'] = function() use ($result, $verbosity, $options)
        {
            $result->includeColumn([
                'style_id',
                'parent_id',
                'title',
                'description',
                'last_modified_date',
                'user_selectable',
                'designer_mode',
            ]);
        };

        $config['XF:AddOn'] = function() use ($result, $verbosity, $options)
        {
            $result->includeColumn([
                'addon_id',
                'title',
                'version_string',
                'version_id',
                'json_hash',
                'active',
                'is_legacy',
                'is_processing',
                'last_pending_action',
            ]);
        };

        $config['XF:Language'] = function() use ($result, $verbosity, $options)
        {
            $result->includeColumn([
                'language_id',
                'parent_id',
                'parent_list',
                'title',
                'language_code',
                'text_direction',
                'user_selectable',
            ]);
        };

        if (!empty($config[$this->_structure->shortName]))
        {
            $config[$this->_structure->shortName]();
            $result->includeRelation('THIAUProduct');
        }
    }
}