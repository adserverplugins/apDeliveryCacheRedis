<?php

/**
 * apDeliveryCacheRedis for the OpenX ad server
 *
 * @author Matteo Beccati
 * @license GPLv2
 * @copyright 2011-2013 AdserverPlugins.com - All rights reserved
 *
 */


class AP_Redis
{
    protected $redis;
    protected $igbinary;

    public function __construct($aConf = null, $default = 'apRedis')
    {
        if (!isset($aConf)) {
            $aConf = $GLOBALS['_MAX']['CONF'][$default];
        }

        $this->igbinary = !empty($aConf['igbinary']) && extension_loaded('igbinary');
        
        if (extension_loaded('redis')) {
            $this->redis = new Redis;
            $method = empty($aConf['persistent']) ? 'connect' : 'pconnect';
            $this->redis->$method($aConf['host'], $aConf['port'], $aConf['timeout']);
        } else {
            if (!class_exists('Redis')) {
                include MAX_PATH.'/plugins/apRedis/Redisent/Redis.php';
            }
            $this->redis = new Redis("redis://{$aConf['host']}:{$aConf['port']}", $aConf['timeout']);
        }
        if (!empty($aConf['database'])) {
            $this->redis->select($aConf['database']);
        }
    }

    public function __call($name, $arguments) {
        return call_user_func_array(array($this->redis, $name), $arguments);
    }

    public function serialize($data)
    {
        if ($this->igbinary) {
            return igbinary_serialize($data);
        }

        return serialize($data);
    }

    public function unserialize($data)
    {
        if ($this->igbinary) {
            return igbinary_unserialize($data);
        }

        return unserialize($data);
    }
}
