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

use think\Facade;
use think\log\Channel;
use think\log\ChannelSet;

/**
 * @see \think\Log
 * @package think\facade
 * @mixin \think\Log
 * @method static \think\Log record(mixed $msg, string $type = 'info', array $context = [], bool $lazy = true) 记录日志信息
 * @method static \think\Log write(mixed $msg, string $type = 'info', array $context = []) 实时写入日志信息
 * @method static Event listen($listener) 注册日志写入事件监听
 * @method static void log(string $level, mixed $message, array $context = []) 记录日志信息
 * @method static void emergency(mixed $message, array $context = []) 记录emergency信息
 * @method static void alert(mixed $message, array $context = []) 记录警报信息
 * @method static void critical(mixed $message, array $context = []) 记录紧急情况
 * @method static void error(mixed $message, array $context = []) 记录错误信息
 * @method static void warning(mixed $message, array $context = []) 记录warning信息
 * @method static void notice(mixed $message, array $context = []) 记录notice信息
 * @method static void info(mixed $message, array $context = []) 记录一般信息
 * @method static void debug(mixed $message, array $context = []) 记录调试信息
 * @method static void sql(mixed $message, array $context = []) 记录sql信息
 */
class Log
{

    /**
     * Undocumented variable
     *
     * @var \think\Log
     */
    protected static $instance;

    /**
     * Undocumented function
     *
     * @return \think\Log
     */
    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new \think\Log;
        }

        return self::$instance;
    }

    // 调用实际类的方法
    public static function __callStatic($method, $params)
    {
        return call_user_func_array([static::getInstance(), $method], $params);
    }
}
