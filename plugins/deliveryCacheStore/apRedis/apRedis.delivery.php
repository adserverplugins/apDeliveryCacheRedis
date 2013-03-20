<?php

/**
 * apDeliveryCacheRedis for the OpenX ad server
 *
 * @author Matteo Beccati
 * @license GPLv2
 * @copyright 2011-2013 AdserverPlugins.com - All rights reserved
 *
 */


if (!class_exists('AP_Redis')) {
    require_once MAX_PATH.'/plugins/apRedis/Redis.php';
}


class AP_Redis_Cache extends AP_Redis
{
    static $instance;

    static function singleton()
    {
        if (!isset(self::$instance)) {
            self::$instance = new AP_Redis_Cache;
        }

        return self::$instance;
    }
}


function Plugin_deliveryCacheStore_apRedis_apRedis_Delivery_cacheRetrieve($filename)
{
    try {
        $redis = AP_Redis_Cache::singleton();

        $serializedCacheVar = $redis->get($filename);
        if (!empty($serializedCacheVar)) {
            return $redis->unserialize($serializedCacheVar);
        }
    } catch (Exception $e) {
    }

    return false;
}

function Plugin_deliveryCacheStore_apRedis_apRedis_Delivery_cacheStore($filename, $cache_contents)
{
    try {
        $redis = AP_Redis_Cache::singleton();

        if (empty($cache_contents['cache_expire'])) {
            $result = $redis->set($filename, $redis->serialize($cache_contents));
        } else {
            $expire = $cache_contents['cache_expire'] - MAX_commonGetTimeNow();
            if ($expire > 0) {
                $result = $redis->setex($filename, $redis->serialize($cache_contents), $expire);
            }
        }

        return $result;
    } catch (Exception $e) {
        return false;
    }
}
