<?php

namespace tpext\common;

class Plugin
{
    protected static $name = null;

    public static function getName()
    {
        return static::$name;
    }

    public final static function getId()
    {
        return preg_replace('/\W/', '_', get_called_class());
    }

    public static function pluginInit()
    {
        echo 'init plugin : ' . get_called_class(), '<br>';
    }
}
