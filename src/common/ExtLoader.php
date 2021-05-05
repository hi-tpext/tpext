<?php

namespace tpext\common;

use think\App;
use think\facade\Db;
use think\facade\Event;
use think\facade\Hook;
use think\helper\Str;
use think\Loader;
use tpext\common\model\Extension as ExtensionModel;

class ExtLoader
{
    private static $classMap = [];

    private static $modules = [];

    private static $resources = [];

    private static $bindModules = [];

    private static $watches = [];

    private static $tpVer;

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

    public static function addResources($class)
    {
        if (is_array($class)) {
            self::$resources = array_merge(self::$resources, $class);
        } else {
            self::$resources[] = $class;
        }
    }

    public static function getModules()
    {
        return self::$modules;
    }

    public static function getResources()
    {
        return self::$resources;
    }

    public static function getExtensions()
    {
        return array_merge(self::$modules, self::$resources);
    }

    public static function bindModules($class)
    {
        if (is_array($class)) {
            self::$bindModules = array_merge(self::$bindModules, $class);
        } else {
            self::$bindModules[] = $class;
        }
    }

    public static function getBindModules()
    {
        return self::$bindModules;
    }

    public static function watch($name, $class, $first = false, $desc = '')
    {
        if (!isset(self::$watches[$name . ':' . $class])) {

            self::$watches[$name . ':' . $class] = [$class, $desc, $first];
            if (self::isTP51()) {
                Hook::add($name, $class, $first);
            } else {
                Event::listen($name, $class, $first);
            }
        }
    }

    public static function trigger($name, $params = null, $once = false)
    {
        if (self::isTP51()) {
            Hook::listen($name, $params, $once);
        } else {
            Event::trigger($name, $params, $once);
        }
    }

    public static function geWatches()
    {
        return self::$watches;
    }

    public static function bindExtensions()
    {
        if (!config('app_debug')) {
            self::$modules = cache('tpext_modules');
            self::$resources = cache('tpext_resources');
            self::$bindModules = cache('tpext_bind_modules');
        }

        $installed = self::getInstalled();

        $enabled = [];
        foreach ($installed as $ins) {
            if ($ins['enable'] == 1) {
                $enabled[] = $ins['key'];
            }
        }

        if (empty(self::$modules)) {
            self::findExtensions($enabled);
            cache('tpext_modules', self::$modules);
            cache('tpext_resources', self::$resources);
            cache('tpext_bind_modules', self::$bindModules);
        }

        foreach (self::$modules as $k => $m) {
            if (in_array($k, $enabled)) {
                $m->loaded();
            }
        }

        foreach (self::$resources as $k => $r) {
            if (in_array($k, $enabled)) {
                $r->loaded();
            }
        }
    }

    /**
     * Undocumented function
     *
     * @param array $enabled
     * @return void
     */
    private static function findExtensions($enabled)
    {
        self::trigger('tpext_find_extensions');

        $classMap = self::$classMap;

        foreach ($classMap as $declare) {

            if (!class_exists($declare)) {
                continue;
            }

            $reflectionClass = new \ReflectionClass($declare);

            if (!$reflectionClass->isInstantiable()) {
                continue;
            }

            if (!isset(self::$modules[$declare]) && !isset(self::$resources[$declare]) && $reflectionClass->hasMethod('extInit') && $reflectionClass->hasMethod('getInstance')) {

                $instance = $declare::getInstance();

                if (!($instance instanceof Extension)) {
                    continue;
                }

                if ($instance instanceof Resource) {
                    self::$resources[$declare] = $instance;
                    continue;
                }

                self::$modules[$declare] = $instance;

                if (!in_array($declare, $enabled)) {
                    continue;
                }

                $mods = $instance->getModules();

                if (!empty($mods)) {

                    $name = $instance->getName();

                    if (!$name) {
                        $name = strtolower(preg_replace('/\W/', '.', $declare));
                    }

                    foreach ($mods as $key => $controllers) {

                        $controllers = array_map(function ($val) {
                            if (self::getTpVer() == 5) {
                                return Loader::parseName($val);
                            } else {
                                return Str::studly($val);
                            }
                        }, $controllers);

                        self::$bindModules[strtolower($key)][] = [
                            'name' => $name, 'controlers' => $controllers,
                            'namespace_map' => $instance->getNameSpaceMap(), 'classname' => $declare,
                        ];
                    }
                }

                continue;
            }

            continue;
        }
    }

    public static function getTpVer()
    {
        if (empty(self::$tpVer)) {
            self::$tpVer = strstr(App::VERSION, '.', true);
        }

        return self::$tpVer;
    }

    public static function isTP51()
    {
        return self::getTpVer() == 5;
    }

    public static function isTP60()
    {
        return self::getTpVer() == 6;
    }

    public static function getInstalled($reget = false)
    {

        $type = Db::getConfig('default', 'mysql');

        $connections = Db::getConfig('connections');

        $config = $connections[$type] ?? [];

        if (empty($config) || empty($config['database'])) {
            return [];
        }

        if ($config['database'] == 'test' && $config['username'] == 'username' && $config['password'] == 'password') {
            return [];
        }

        $tableName = $config['prefix'] . 'extension';

        $isTable = Db::query("SHOW TABLES LIKE '{$tableName}'");

        if (empty($isTable)) {
            cache('installedExtensions', null);

            TpextCore::getInstance()->install();

            return [];
        }

        $data = cache('installed_extensions');

        if (!$reget && $data) {
            return $data;
        }

        $list = ExtensionModel::where(['install' => 1])->select();

        cache('installed_extensions', $list);

        return $list;
    }

    public static function clearCache()
    {
        cache('tpext_modules', null);
        cache('tpext_resources', null);
        cache('tpext_bind_modules', null);
    }
}
