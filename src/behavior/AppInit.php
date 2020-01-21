<?php

namespace tpext\behavior;

use think\facade\Env;
use think\facade\Hook;
use tpext\behavior;
use tpext\common\ExtLoader;
use tpext\common\Module as TpexModule;
use tpext\common\Plugin as TpexPlugin;
use tpext\common\TpextModule;

class AppInit
{
    private $modules = [];

    private $bindModules = [];

    private $plugins = [];

    public function run()
    {
        include realpath(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'common.php';

        ExtLoader::addClassMap(TpextModule::class);

        Hook::add('app_dispatch', behavior\AppDispatch::class);

        $this->bindExtensions();
    }

    protected function bindExtensions()
    {
        $initedClassMap = ExtLoader::getClassMap();

        $this->findExtensions($initedClassMap);

        $composerPath = Env::get('root_path') . 'vendor' . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR;

        if (is_file($composerPath . 'autoload_classmap.php')) {

            $classMap = require $composerPath . 'autoload_classmap.php';

            if ($classMap && is_array($classMap)) {

                $classMap = array_keys($classMap);

                $this->findExtensions($classMap);

            }
        }

        ExtLoader::addModules($this->modules);

        ExtLoader::addPlugins($this->plugins);

        ExtLoader::bindModules($this->bindModules);

        Hook::listen('tpext_modules_loaded');
    }

    private function passClasses($declare)
    {
        if (preg_match('/^think\\\.+/i', $declare)) {
            return true;
        }

        if ($declare != TpextModule::class && preg_match('/^tpext\\\common\\\.+/i', $declare)) {
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

            if (!isset($this->modules[$declare]) && $reflectionClass->hasMethod('moduleInit') && $reflectionClass->hasMethod('getInstance')) {

                $instance = $declare::getInstance();

                if (!($instance instanceof TpexModule)) {
                    continue;
                }

                $name = $instance->getName();

                if (!$name) {
                    $name = strtolower(preg_replace('/\W/', '.', $declare));
                }

                $this->modules[$declare] = $name;

                $mods = $instance->getModules();

                if (!empty($mods)) {

                    foreach ($mods as $key => $controllers) {

                        $controllers = array_map('strtolower', $controllers);

                        $this->bindModules[strtolower($key)][] = ['name' => $name, 'controlers' => $controllers,
                            'namespace_map' => $instance->getNameSpaceMap(), 'classname' => $declare];
                    }
                }

                continue;
            }

            if (!isset($this->plugins[$declare]) && $reflectionClass->hasMethod('pluginInit') && $reflectionClass->hasMethod('getInstance')) {

                $instance = $declare::getInstance();

                if (!($instance instanceof TpexPlugin)) {
                    continue;
                }

                $name = $instance->getName();

                if (!$name) {
                    $name = strtolower(preg_replace('/\W/', '.', $declare));
                }

                $this->plugins[$declare] = $name;

                $instance->pluginInit();
                $instance->autoCheck();
            }

            continue;
        }
    }
}
