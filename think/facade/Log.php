<?php

namespace think\facade;

use support\Log as baseLog;

class Log
{
    public static function record($level, $message, array $context = [])
    {
        if (is_array($message) || is_object($message)) {
            $message = json_encode($message);
        }
        baseLog::log($level, $message, $context);
    }
    public static function log($level, $message, array $context = [])
    {
        if (is_array($message) || is_object($message)) {
            $message = json_encode($message);
        }
        baseLog::log($level, $message, $context);
    }
    public static function info($message, array $context = [])
    {
        if (is_array($message) || is_object($message)) {
            $message = json_encode($message);
        }
        baseLog::info($message, $context);
    }
    public static function debug($message, array $context = [])
    {
        if (is_array($message) || is_object($message)) {
            $message = json_encode($message);
        }
        baseLog::info($message, $context);
    }
    public static function sql($message, array $context = [])
    {
        if (is_array($message) || is_object($message)) {
            $message = json_encode($message);
        }
        baseLog::info($message, $context);
    }
    public static function notice($message, array $context = [])
    {
        if (is_array($message) || is_object($message)) {
            $message = json_encode($message);
        }
        baseLog::notice($message, $context);
    }
    public static function warning($message, array $context = [])
    {
        if (is_array($message) || is_object($message)) {
            $message = json_encode($message);
        }
        baseLog::warning($message, $context);
    }
    public static function error($message, array $context = [])
    {
        if (is_array($message) || is_object($message)) {
            $message = json_encode($message);
        }
        baseLog::error($message, $context);
    }
    public static function critical($message, array $context = [])
    {
        if (is_array($message) || is_object($message)) {
            $message = json_encode($message);
        }
        baseLog::critical($message, $context);
    }
    public static function alert($message, array $context = [])
    {
        if (is_array($message) || is_object($message)) {
            $message = json_encode($message);
        }
        baseLog::alert($message, $context);
    }
    public static function emergency($message, array $context = [])
    {
        if (is_array($message) || is_object($message)) {
            $message = json_encode($message);
        }
        baseLog::emergency($message, $context);
    }
}
