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
        $arr1 = explode('/', trim($url, '/'));
        $arr2 = [];
        if (count($arr1) >= 3) {
            $arr2 = $arr1;
        } else if (strpos($url, '/') === 0) {
            //绝对路径
            $arr2[0] = !empty($arr1[0]) ? $arr1[0] : 'index';
            $arr2[1] = !empty($arr1[1]) ? $arr1[1] : 'index';
            $arr2[2] = !empty($arr1[2]) ? $arr1[2] : 'index';
        } else {
            $path = trim(request()->path(), '/');
            $arr2 = explode('/', $path);

            $arr2[0] = !empty($arr2[0]) ? $arr2[0] : 'index';
            $arr2[1] = !empty($arr2[1]) ? $arr2[1] : 'index';
            $arr2[2] = !empty($arr2[2]) ? $arr2[2] : 'index';

            $len = count($arr1);
            for ($i = 0; $i < $len; $i += 1) {
                array_pop($arr2);
            }
            $arr2 = array_merge($arr2, $arr1);
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
