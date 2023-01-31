<?php

namespace tpext\common;

use think\facade\Db;
use think\App;
use think\helper\Str;
use think\facade\Cache;
use think\facade\Event;
use tpext\common\model\Extension as ExtensionModel;

class ExtLoader
{
    /**
     * Undocumented variable
     *
     * @var string[]
     */
    private static $classMap = [];

    /**
     * Undocumented variable
     *
     * @var Module[]
     */
    private static $modules = [];

    /**
     * Undocumented variable
     *
     * @var Resource[]
     */
    private static $resources = [];

    /**
     * Undocumented variable
     *
     * @var array
     */
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
     * @return string[]
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
     * @param array $class
     * @return void
     */
    public static function bindModules($class)
    {
        self::$bindModules = array_merge(self::$bindModules, $class);
    }

    /**
     * Undocumented function
     *
     * @return array
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
        Event::listen($name, $class, $first);
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
        Event::trigger($name, $params, $once);
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
            self::$modules = Cache::get('tpext_modules') ?: [];
            self::$resources = Cache::get('tpext_resources') ?: [];
            self::$bindModules = Cache::get('tpext_bind_modules') ?: [];

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
            Cache::set('tpext_modules', self::$modules);
            Cache::set('tpext_resources', self::$resources);
            Cache::set('tpext_bind_modules', self::$bindModules);
        }

        foreach (self::$modules as $k => $m) {
            if (in_array($k, $enabled)) {
                $m->loaded();
                self::trigger('tpext_extension_loaded_' . $k);
            }
        }

        foreach (self::$resources as $k => $r) {
            if (in_array($k, $enabled)) {
                $r->loaded();
                self::trigger('tpext_extension_loaded_' . $k);
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

                if ($instance instanceof Module) {

                    self::$modules[$declare] = $instance;

                    if (count($enabled) > 1 && !in_array($declare, $enabled)) {
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
                                return Str::studly($val);
                            }, $controllers);

                            self::$bindModules[strtolower($key)][] = [
                                'name' => $name,
                                'controllers' => $controllers,
                                'controlers' => $controllers, //兼容
                                'namespace_map' => $instance->getNameSpaceMap(),
                                'classname' => $declare,
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

    public static function isWebman()
    {
        return false;
    }

    public static function getInstalled($reget = false)
    {

        $type = Db::getConfig('default', 'mysql');

        $connections = Db::getConfig('connections');

        $config = $connections[$type] ?? [];

        if (empty($config) || empty($config['database'])) {
            return [];
        }

        if ($config['database'] == '' && $config['username'] == 'root' && $config['password'] == '') {
            return [];
        }

        if ($config['database'] == 'test' && $config['username'] == 'username' && $config['password'] == 'password') {
            return [];
        }

        $tableName = $config['prefix'] . 'extension';

        $isTable = Db::query("SHOW TABLES LIKE '{$tableName}'");

        if (empty($isTable)) {
            Cache::set('tpext_installed_extensions', null);

            TpextCore::getInstance()->install();

            return [];
        }

        $data = Cache::get('tpext_installed_extensions');

        if (!$reget && $data) {
            return $data;
        }

        $list = ExtensionModel::where(['install' => 1])->select();

        Cache::set('tpext_installed_extensions', $list);

        return $list;
    }

    /**
     * Undocumented function
     *
     * @param boolean $clearInstance 是否清除ExtLoader中已发现的实列
     * @return void
     */
    public static function clearCache($clearInstance = false)
    {
        Cache::delete('tpext_modules');
        Cache::delete('tpext_resources');
        Cache::delete('tpext_bind_modules');

        if ($clearInstance) {
            self::$modules = [];
            self::$resources = [];
            self::$bindModules = [];
        }
    }
}
