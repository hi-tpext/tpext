<?php

namespace tpext\common;

class Module
{
    protected static $name = null;

    protected static $modules = [];

    protected static $namespaceMap = [];

    public static function getName()
    {
        return static::$name;
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
