<?php
/**
 *
 */

namespace DataTables;


use Phalcon\Di;
use Phalcon\Cache\BackendInterface;

class CacheHelper
{
    /** @var BackendInterface $cache */
    public $cache;
    public $lifetime;

    public function __construct($cache_di, $lifetime = 86400)
    {
        if (Di::getDefault()->has($cache_di)) {
            /** @var BackendInterface $cache */
            $this->cache = Di::getDefault()->getShared($cache_di);
        }
        $this->lifetime = $lifetime;
    }

    public function getCache($key)
    {
        if ($this->cache != null && $this->cache->exists($key, $this->lifetime)) {
            return $this->cache->get($key);
        }

        return false;
    }

    public function saveCache($key, $data)
    {
        if ($this->cache != null) {
            return $this->cache->save($key, $data, $this->lifetime);
        }

        return false;
    }
}