<?php

namespace ThemeHouse\InstallAndUpgrade\Repository;

/**
 * This class is used to reset the query log which XF doesn't provide a sane way to zap
 *
 * @package ThemeHouse\InstallAndUpgrade\Repository
 */
abstract class DbLogQuery extends \XF\DB\AbstractAdapter
{
    public static function resetQueryLog(\XF\DB\AbstractAdapter $db)
    {
        try
        {
            $db->queryLog = [];
        }
        catch(\Exception $e)
        {

        }
    }
}