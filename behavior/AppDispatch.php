<?php

namespace tpext\behavior;

use think\facade\App;
use think\facade\Env;
use think\facade\Hook;
use think\facade\Route;
use think\Loader as Tploader;
use think\route\dispatch\Url;
use tpext\common\Loader;

class AppDispatch
{
    public function run()
    {
        $route = App::routeCheck();

        if ($route instanceof Url) {

            $dispatch = $route->getDispatch();

            if (is_string($dispatch)) {

                $this->cherckModule($dispatch);
            }
        }
    }

    private function cherckModule($dispatch)
    {
        $result = explode('|', $dispatch);

        $extension = isset($result[0]) ? strtolower($result[0]) : '';

        $module = isset($result[1]) ? strtolower($result[1]) : '';

        $controller = isset($result[2]) ? strtolower($result[2]) : '';

        $action = isset($result[3]) ? strtolower($result[3]) : '';

        $matchMod = null;
        $url = $result;

        if ($extension == 'ext') {

            //http://localhost/ext/home/hello/say/name/2334

            $matchMod = $this->matchModule($module, $controller, $action, false);

            array_shift($url);

        } else {
            $modules = Loader::getModules();

            foreach ($modules as $name) {

                if ($name == $extension || strtolower(preg_replace('/\W/', '', $name)) == $extension) {

                    //http://localhost/tpexthelloworldmodule/home/hello/say/name/2334

                    $matchMod = $this->matchModule($module, $controller, $action, true);

                    array_shift($url);

                    break;
                }
            }

            if (!$matchMod) {

                //http://localhost/home/hello/say/name/2334

                $matchMod = $this->matchModule($extension, $module, $controller, false);
            }
        }

        if ($matchMod) {

            $pathinfo_depr = config('app.pathinfo_depr');

            $urlDispatch = implode($pathinfo_depr, $url);

            Route::setConfig(['empty_module' => $url[0]]);

            Hook::listen('tpext_match_module', [$matchMod, $url[0]]);

            $newDispatch = Route::check($urlDispatch, false);

            App::dispatch($newDispatch->init());

            return;

        }
    }

    private function matchModule($module, $controller, $action, $ext = true)
    {
        $controller = $controller ? $controller : config('app.empty_controller');

        $action = $action ? $action : config('app.default_action');

        $bindModules = Loader::getBindModules();

        $matchMod = null;

        foreach ($bindModules as $key => $bindModule) {

            if (empty($bindModule)) {

                continue;
            }

            if ($module == $key) {

                foreach ($bindModule as $mod) {

                    if ($controller == $mod['controler']) {

                        if ($this->checkAction($mod['namespace_map'], $module, $mod['controler'], $action, $ext, $mod['classname'])) {

                            $matchMod = $mod;
                        }
                    }
                }
            }
        }

        return $matchMod;
    }

    private function checkAction($namespaceMap, $module, $controller, $action, $ext = true, $className = '')
    {
        if (empty($namespaceMap) || count($namespaceMap) != 2) {
            $namespaceMap = $this->getNameSpaceMap($className);
        }

        if (empty($namespaceMap)) {
            return false;
        }

        $namespace = rtrim($namespaceMap[0], '\\');

        $rootpath = $namespaceMap[1];

        $url_controller_layer = config('app.url_controller_layer');

        $class = '\\' . $module . '\\' . $url_controller_layer . '\\' . ucfirst($controller);

        if (!class_exists($namespace . $class)) {

            return false;
        }

        $reflectionClass = new \ReflectionClass($namespace . $class);

        if (!$reflectionClass->hasMethod($action) && !$ext) {
            return false;
        }

        App::path($rootpath);

        TpLoader::addClassAlias('app' . $class, $namespace . $class);

        TpLoader::autoload('app' . $class);

        return true;
    }

    private function getNameSpaceMap($class)
    {
        $composerPath = Env::get('root_path') . 'vendor' . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR;

        if (is_file($composerPath . 'autoload_psr4.php')) {
            $map = require $composerPath . 'autoload_psr4.php';

            foreach ($map as $namespace => $paths) {

                if (false !== strpos(strtolower($class), strtolower($namespace))) {
                    return [$namespace, '..' . DIRECTORY_SEPARATOR . preg_replace('/.*[\/\\\](vendor[\/\\\].+$)/i', '$1', $paths[0])];
                }
            }
        }

        return [];
    }
}
