<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: yunwuxin <448901948@qq.com>
// +----------------------------------------------------------------------

use tpext\common\model\WebConfig;
use tpext\common\ExtLoader;

if (!function_exists('isTP51')) {
    function isTP51()
    {
        return ExtLoader::isTP51();
    }
}
if (!function_exists('isTP60')) {
    function isTP60()
    {
        return ExtLoader::isTP60();
    }
}
if (!function_exists('getTpVer')) {
    function getTpVer()
    {
        return ExtLoader::getTpVer();
    }
}
if (!function_exists('webConfig')) {
    function webConfig($key, $reget = false)
    {
        return WebConfig::config($key, $reget);
    }
}
