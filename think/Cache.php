<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2021 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
declare(strict_types=1);

namespace think;

use support\Cache as baseCache;

/**
 * 缓存管理类
 * @package think
 */
class Cache
{
    public function get(string $key, mixed $default = null)
    {
        return baseCache::get($key, $default);
    }

    public function set(string $key, mixed $value, int|\DateTime $ttl = null)
    {
        return baseCache::set($key, $value, $ttl);
    }

    public function delete(string $key)
    {
        return baseCache::delete($key);
    }

    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null)
    {
        return baseCache::setMultiple($values, $ttl);
    }

    public function deleteMultiple(iterable $keys)
    {
        return baseCache::deleteMultiple($keys);
    }

    public function has(string $key)
    {
        return baseCache::has($key);
    }

    public function clear()
    {
        return baseCache::clear();
    }
}
