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

namespace think\facade;

/**
 * @see \think\Request
 * @package think\facade
 * @mixin \think\Request
 */
class Request extends Facade
{
    /**
     * Undocumented variable
     *
     * @var \think\Request
     */
    protected static $instance;

    /**
     * Undocumented function
     *
     * @return \think\Request
     */
    public static function getInstance()
    {
        return tpRequest();
    }

    // 调用实际类的方法
    public static function __callStatic($method, $params)
    {
        return call_user_func_array([static::getInstance(), $method], $params);
    }
}
