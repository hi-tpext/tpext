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

use support\Log as baseLog;

/**
 * 日志管理类
 * @package think
 * @mixin Channel
 */
class Log
{
    public function record($level, $message, array $context = [])
    {
        if (is_array($message) || is_object($message)) {
            $message = json_encode($message);
        }
        baseLog::log($level, $message, $context);
    }

    public function log($level, $message, array $context = [])
    {
        if (is_array($message) || is_object($message)) {
            $message = json_encode($message);
        }
        baseLog::log($level, $message, $context);
    }

    public function info($message, array $context = [])
    {
        if (is_array($message) || is_object($message)) {
            $message = json_encode($message);
        }
        baseLog::info($message, $context);
    }

    public function debug($message, array $context = [])
    {
        if (is_array($message) || is_object($message)) {
            $message = json_encode($message);
        }
        baseLog::info($message, $context);
    }

    public function sql($message, array $context = [])
    {
        if (is_array($message) || is_object($message)) {
            $message = json_encode($message);
        }
        baseLog::info($message, $context);
    }

    public function notice($message, array $context = [])
    {
        if (is_array($message) || is_object($message)) {
            $message = json_encode($message);
        }
        baseLog::notice($message, $context);
    }

    public function warning($message, array $context = [])
    {
        if (is_array($message) || is_object($message)) {
            $message = json_encode($message);
        }
        baseLog::warning($message, $context);
    }

    public function error($message, array $context = [])
    {
        if (is_array($message) || is_object($message)) {
            $message = json_encode($message);
        }
        baseLog::error($message, $context);
    }

    public function critical($message, array $context = [])
    {
        if (is_array($message) || is_object($message)) {
            $message = json_encode($message);
        }
        baseLog::critical($message, $context);
    }

    public function alert($message, array $context = [])
    {
        if (is_array($message) || is_object($message)) {
            $message = json_encode($message);
        }
        baseLog::alert($message, $context);
    }
    
    public function emergency($message, array $context = [])
    {
        if (is_array($message) || is_object($message)) {
            $message = json_encode($message);
        }
        baseLog::emergency($message, $context);
    }
}
