<?php

namespace tpext\common;

class Module
{
    /**
     * 名称标识 ，英文字母，如 hello.world
     *
     * @var string
     */
    protected static $name = null;

    /**
     * 显示名称，如 你好世界
     *
     * @var string
     */
    protected static $title = '';

    /**
     * 模块定义，如 ['module1' => ['controller1','controller2']]
     *
     * @var array
     */
    protected static $modules = [];

    /**
     * 命名空间和路径，一般不用填写
     *
     * @var array
     */
    protected static $namespaceMap = [];

    public static function getName()
    {
        return static::$name;
    }

    public static function getTitle()
    {
        return empty(static::$title) ? static::getName() : static::$title;
    }

    final public static function getId()
    {
        return preg_replace('/\W/', '_', get_called_class());
    }

    public static function getModules()
    {
        return static::$modules;
    }

    public static function getNameSpaceMap()
    {
        return static::$namespaceMap;
    }

    public static function moduleInit()
    {
        echo 'init module : ' . get_called_class(), '<br>';
    }
}
