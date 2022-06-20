<?php

namespace tpext\common;

use think\helper\Str;

/**
 * for webman
 */

class RouteLoader
{

    public function load($focusWrite = false)
    {
        $bindModules = ExtLoader::getBindModules();

        foreach ($bindModules as $key => $moduleInfo) {

            foreach ($moduleInfo as $mod) {

                foreach ($mod['controllers'] as $controller) {

                    $routes = $this->matchModule($mod, $key, $controller);

                    if (!empty($routes)) {
                        $routesGroup[$key][$controller] = $routes;
                    }
                }
            }
        }

        $this->witeToFile($routesGroup, $focusWrite);
    }

    /**
     * Undocumented function
     *
     * @param array $routesGroup
     * @param boolean $focusWrite
     * @return void
     */
    protected function witeToFile($routesGroup, $focusWrite)
    {
        $routeFile = config_path() . '/plugin/tpext/route.php';

        if (is_file($routeFile) && time() - filemtime($routeFile) < 60 && !$focusWrite) {
            return;
        }

        $lines = [];

        $lines[] = '<?php';
        $lines[] = '';
        $lines[] = '/**';
        $lines[] = ' *tpext 自动生成扩展路由，请不要手动修改.';
        $lines[] = ' *时间:' . date('Y-m-d H:i:s');
        $lines[] = ' */';
        $lines[] = '';
        $lines[] = 'use Webman\Route;';
        $lines[] = '';

        foreach ($routesGroup as $module => $controller) {
            $lines[] = "Route::group('/{$module}', function () {";

            foreach ($controller as  $infos) {

                foreach ($infos as $info) {

                    [$path, $classAction] = $info;
                    [$class, $action] = $classAction;

                    $lines[] = "    Route::any('{$path}', [{$class}::class, '{$action}']);";
                }
            }
            $lines[] = '});';

            $lines[] = '';
        }

        if (!is_dir(config_path() . '/plugin/tpext/')) {
            mkdir(config_path() . '/plugin/tpext/', '0777', true);
        }

        $routeFile = config_path() . '/plugin/tpext/route.php';

        file_put_contents($routeFile, implode(PHP_EOL, $lines));
    }

    /**
     * Undocumented function
     *
     * @param array $mod
     * @param string $module
     * @param string $controller
     * @return array
     */
    public function matchModule($mod, $module, $controller)
    {
        $namespaceMap = $mod['namespace_map'];

        if (empty($namespaceMap) || count($namespaceMap) != 2) {
            $namespaceMap = Tool::getNameSpaceMap($mod['classname']);
        }

        if (empty($namespaceMap)) {
            return;
        }

        $namespace = rtrim($namespaceMap[0], '\\');

        $class = '\\' . $module . '\\controller\\' . Str::studly($controller);

        if (!class_exists($namespace . $class)) {
            return;
        }

        $reflectionClass = new \ReflectionClass($namespace . $class);

        $reflectionAppClass = null;

        $appClassExists = $this->appClassExists($module, $controller);

        if ($appClassExists) {
            $reflectionAppClass = new \ReflectionClass($appClassExists);
        }

        $methods = $this->getMethods($reflectionClass);

        $routes = [];

        foreach ($methods as $method) {

            if ($reflectionAppClass && $reflectionAppClass->hasMethod($method->name)) { //app目录下的模块控制器方法优先于扩展中的方法
                continue;
            }

            $action = strtolower($method->name);
            $controller = strtolower($controller);

            if (in_array($action, ['beforeaction', 'afteraction'])) {
                continue;
            }

            if ($controller == 'index' && $action == 'index') {
                $routes[] = ["", [$namespace . $class, $action]];
                $routes[] = ["/", [$namespace . $class, $action]];
                $routes[] = ["/{$controller}", [$namespace . $class, $action]];
            }

            $routes[] = ["/{$controller}/{$action}", [$namespace . $class, $action]];
        }

        return $routes;
    }

    public function appClassExists($module, $controller)
    {
        $suffix = config('app.controller_suffix', '');

        $controller_class = "app\\{$module}\\controller\\$controller{$suffix}";

        if (class_exists($controller_class)) {
            return $controller_class;
        }

        $controller_class = Str::studly($controller_class);

        if (class_exists($controller_class)) {
            return $controller_class;
        }

        return false;
    }

    private function getMethods($reflection)
    {
        $methods = [];
        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            if ($method->class == $reflection->getName() && !in_array($method->name, ['__construct', '_initialize', 'initialize'])) {
                $methods[] = $method;
            }
        }

        return $methods;
    }
}
