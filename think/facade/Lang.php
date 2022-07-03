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

class Lang
{
    /**
     * 配置参数
     * @var array
     */
    protected $config = [
        // 默认语言
        'default_lang'    => 'zh-cn',
        // 允许的语言列表
        'allow_lang_list' => [],
        // 是否使用Cookie记录
        'use_cookie'      => true,
        // 扩展语言包
        'extend_list'     => [],
        // 多语言cookie变量
        'cookie_var'      => 'think_lang',
        // 多语言header变量
        'header_var'      => 'think-lang',
        // 多语言自动侦测变量名
        'detect_var'      => 'lang',
        // Accept-Language转义为对应语言包名称
        'accept_language' => [
            'zh-hans-cn' => 'zh-cn',
        ],
        // 是否支持语言分组
        'allow_group'     => false,
    ];

    /**
     * 多语言信息
     * @var array
     */
    private $lang = [];

    /**
     * 当前语言
     * @var string
     */
    private $range = 'zh-cn';

    protected static  $instance;

    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new static;
        }

        return self::$instance;
    }

    /**
     * 加载语言定义(不区分大小写)
     * @access public
     * @param string|array $file  语言文件
     * @param string       $range 语言作用域
     * @return array
     */
    public static function load(string $file, string $range = '')
    {
        return self::getInstance()->_load($file, $range = '');
    }

    /**
     * 获取语言定义(不区分大小写)
     * @access public
     * @param string|null $name  语言变量
     * @param array       $vars  变量替换
     * @param string      $range 语言作用域
     * @return mixed
     */
    public static function get(string $name = null, array $vars = [], string $range = '')
    {
        return self::getInstance()->_get($name, $vars, $range);
    }

    /**
     * 加载语言定义(不区分大小写)
     * @access public
     * @param string|array $file  语言文件
     * @param string       $range 语言作用域
     * @return array
     */
    public function _load($file, $range = ''): array
    {
        $range = $range ?: $this->range;
        if (!isset($this->lang[$range])) {
            $this->lang[$range] = [];
        }

        $lang = [];

        foreach ((array) $file as $name) {
            if (is_file($name)) {
                $result = $this->parse($name);
                $lang   = array_change_key_case($result) + $lang;
            }
        }

        if (!empty($lang)) {
            $this->lang[$range] = $lang + $this->lang[$range];
        }

        return $this->lang[$range];
    }

    /**
     * 获取语言定义(不区分大小写)
     * @access public
     * @param string|null $name  语言变量
     * @param array       $vars  变量替换
     * @param string      $range 语言作用域
     * @return mixed
     */
    public function _get(string $name = null, array $vars = [], string $range = '')
    {
        $range = $range ?: $this->range;

        if (!isset($this->lang[$range])) {
            return $name;
        }

        // 空参数返回所有定义
        if (is_null($name)) {
            return $this->lang[$range] ?? [];
        }

        if ($this->config['allow_group'] && strpos($name, '.')) {
            [$name1, $name2] = explode('.', $name, 2);

            $value = $this->lang[$range][strtolower($name1)][$name2] ?? $name;
        } else {
            $value = $this->lang[$range][strtolower($name)] ?? $name;
        }

        // 变量解析
        if (!empty($vars) && is_array($vars)) {
            /**
             * Notes:
             * 为了检测的方便，数字索引的判断仅仅是参数数组的第一个元素的key为数字0
             * 数字索引采用的是系统的 sprintf 函数替换，用法请参考 sprintf 函数
             */
            if (key($vars) === 0) {
                // 数字索引解析
                array_unshift($vars, $value);
                $value = call_user_func_array('sprintf', $vars);
            } else {
                // 关联索引解析
                $replace = array_keys($vars);
                foreach ($replace as &$v) {
                    $v = "{:{$v}}";
                }
                $value = str_replace($replace, $vars, $value);
            }
        }

        return $value;
    }

    /**
     * 解析语言文件
     * @access protected
     * @param string $file 语言文件名
     * @return array
     */
    protected function parse(string $file): array
    {
        $type = pathinfo($file, PATHINFO_EXTENSION);

        switch ($type) {
            case 'php':
                $result = include $file;
                break;
        }

        return isset($result) && is_array($result) ? $result : [];
    }
}
