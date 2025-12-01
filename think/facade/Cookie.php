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

namespace think\facade;

use DateTimeInterface;
use Webman\Context;

/**
 * Cookie管理类
 */
class Cookie
{
    /**
     * 获取cookie
     * @access public
     * @param  mixed  $name 数据名称
     * @param  string $default 默认值
     * @return mixed
     */
    public static function get(string $name = '', $default = null)
    {
        return request()->cookie($name, $default);
    }

    /**
     * 是否存在Cookie参数
     * @access public
     * @param  string $name 变量名
     * @return bool
     */
    public static function has(string $name): bool
    {
        $cookie = request()->cookie();
        return !empty($cookie) && isset($cookie[$name]);
    }

    /**
     * Cookie 设置
     *
     * @access public
     * @param  string $name  cookie名称
     * @param  string $value cookie值
     * @param  mixed  $option 可选参数
     * @return void
     */
    public static function set(string $name, string $value, $option = null): void
    {
        $expire = 0;

        if (!is_null($option)) {
            if (is_numeric($option) || $option instanceof DateTimeInterface) {
                $expire = $option;
                $option = [];
            } else if (is_array($option) && isset($option['expire'])) {
                $expire = $option['expire'] ?: 0;
            }

            if (is_numeric($expire)) {
                $expire = intval($expire);
            } else if ($expire instanceof DateTimeInterface) {
                $expire = $expire->getTimestamp() - time();
            }
        }

        self::setCookie($name, $value, $expire, $option ?? []);
    }

    /**
     * Cookie 保存
     *
     * @access public
     * @param  string $name  cookie名称
     * @param  string $value cookie值
     * @param  int    $expire 有效期
     * @param  array  $option 可选参数
     * @return void
     */
    protected static function setCookie(string $name, string $value, int $expire, array $option = []): void
    {
        $cookie = self::getCookie();
        $cookie[$name] = [$value, $expire, array_change_key_case($option)];
        Context::set(static::class . '::cookie',  $cookie);
    }

    /**
     * 永久保存Cookie数据
     * @access public
     * @param  string $name  cookie名称
     * @param  string $value cookie值
     * @param  mixed  $option 可选参数 可能会是 null|integer|string
     * @return void
     */
    public static function forever(string $name, string $value = '', $option = null): void
    {
        if (is_null($option) || is_numeric($option)) {
            $option = [];
        }

        $option['expire'] = 315360000;

        self::set($name, $value, $option);
    }

    /**
     * Cookie删除
     * @access public
     * @param  string $name cookie名称
     * @param  array  $option cookie参数
     * @return void
     */
    public static function delete(string $name, array $option = []): void
    {
        self::setCookie($name, '', time() - 3600, $option);
    }

    /**
     * 获取cookie保存数据
     * @access public
     * @return array
     */
    public static function getCookie(): array
    {
        return Context::get(static::class . '::cookie', []);
    }

    /**
     * 保存Cookie
     * @access public
     * @return void
     */
    public static function save(): void
    {
        //
    }
}
