<?php

use tpext\common\ExtLoader;

ExtLoader::watch('app_init', tpext\common\behavior\AppInit::class, true, '初始化tpext');

$classMap = [
    'tpext\\common\\TpextCore'
];

ExtLoader::addClassMap($classMap);

if (!function_exists('tpInput')) {
    /**
     * 获取输入数据 支持默认值和过滤
     * (tp框架用法，用于webman等框架兼容)
     * @param string    $key 获取的变量名
     * @param mixed     $default 默认值
     * @param string    $filter 过滤方法
     * @return mixed
     */
    function tpInput($key = '', $default = null, $filter = '')
    {
        return input($key, $default, $filter);
    }
}
