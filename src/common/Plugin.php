<?php

namespace tpext\common;

class Plugin
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

    public static function getName()
    {
        return static::$name;
    }

    public static function getTitle()
    {
        return empty(static::$title) ? static::getName() : static::$title;
    }

    public final static function getId()
    {
        return md5(get_called_class());
    }

    public static function pluginInit($info = [])
    {
        echo 'init plugin : ' . get_called_class(), '<br>';

        return true;
    }
}
