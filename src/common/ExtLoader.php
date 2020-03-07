<?php

namespace tpext\common;

use think\Db;
use think\facade\Hook;
use tpext\common\model\Extension as ExtensionModel;

class ExtLoader
{
    private static $classMap = [];

    private static $modules = [];

    private static $bindMods = [];

    private static $watches = [];

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

    public static function watch($name, $class, $desc = '', $first = false)
    {
        if (!isset(self::$watches[$class])) {

            self::$watches[$class] = [$class, $desc, $first];

            Hook::add($name, $class, $first);
        }
    }

    public static function trigger($name, $params = null, $once = false)
    {
        Hook::listen($name, $params, $once);
    }

    public static function geWatches()
    {
        return self::$watches;
    }

    public static function getInstalled($reget = false)
    {
        if (empty(config('database.database'))) {
            return [];
        }

        $tableName = config('database.prefix') . 'extension';

        $isTable = Db::query("SHOW TABLES LIKE '{$tableName}'");

        if (empty($isTable)) {
            cache('installedExtensions', null);
            return [];
        }

        $data = cache('installedExtensions');

        if (!$reget && $data) {
            return $data;
        }

        $list = ExtensionModel::all();

        cache('installExtensions', $list);

        return $list;
    }

    

    public static function clearCache()
    {
        cache('tpext_modules', null);
        cache('tpext_bind_modules', null);
    }
}
