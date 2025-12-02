<?php

use think\Request;
use think\route\Url;
use think\facade\Log;
use think\facade\Cache;
use think\facade\Cookie;
use think\facade\Session;
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
     * @return Request|\Webman\Http\Request|support\Request|think\facade\Request|null
     */
    function tpRequest()
    {
        return request();
    }
}

if (!function_exists('tp_request')) {

    /**
     * @return Request|\Webman\Http\Request|support\Request|think\facade\Request|null
     */
    function tp_request()
    {
        return request();
    }
}

if (!function_exists('tp_cache')) {
    /**
     * 缓存管理
     * @param string|null $name    缓存名称
     * @param mixed  $value   缓存值
     * @param mixed  $options 缓存参数
     * @param string $tag     缓存标签
     * @return mixed
     */
    function tp_cache(?string $name = null, $value = '', $options = null, $tag = null)
    {
        if (is_null($name)) {
            return Cache::instance();
        }

        if ('' === $value) {
            // 获取缓存
            return 0 === strpos($name, '?') ? Cache::has(substr($name, 1)) : Cache::get($name);
        } elseif (is_null($value)) {
            // 删除缓存
            return Cache::delete($name);
        }

        // 缓存数据
        if (is_array($options)) {
            $expire = $options['expire'] ?? null; //修复查询缓存无法设置过期时间
        } else {
            $expire = $options;
        }

        if (is_null($tag)) {
            return Cache::set($name, $value, $expire);
        } else {
            return Cache::tag($tag)->set($name, $value, $expire);
        }
    }
}

if (!function_exists('cache')) {
    /**
     * 缓存管理
     * @param string|null $name    缓存名称
     * @param mixed  $value   缓存值
     * @param mixed  $options 缓存参数
     * @param string $tag     缓存标签
     * @return mixed
     */
    function cache(?string $name = null, $value = '', $options = null, $tag = null)
    {
        return tp_cache($name, $value, $options, $tag);
    }
}

if (!function_exists('tp_cookie')) {
    /**
     * Cookie管理
     * @param string $name   cookie名称
     * @param mixed  $value  cookie值
     * @param mixed  $option 参数
     * @return mixed
     */
    function tp_cookie(string $name, $value = '', $option = null)
    {
        if (is_null($value)) {
            // 删除
            Cookie::delete($name, $option ?: []);
        } elseif ('' === $value) {
            // 获取
            return 0 === strpos($name, '?') ? Cookie::has(substr($name, 1)) : Cookie::get($name);
        } else {
            // 设置
            return Cookie::set($name, $value, $option);
        }
    }
}

if (!function_exists('cookie')) {
    /**
     * Cookie管理
     * @param string $name   cookie名称
     * @param mixed  $value  cookie值
     * @param mixed  $option 参数
     * @return mixed
     */
    function cookie(string $name, $value = '', $option = null)
    {
        return tp_cookie($name, $value, $option);
    }
}

if (!function_exists('tp_session')) {
    /**
     * Session管理
     * @param string $name  session名称
     * @param mixed  $value session值
     * @return mixed
     */
    function tp_session($name = '', $value = '')
    {
        if (is_null($name)) {
            // 清除
            Session::clear();
        } elseif ('' === $name) {
            return Session::all();
        } elseif (is_null($value)) {
            // 删除
            Session::delete($name);
        } elseif ('' === $value) {
            // 判断或获取
            return 0 === strpos($name, '?') ? Session::has(substr($name, 1)) : Session::get($name);
        } else {
            // 设置
            Session::set($name, $value);
        }
    }
}

if (!function_exists('session')) {
    /**
     * Session管理
     * @param string $name  session名称
     * @param mixed  $value session值
     * @return mixed
     */
    function session($name = '', $value = '')
    {
        return tp_session($name, $value);
    }
}
