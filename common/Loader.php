<?php

namespace tpsc\common;

class Loader
{
    private static $classMap = [];

    private static $modules = [];

    private static $plugins = [];

    private static $bindMods = [];

    // 注册classmap
    public static function addClassMap($class)
    {
        if (is_array($class)) {
            self::$classMap = array_merge(self::$classMap, $class);
        } else {
            self::$classMap[] = $class;
        }
    }

    public static function getClassMap()
    {
        return self::$classMap;
    }

    public static function addModules($class)
    {
        if (is_array($class)) {
            self::$modules = array_merge(self::$modules, $class);
        } else {
            self::$modules[] = $class;
        }
    }

    public static function getModules()
    {
        return self::$modules;
    }

    public static function addPlugins($class)
    {
        if (is_array($class)) {
            self::$plugins = array_merge(self::$plugins, $class);
        } else {
            self::$plugins[] = $class;
        }
    }

    public static function getPlugins()
    {
        return self::$plugins;
    }

    public static function bindModules($class)
    {
        if (is_array($class)) {
            self::$bindMods = array_merge(self::$bindMods, $class);
        } else {
            self::$bindMods[] = $class;
        }
    }

    public static function getBindModules()
    {
        return self::$bindMods;
    }
}
