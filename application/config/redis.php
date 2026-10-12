<?php

defined('BASEPATH') or exit('No direct script access allowed');

/*
| Redis connection used by CI3's redis cache driver (CACHE_DRIVER=redis) and
| session driver (SESS_DRIVER=redis). Set the values in .env; with several app
| servers they all point at the same Redis.
*/
$config['socket_type'] = getenv('REDIS_SOCKET') ? 'unix' : 'tcp';
$config['socket'] = getenv('REDIS_SOCKET') ?: '/var/run/redis.sock';
$config['host'] = getenv('REDIS_HOST') ?: '127.0.0.1';
$config['password'] = getenv('REDIS_PASSWORD') ?: null;
$config['port'] = (int) (getenv('REDIS_PORT') ?: 6379);
$config['timeout'] = 2;
