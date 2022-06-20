<?php

use think\facade\Log;
use think\route\Url;

if (!function_exists('trace')) {

    function trace($log)
    {
        Log::info($log);
    }
}

if (!function_exists('input')) {

    function input($name, $default = null)
    {
        if ($pos = strpos($name, '.')) {
            // 指定参数来源
            $method = substr($name, 0, $pos);
            if (in_array($method, ['get', 'post', 'file'])) {
                $name = substr($name, $pos + 1);

                return request()->$method($name, $default);
            }
        }

        return request()->input($name, $default);
    }
}

if (!function_exists('url')) {

    /**
     * Url生成
     * @param string      $url    路由地址
     * @param array       $vars   变量
     * @param bool|string $suffix 生成的URL后缀
     * @return Url
     */
    function url($url = '', $vars = [], $suffix = false)
    {
        $url = ltrim($url, '/');
        $path = ltrim(request()->path(), '/');

        $arr1 = explode('/', $url);
        $arr2 = explode('/', $path);

        $arr2[1] = !empty($arr2[1]) ? $arr2[1] : 'index';
        $arr2[2] = !empty($arr2[2]) ? $arr2[2] : 'index';

        if (count($arr1) == 1) {
            $arr2 = [$arr2[0], $arr2[1], $arr1[0]];
        } else if (count($arr1) == 2) {
            $arr2 = [$arr2[0], $arr1[0], $arr1[1]];
        } else if (count($arr1) >= 3) {
            $arr2 = [$arr1[0], $arr1[1], $arr1[2]];
        }

        $url = '/' . implode('/', $arr2);

        $url = (count($vars) > 0 ? $url . '?' . http_build_query($vars) : $url) . ($suffix ? '.html' : '');

        return new Url($url);
    }
}
