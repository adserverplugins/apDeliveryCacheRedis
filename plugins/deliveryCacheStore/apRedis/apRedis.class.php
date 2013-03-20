<?php

/**
 * apDeliveryCacheRedis for the OpenX ad server
 *
 * @author Matteo Beccati
 * @license GPLv2
 * @copyright 2011-2013 AdserverPlugins.com - All rights reserved
 * 
 */


require_once LIB_PATH . '/Extension/deliveryCacheStore/DeliveryCacheStore.php';
// Using multi-dirname so tests can be run from either plugins or plugins_repo
require_once dirname(__FILE__) . '/apRedis.delivery.php';


class Plugins_DeliveryCacheStore_apRedis_apRedis extends Plugins_DeliveryCacheStore
{
    function getName()
    {
        return 'Redis';
    }

    function getStatus()
    {
        try {
            $redis = AP_Redis_Cache::singleton();
        } catch (Exception $e) {
            return false;
        }

        return true;
    }

    function _deleteCacheFile($filename)
    {
        $redis = AP_Redis_Cache::singleton();

        return $redis->del($filename);
    }

    function _deleteAll()
    {
        $redis = AP_Redis_Cache::singleton();

        return $redis->flushdb();
    }
}
