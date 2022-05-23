<?php

namespace tpext\common;

use think\App;
use think\Db;
use think\facade\Event;
use think\facade\Hook;
use think\helper\Str;
use think\Loader;
use tpext\common\model\Extension as ExtensionModel;

class ExtLoader
{
    /**
     * Undocumented variable
     *
     * @var Module[]|Resource[]
     */
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

    /**
     * Undocumented function
     *
     * @return Module[]|Resource[]
     */
    public static function getClassMap()
    {
        return self::$classMap;
    }

    /**
     * Undocumented function
     *
     * @param Module|array $class
     * @return void
     */
    public static function addModules($class)
    {
        if (is_array($class)) {
            self::$modules = array_merge(self::$modules, $class);
        } else {
            self::$modules[] = $class;
        }
    }

    /**
     * Undocumented function
     *
     * @param Resource|array $class
     * @return void
     */
    public static function addResources($class)
    {
        if (is_array($class)) {
            self::$resources = array_merge(self::$resources, $class);
        } else {
            self::$resources[] = $class;
        }
    }

    /**
     * Undocumented function
     *
     * @return Module[]
     */
    public static function getModules()
    {
        return self::$modules;
    }

    /**
     * Undocumented function
     *
     * @return Resource[]
     */
    public static function getResources()
    {
        return self::$resources;
    }

    /**
     * Undocumented function
     *
     * @return Resource[]|Module[]
     */
    public static function getExtensions()
    {
        return array_merge(self::$modules, self::$resources);
    }

    /**
     * Undocumented function
     *
     * @param Module|array $class
     * @return void
     */
    public static function bindModules($class)
    {
        if (is_array($class)) {
            self::$bindModules = array_merge(self::$bindModules, $class);
        } else {
            self::$bindModules[] = $class;
        }
    }

    /**
     * Undocumented function
     *
     * @return Module[]
     */
    public static function getBindModules()
    {
        return self::$bindModules;
    }

    /**
     * Undocumented function
     *
     * @param string $name
     * @param mixed $class
     * @param boolean $first
     * @param string $desc
     * @return void
     */
    public static function watch($name, $class, $first = false, $desc = '')
    {
        if (!isset(self::$watches[$name])) {
            self::$watches[$name] = [];
        }
        self::$watches[$name][] = [$class, $desc, $first];
        if (self::isTP51()) {
            Hook::add($name, $class, $first);
        } else {
            Event::listen($name, $class, $first);
        }
    }

    /**
     * Undocumented function
     *
     * @param string $name
     * @param mixed $params
     * @param boolean $once
     * @return void
     */
    public static function trigger($name, $params = null, $once = false)
    {
        if (self::isTP51()) {
            Hook::listen($name, $params, $once);
        } else {
            Event::trigger($name, $params, $once);
        }
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    public static function geWatches()
    {
        return self::$watches;
    }

    public static function bindExtensions()
    {
        if (!config('app_debug')) {
            self::$modules = cache('tpext_modules') ?: [];
            self::$resources = cache('tpext_resources') ?: [];
            self::$bindModules = cache('tpext_bind_modules') ?: [];

            foreach (self::$modules as $k => $m) {
                if (!class_exists($k, false)) {
                    unset(self::$modules[$k]);
                }
            }

            foreach (self::$resources as $k => $r) {
                if (!class_exists($k, false)) {
                    unset(self::$resources[$k]);
                }
            }
        }

        $installed = self::getInstalled();

        $enabled = [];
        $disabled = [];
        foreach ($installed as $ins) {
            if ($ins['enable'] == 1) {
                $enabled[] = $ins['key'];
            } else {
                $disabled[] = $ins['key'];
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

                if (count($enabled) > 1 && !in_array($declare, $enabled)) {
                    continue;
                }

                if ($instance instanceof Resource) {
                    self::$resources[$declare] = $instance;
                    continue;
                }

                if ($instance instanceof Module) {

                    self::$modules[$declare] = $instance;
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
                }
            }
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
        if (empty(config('database.database'))) {
            return [];
        }

        $tableName = config('database.prefix') . 'extension';

        $isTable = Db::query("SHOW TABLES LIKE '{$tableName}'");

        if (empty($isTable)) {
            cache('tpext_installed_extensions', null);

            TpextCore::getInstance()->install();

            return [];
        }

        $data = cache('tpext_installed_extensions');

        if (!$reget && $data) {
            return $data;
        }

        $list = ExtensionModel::where(['install' => 1])->select();

        cache('tpext_installed_extensions', $list);

        return $list;
    }

    public static function clearCache()
    {
        cache('tpext_modules', null);
        cache('tpext_resources', null);
        cache('tpext_bind_modules', null);
    }
}
