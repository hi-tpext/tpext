<?php

namespace tpext\behavior;

use think\Loader;
use tpext\common\Extension;
use tpext\common\ExtLoader;
use tpext\common\Module;
use tpext\common\Resource;

class AppInit
{
    private $modules = [];
    private $resources = [];

    private $bindModules = [];

    public function run()
    {
        include realpath(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'common.php';

        ExtLoader::watch('app_dispatch', AppDispatch::class, true, 'tpext路由处理');

        if (!config('app_debug')) {
            $this->modules = cache('tpext_modules');
            $this->resources = cache('tpext_resources');
            $this->bindModules = cache('tpext_bind_modules');
        }

        if (!empty($this->modules)) {
            ExtLoader::addModules($this->modules);
            ExtLoader::bindModules($this->bindModules);
            ExtLoader::addResources($this->resources);
        } else {
            $this->bindExtensions();
        }

        ExtLoader::trigger('tpext_modules_loaded');
    }

    protected function bindExtensions()
    {
        $initedClassMap = ExtLoader::getClassMap();

        $this->findExtensions($initedClassMap);

        /*   $composerPath = Env::get('root_path') . 'vendor' . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR;

        if (is_file($composerPath . 'autoload_classmap.php')) {

        $classMap = require $composerPath . 'autoload_classmap.php';

        if ($classMap && is_array($classMap)) {

        $classMap = array_keys($classMap);

        $this->findExtensions($classMap);
        }
        }*/

        ExtLoader::addModules($this->modules);
        ExtLoader::addResources($this->resources);
        ExtLoader::bindModules($this->bindModules);

        if (!config('app_debug')) {
            cache('tpext_modules', $this->modules);
            cache('tpext_resources', $this->resources);
            cache('tpext_bind_modules', $this->bindModules);
        }
    }

    private function passClasses($declare)
    {
        if (in_array($declare, [Extension::class, Resource::class, Module::class])) {
            return true;
        }

        if (preg_match('/^think\\\.+/i', $declare)) {
            return true;
        }

        if (preg_match('/^PHPUnit\\\.+/i', $declare)) {
            return true;
        }

        if (preg_match('/^PHP_Token_.+/i', $declare)) {
            return true;
        }

        if (preg_match('/^PharIo\\\.+/i', $declare)) {
            return true;
        }

        if (preg_match('/^Symfony\\\.+/i', $declare)) {
            return true;
        }

        if (preg_match('/^phpDocumentor\\\.+/i', $declare)) {
            return true;
        }
    }

    private function findExtensions($classMap)
    {
        $installed = ExtLoader::getInstalled();

        $disabled = [];
        foreach ($installed as $ins) {
            if ($ins['install'] == 0 || $ins['enable'] == 0) {
                $disabled[] = $ins['key'];
            }
        }

        foreach ($classMap as $declare) {

            if ($this->passClasses($declare)) {
                continue;
            }

            if (!class_exists($declare)) {
                continue;
            }

            $reflectionClass = new \ReflectionClass($declare);

            if (!$reflectionClass->isInstantiable()) {
                continue;
            }

            if (!isset($this->modules[$declare]) && !isset($this->resources[$declare]) && $reflectionClass->hasMethod('extInit') && $reflectionClass->hasMethod('getInstance')) {

                $instance = $declare::getInstance();

                if (!($instance instanceof Extension)) {
                    continue;
                }

                if ($instance instanceof Resource) {
                    $this->resources[$declare] = $instance;
                    continue;
                }

                $this->modules[$declare] = $instance;

                if (in_array($declare, $disabled)) {
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
                            return Loader::parseName($val);
                        }, $controllers);

                        $this->bindModules[strtolower($key)][] = [
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
}
