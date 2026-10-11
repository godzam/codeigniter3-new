<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Cache that several app servers can share. The backend comes from .env:
 *
 *   CACHE_DRIVER=file        (default) one server only, stored in application/cache
 *   CACHE_DRIVER=redis       REDIS_HOST / REDIS_PORT / REDIS_PASSWORD (application/config/redis.php)
 *   CACHE_DRIVER=memcached   application/config/memcached.php
 *
 * If the chosen backend is not reachable it falls back to files, so a cache
 * outage degrades (counters become per-server) instead of taking the site down.
 *
 * Used by the rate limiter and the export lock. Values must be serialisable.
 *
 * @property CI_Cache $cache
 */
class Sharedcache
{
    protected $CI;

    /** @var string */
    protected $adapter = 'file';

    public function __construct()
    {
        $this->CI = &get_instance();

        $wanted = strtolower((string) (getenv('CACHE_DRIVER') ?: 'file'));
        $wanted = in_array($wanted, ['file', 'redis', 'memcached'], true) ? $wanted : 'file';

        $config = ['adapter' => $wanted, 'backup' => 'file', 'key_prefix' => (string) (getenv('CACHE_PREFIX') ?: 'app_')];
        $this->CI->load->driver('cache', $config);

        $this->adapter = $wanted !== 'file' && $this->supported($wanted) ? $wanted : 'file';
    }

    /**
     * The backend actually in use (it can differ from CACHE_DRIVER after a fallback).
     */
    public function adapter()
    {
        return $this->adapter;
    }

    protected function supported($adapter)
    {
        return $this->CI->cache->is_supported($adapter);
    }

    public function get($key)
    {
        return $this->CI->cache->get($key);
    }

    public function save($key, $value, $seconds)
    {
        return $this->CI->cache->save($key, $value, $seconds);
    }

    public function delete($key)
    {
        return $this->CI->cache->delete($key);
    }
}
