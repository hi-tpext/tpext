<?php

use think\Request;
use think\route\Url;
use think\facade\Log;
use Webman\Http\Response;
use tpext\common\ExtLoader;

$classMap = [
    'tpext\\common\\TpextCore'
];

ExtLoader::addClassMap($classMap);

if (!function_exists('trace')) {

    function trace($log)
    {
        Log::info($log);
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
        $url = trim($url, '/');
        $path = trim(request()->path(), '/');

        $arr1 = explode('/', $url);
        $arr2 = explode('/', $path);

        $arr2[0] = !empty($arr2[0]) ? $arr2[0] : 'index';
        $arr2[1] = !empty($arr2[1]) ? $arr2[1] : 'index';
        $arr2[2] = !empty($arr2[2]) ? $arr2[2] : 'index';

        if (count($arr1) == 1) {
            $arr2 = [$arr2[0], $arr2[1], $arr1[0]];
        } else if (count($arr1) == 2) {
            $arr2 = [$arr2[0], $arr1[0], $arr1[1]];
        } else if (count($arr1) >= 3) {
            $arr2 = [$arr1[0], $arr1[1], $arr1[2]];
        }

        $url = strtolower('/' . implode('/', $arr2));

        $url = (count($vars) > 0 ? $url . '?' . http_build_query($vars) : $url) . ($suffix ? '.html' : '');

        return new Url($url);
    }
}

if (!function_exists('download')) {
    /**
     * @param string $filename 要下载的文件
     * @param string $name     显示文件名
     * @return Response
     */
    function download(string $filename, string $name)
    {
        $response = new Response;
        $response->download($filename, $name);

        return $response;
    }
}


if (!function_exists('tpRequest')) {

    /**
     * @return Request|\Webman\Http\Request|support\Request|think\facade\Request|Request|null
     */
    function tpRequest()
    {
        return request();
    }
}

if (!function_exists('tpInput')) {

    /**
     * 获取输入数据 支持默认值和过滤
     * (tp框架用法，用于webman等框架兼容)
     * @param string    $key 获取的变量名
     * @param mixed     $default 默认值
     * @param string    $filter 过滤方法
     * @return mixed
     * @example 1   tpInput('get.name') 获取get参数
     * @example 2   tpInput('post.') 获取post全部参数
     * @example 3   tpInput('id/d', 0) 类型转换
     */
    function tpInput($key = '', $default = null, $filter = '')
    {
        if (0 === strpos($key, '?')) {
            $key = substr($key, 1);
            $has = true;
        }

        if ($pos = strpos($key, '.')) {
            // 指定参数来源
            $method = substr($key, 0, $pos);
            if (in_array($method, ['get', 'post', 'put', 'patch', 'delete', 'route', 'param', 'request', 'session', 'cookie', 'server', 'header', 'file'])) {
                $key = substr($key, $pos + 1);
                if ('server' == $method && is_null($default)) {
                    $default = '';
                }
            } else {
                $method = 'param';
            }
        } else {
            // 默认为自动判断
            $method = 'param';
        }

        if (isset($has)) {
            return tpRequest()->has($key, $method, $default);
        } else {
            return tpRequest()->$method($key, $default, $filter);
        }
    }
}
