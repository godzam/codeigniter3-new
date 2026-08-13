<?php

defined('BASEPATH') or exit('No direct script access allowed');

/**
 * Simple fixed-window rate limiter backed by CI3's file cache driver —
 * no Redis/Memcached required. Good enough for things like login-attempt
 * throttling on a single-server deployment. For multi-server setups,
 * point the cache driver at something shared (redis/memcached) via CI3's
 * normal cache config instead of 'file'.
 *
 * Usage:
 *   $this->load->library('ratelimiter');
 *
 *   if (! $this->ratelimiter->attempt('login:'.$this->input->ip_address(), 5, 60)) {
 *       $retry_after = $this->ratelimiter->retry_after('login:'.$this->input->ip_address());
 *       show_error('Too many attempts. Try again in '.$retry_after.'s.', 429);
 *   }
 */
class Ratelimiter
{
    protected $CI;

    public function __construct()
    {
        $this->CI = &get_instance();
        $this->CI->load->driver('cache', ['adapter' => 'file', 'key_prefix' => 'ratelimit_']);
    }

    /**
     * Record one attempt against $key and report whether it's still
     * within the allowed rate.
     *
     * @param string $key            Identifies what's being limited, e.g. 'login:203.0.113.5'
     * @param int    $max_attempts   Attempts allowed per window
     * @param int    $window_seconds Window length in seconds
     * @return bool TRUE if this attempt is allowed, FALSE if the limit was hit
     */
    public function attempt($key, $max_attempts = 5, $window_seconds = 60)
    {
        $cache_key = md5($key);
        $data = $this->CI->cache->get($cache_key);

        if ($data === false || time() > $data['reset_at']) {
            $data = ['count' => 0, 'reset_at' => time() + $window_seconds];
        }

        ++$data['count'];
        $this->CI->cache->save($cache_key, $data, $window_seconds);

        return $data['count'] <= $max_attempts;
    }

    /**
     * Seconds remaining until $key's current window resets. 0 if $key
     * has no active window.
     */
    public function retry_after($key)
    {
        $data = $this->CI->cache->get(md5($key));

        if ($data === false) {
            return 0;
        }

        return max(0, $data['reset_at'] - time());
    }

    /**
     * Clear $key's window early — e.g. after a successful login, so a
     * legitimate user isn't penalized by earlier failed attempts.
     */
    public function reset($key)
    {
        $this->CI->cache->delete(md5($key));
    }
}
