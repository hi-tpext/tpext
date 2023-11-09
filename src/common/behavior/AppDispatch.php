<?php

namespace tpext\common\behavior;

use think\facade\App;
use think\facade\Route;
use think\Loader;
use tpext\common\ExtLoader;
use tpext\common\Tool;
use think\route\dispatch\Url as UrlDispatch;

/**
 * for tp5
 */

class AppDispatch
{
    public function run()
    {
        $dispatch = App::routeCheck();

        if ($dispatch instanceof UrlDispatch) {

            $url = $dispatch->getDispatch();

            if (is_string($url)) {

                $this->cherckModule($url, $dispatch);
            } else {
                App::dispatch($dispatch->init());
            }
        } else {
            App::dispatch($dispatch->init());
        }
    }

    private function cherckModule($url, $dispatch)
    {
        $auto_bind_module = config('auto_bind_module');
        $bind = '';

        if ($auto_bind_module) {
            $bind = pathinfo($_SERVER['SCRIPT_FILENAME'], PATHINFO_FILENAME);
        } else {
            $bind = Route::getBind();
        }

        if ($bind) {
            $url = $url ? $bind . '|' . $url : $bind;
        }

        $result = explode('|', $url);

        if (count($result) <= 3) {
            $module = isset($result[0]) && !empty($result[0]) ? $result[0] : config('default_module');
            $controller = isset($result[1]) && !empty($result[1]) ? $result[1] : config('default_controller');
            $action = isset($result[2]) && !empty($result[2]) ? $result[2] : config('default_action');

            $result = [$module, $controller, $action];
        } else {
            [$module, $controller, $action] = $result;
        }

        $module = strip_tags($module);
        $controller = Loader::parseName(strip_tags($controller), 1);
        $action = strip_tags($action);

        $matchMod = $this->matchModule($module, $controller, $action, false);

        if ($matchMod) {

            if ($bind) {
                array_shift($result);
            }

            $urlDispatch = implode('|', $result);

            $newDispatch  = new UrlDispatch(app('request'), Route::getGroup(), $urlDispatch, [
                'auto_search' => true,
            ]);

            App::path($matchMod['rootPath']);

            App::setNamespace($matchMod['namespace']);

            App::init('');

            $instance = $matchMod['classname']::getInstance();

            $instance->extInit($matchMod);

            ExtLoader::trigger('tpext_match_module', [$matchMod, $module]);

            App::dispatch($newDispatch->init());
        } else {
            App::dispatch($dispatch->init());
        }
    }

    private function matchModule($module, $controller, $action, $ext = true)
    {
        $appClassExists = self::appClassExists($module, $controller);

        if ($appClassExists) {
            $reflectionAppClass = new \ReflectionClass($appClassExists);
            
            if ($reflectionAppClass && $reflectionAppClass->hasMethod($action)) { //app目录下的模块控制器方法优先于扩展中的方法
                return null;
            }
        }

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

                    if (!empty($mod['controllers']) && in_array($controller, $mod['controllers'])) {

                        $mod = $this->checkAction($mod, $module, $controller, $action, $ext);

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

    private function checkAction($mod, $module, $controller, $action, $ext = true)
    {
        $namespaceMap = $mod['namespace_map'];

        if (empty($namespaceMap) || count($namespaceMap) != 2) {
            $namespaceMap = Tool::getNameSpaceMap($mod['classname']);
        }

        if (empty($namespaceMap)) {
            return null;
        }

        $namespace = rtrim($namespaceMap[0], '\\');

        $rootpath = $namespaceMap[1];

        $url_controller_layer = 'controller';

        $class = '\\' . $module . '\\' . $url_controller_layer . '\\' . $controller;

        if (!class_exists($namespace . $class)) {
            return null;
        }

        $reflectionClass = new \ReflectionClass($namespace . $class);

        if (!$reflectionClass->hasMethod($action) && !$ext) {
            return null;
        }

        $mod['namespace'] = $namespace;

        $mod['rootPath'] = $rootpath;

        return $mod;
    }

    private static function appClassExists($module, $controller)
    {
        $controller_class = "app\\{$module}\\controller\\" . $controller;

        if (class_exists($controller_class)) {
            return $controller_class;
        }

        return false;
    }
}
