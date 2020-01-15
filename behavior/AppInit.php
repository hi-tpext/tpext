<?php

namespace tpext\behavior;

use think\facade\Env;
use think\facade\Hook;
use tpext\behavior;
use tpext\common\Loader;
use tpext\common\Module;
use tpext\common\Plugin;

class AppInit
{
    private $modules = [];

    private $bindModules = [];

    private $plugins = [];

    public function run()
    {
        Hook::add('app_dispatch', behavior\AppDispatch::class);
        Hook::add('module_init', behavior\ModuleInit::class);
        Hook::add('action_begin', behavior\ActionBegin::class);

        $this->bindExtensions();
    }

    protected function bindExtensions()
    {
        $initedClassMap = Loader::getClassMap();

        $this->findExtensions($initedClassMap);

        $composerPath = Env::get('root_path') . 'vendor' . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR;

        if (is_file($composerPath . 'autoload_classmap.php')) {
            $classMap = require $composerPath . 'autoload_classmap.php';

            if ($classMap && is_array($classMap)) {

                $classMap = array_keys($classMap);

                $this->findExtensions($classMap);
            }
        }

        Loader::addModules($this->modules);

        Loader::addModules($this->plugins);

        Loader::bindModules($this->bindModules);

        Hook::listen('tpext_modules_loaded');
    }

    private function findExtensions($classMap)
    {
        foreach ($classMap as $declare) {

            if (preg_match('/^think\\\.+/i', $declare)) {
                continue;
            }

            if (preg_match('/^tpext\\\common\\\.+/i', $declare)) {
                continue;
            }

            if (!class_exists($declare)) {
                continue;
            }

            $reflectionClass = new \ReflectionClass($declare);

            if (!$reflectionClass->isInstantiable()) {
                continue;
            }

            if (!isset($this->modules[$declare]) && $reflectionClass->hasMethod('moduleInit')) {

                $instance = $reflectionClass->newInstance();

                if (!($instance instanceof Module)) {
                    continue;
                }

                $name = $instance->getName();

                if (!$name) {
                    $name = $declare->getNameSpace();
                }

                $this->modules[$declare] = $name;

                $instance->moduleInit();

                $mods = $instance->getModules();

                if (!empty($mods)) {

                    foreach ($mods as $key => $controllers) {

                        foreach ($controllers as $contr) {
                            $this->bindModules[strtolower($key)][] = ['name' => $name, 'controler' => $contr,
                                'namespace_map' => $instance->getNameSpaceMap(), 'classname' => $declare];
                        }
                    }
                }
            }

            if (!isset($this->plugins[$declare]) && $reflectionClass->hasMethod('pluginInit')) {

                $instance = $reflectionClass->newInstance();

                if (!($instance instanceof Plugin)) {
                    continue;
                }

                $name = $instance->getName();

                if (!$name) {
                    $name = $declare->getNameSpace();
                }

                $this->plugins[$declare] = $name;

                $instance->pluginInit();
            }
        }
    }
}
