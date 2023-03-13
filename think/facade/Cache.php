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
declare (strict_types = 1);

namespace think\facade;

use think\Facade;

/**
 * @see \think\Cache
 * @package think\facade
 * @mixin \think\Cache
 * @method static bool clear() 清空缓冲池
 * @method static mixed get(string $key, mixed $default = null) 读取缓存
 * @method static bool set(string $key, mixed $value, int|\DateTime $ttl = null) 写入缓存
 * @method static bool delete(string $key) 删除缓存
 * @method static bool setMultiple(iterable $values, null|int|\DateInterval $ttl = null) 写入缓存
 * @method static bool deleteMultiple(iterable $keys) 删除缓存
 * @method static bool has(string $key) 判断缓存是否存在
 */
class Cache extends Facade
{
    /**
     * Undocumented variable
     *
     * @var \think\Cache
     */
    protected static $instance;

    /**
     * Undocumented function
     *
     * @return \think\Cache
     */
    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new \think\Cache;
        }

        return self::$instance;
    }

    // 调用实际类的方法
    public static function __callStatic($method, $params)
    {
        return call_user_func_array([static::getInstance(), $method], $params);
    }
}
