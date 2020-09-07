<?php

namespace tpext\behavior;

use think\facade\App;
use think\facade\Route;
use think\Loader;
use think\route\dispatch\Url;
use tpext\common\ExtLoader;
use tpext\common\Tool;

class AppDispatch
{
    public function run()
    {
        $dispatch = App::routeCheck();

        if ($dispatch instanceof Url) {

            $url = $dispatch->getDispatch();

            if (is_string($url)) {

                $this->cherckModule($url, $dispatch);
            }
        }
    }

    private function cherckModule($url, $dispatch)
    {
        $auto_bind_module = config('auto_bind_module');

        if ($auto_bind_module) {
            $script = pathinfo($_SERVER['SCRIPT_FILENAME'], PATHINFO_FILENAME);
            $url = $url ? $script . '|' . $url : $script;
        }

        $result = explode('|', $url);

        $extension = isset($result[0]) ? $result[0] : '';

        $module = isset($result[1]) && !empty($result[1]) ? $result[1] : '';

        $controller = isset($result[2]) && !empty($result[2]) ? $result[2] : '';

        $action = isset($result[3]) && !empty($result[3]) ? $result[3] : '';

        $matchMod = null;

        if (empty($module)) {
            $module = config('default_controller');

            $controller = config('default_action');

            $url = [$extension, $module, $controller];
        } else if (empty($controller)) {
            $controller = config('default_action');

            $url = [$extension, $module, $controller];
        } else {
            $url = $result;
        }

        if ($extension == 'ext') {

            //http://localhost/ext/home/hello/say/name/2334

            $matchMod = $this->matchModule($module, $controller, $action, false);

            array_shift($url);
        } else {
            $modules = ExtLoader::getModules();

            foreach ($modules as $name => $intance) {

                if (!class_exists($name)) {
                    continue;
                }

                $name = $intance->getName();

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

            Route::setConfig(['empty_module' => $url[0]]);

            if ($auto_bind_module) {
                array_shift($url);
                $urlDispatch = implode($pathinfo_depr, $url);
            } else {
                $urlDispatch = implode($pathinfo_depr, $url);
            }

            $newDispatch = Route::check($urlDispatch, false);

            App::path($matchMod['rootPath']);

            App::init('');

            $instance = $matchMod['classname']::getInstance();

            $instance->extInit($matchMod);

            ExtLoader::trigger('tpext_match_module', [$matchMod, $url[0]]);

            App::dispatch($newDispatch->init());
        } else {
            App::dispatch($dispatch->init());
        }
    }

    private function matchModule($module, $controller, $action, $ext = true)
    {
        $controller = $controller ? $controller : config('app.empty_controller');

        $controller = Loader::parseName($controller);

        $action = $action ? $action : config('app.default_action');

        $bindModules = ExtLoader::getBindModules();

        $matchMod = null;

        foreach ($bindModules as $key => $bindModule) {

            if (empty($bindModule)) {

                continue;
            }

            if ($matchMod) {
                break;
            }

            if ($module == $key) {

                foreach ($bindModule as $mod) {

                    if (!empty($mod['controlers']) && in_array($controller, $mod['controlers'])) {

                        $mod = $this->checkAction($mod, $module, $controller, $action, $ext, $mod['classname']);

                        if ($mod != null) {

                            $matchMod = $mod;
                            break;
                        }
                    }
                }
            }
        }

        return $matchMod;
    }

    private function checkAction($mod, $module, $controller, $action, $ext = true, $className = '')
    {
        $namespaceMap = $mod['namespace_map'];

        if (empty($namespaceMap) || count($namespaceMap) != 2) {
            $namespaceMap = Tool::getNameSpaceMap($className);
        }

        if (empty($namespaceMap)) {
            return null;
        }

        $namespace = rtrim($namespaceMap[0], '\\');

        $rootpath = $namespaceMap[1];

        $url_controller_layer = 'controller';

        $class = '\\' . $module . '\\' . $url_controller_layer . '\\' . Loader::parseName($controller, 1);

        if (!class_exists($namespace . $class)) {
            return null;
        }

        $reflectionClass = new \ReflectionClass($namespace . $class);

        if (!$reflectionClass->hasMethod($action) && !$ext) {
            return null;
        }

        Loader::addClassAlias('app' . $class, $namespace . $class);

        Loader::autoload('app' . $class);

        $mod['namespace'] = $namespace;

        $mod['rootPath'] = $rootpath;

        return $mod;
    }
}
