<?php

namespace tpext\common\middleware;

use Closure;
use think\App;
use think\helper\Str;
use think\Request;
use think\Response;
use tpext\common\ExtLoader;
use tpext\common\Tool;

/**
 * for tp6
 */

class AppRun
{
    /** @var App */
    protected $app;

    /**
     * 请求对象
     * @var Request
     */
    protected $request;

    /**
     * 应用名称
     * @var string
     */
    protected $name;

    /**
     * 应用名称
     * @var string
     */
    protected $appName;

    /**
     * 应用路径
     * @var string
     */
    protected $path;

    public function __construct(App $app)
    {
        $this->app = $app;
        $this->name = $this->app->http->getName();
        $this->path = $this->app->http->getPath();
        $this->request = $app->request;
    }

    /**
     * 多模块解析
     * @access public
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle($request, Closure $next)
    {
        if (!$this->parseModule()) {
            return $next($request);
        }

        return $this->app->middleware->pipeline('tpext')
            ->send($request)
            ->then(function ($request) use ($next) {
                return $next($request);
            });
    }

    protected function parseModule(): bool
    {
        $scriptName = $this->getScriptName();
        $module_bind = '';

        if ($this->name || ($scriptName && !in_array($scriptName, ['index', 'router', 'think']))) {

        } else {
            // 自动多应用识别
            $this->app->http->setBind(false);

            $bind = $this->app->route->getDomainBind(); //绑定到模块　Route::bind('admin');
            $module_bind = $this->app->config->get('app.domain_bind', []);

            if (!empty($bind)) {
                // 获取当前子域名
                $subDomain = $this->app->request->subDomain();
                $domain = $this->app->request->host(true);

                if (isset($bind[$domain])) {
                    $module_bind = $bind[$domain];
                } elseif (isset($bind[$subDomain])) {
                    $module_bind = $bind[$subDomain];
                } elseif (isset($bind['*'])) {
                    $module_bind = $bind['*'];
                }
            } else {
                $module_bind = $this->app->route->getDomainBind(); //绑定到模块　Route::bind('admin');
            }
        }

        $url = str_replace(config('route.pathinfo_depr'), '|', $this->path());

        if ($this->cherckModule($url, $module_bind)) {
            return true;
        }

        return false;
    }

    /**
     * 获取当前请求URL的pathinfo信息(不含URL后缀)
     * @access protected
     * @return string
     */
    protected function path(): string
    {
        $suffix = config('route.url_html_suffix');
        $pathinfo = $this->request->pathinfo();

        if (false === $suffix) {
            // 禁止伪静态访问
            $path = $pathinfo;
        } elseif ($suffix) {
            // 去除正常的URL后缀
            $path = preg_replace('/\.(' . ltrim($suffix, '.') . ')$/i', '', $pathinfo);
        } else {
            // 允许任何后缀访问
            $path = preg_replace('/\.' . $this->request->ext() . '$/i', '', $pathinfo);
        }

        return $path;
    }

    /**
     * 获取当前运行入口名称
     * @access protected
     * @codeCoverageIgnore
     * @return string
     */
    protected function getScriptName(): string
    {
        if (isset($_SERVER['SCRIPT_FILENAME'])) {
            $file = $_SERVER['SCRIPT_FILENAME'];
        } elseif (isset($_SERVER['argv'][0])) {
            $file = realpath($_SERVER['argv'][0]);
        }

        return isset($file) ? pathinfo($file, PATHINFO_FILENAME) : '';
    }

    /**
     * Undocumented function
     *
     * @param string $url
     * @param string $bind
     * @return boolean
     */
    private function cherckModule($url, $bind)
    {
        if ($bind) {
            $url = $url ? $bind . '|' . $url : $bind;
        }

        $url = strtolower($url);

        $result = explode('|', $url);

        $extension = isset($result[0]) ? $result[0] : '';

        $module = isset($result[1]) && !empty($result[1]) ? $result[1] : '';

        $controller = isset($result[2]) && !empty($result[2]) ? $result[2] : '';

        $action = isset($result[3]) && !empty($result[3]) ? $result[3] : '';

        $extra = isset($result[4]) && !empty($result[4]) ? $result[4] : '';

        $matchMod = null;

        if (empty($module)) {
            $module = config('route.default_controller');

            $controller = config('route.default_action');

            $url = [$extension, $module, $controller];
        } else if (empty($controller)) {
            $controller = config('route.default_action');

            $url = [$extension, $module, $controller];
        } else {
            $url = $result;
        }

        if ($extension == 'ext' || ($bind && $module == 'ext')) {
            //http://localhost/ext/home/hello/say/name/2334

            if ($bind && $controller == $bind) {
                unset($url[0]);

                $url = array_values($url);

                $matchMod = $this->matchModule($controller, $action, $extra, false);
            } else {
                $matchMod = $this->matchModule($module, $controller, $action, false);
            }

            array_shift($url);

        } else {
            $modules = ExtLoader::getModules();

            foreach ($modules as $name => $intance) {

                if (!class_exists($name)) {
                    continue;
                }

                $name = $intance->getName();

                if ($name == $extension || strtolower(preg_replace('/\W/', '', $name)) == $extension
                    || ($bind && ($name == $module || strtolower(preg_replace('/\W/', '', $name)) == $module))) {

                    //http://localhost/tpexthelloworldmodule/home/hello/say/name/2334
                    if ($bind && $controller == $bind) {
                        unset($url[0]);

                        $url = array_values($url);

                        $matchMod = $this->matchModule($controller, $action, $extra, true);
                        if ($matchMod) {
                            $this->app->request->setPathinfo("{$controller}/{$action}/{$extra}");
                        }

                    } else {
                        $matchMod = $this->matchModule($module, $controller, $action, true);
                        if ($matchMod) {
                            $this->app->request->setPathinfo("{$module}/{$controller}/{$action}");
                        }
                    }

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

            $path = $this->app->request->pathinfo();

            $this->app->setAppPath($matchMod['rootPath'] . DIRECTORY_SEPARATOR . $url[0]);

            $this->app->http->path($matchMod['rootPath'] . DIRECTORY_SEPARATOR . $url[0]);

            if (is_file($matchMod['rootPath'] . DIRECTORY_SEPARATOR . 'common.php')) {
                include_once $matchMod['rootPath'] . DIRECTORY_SEPARATOR . 'common.php';
            }

            $this->app->setNamespace($matchMod['namespace']);

            $this->app->http->name($url[0]);

            $this->app->request->setRoot('/' . $url[0]);

            $this->app->request->setPathinfo(strpos($path, '/') ? ltrim(strstr($path, '/'), '/') : '');

            $instance = $matchMod['classname']::getInstance();

            $instance->extInit($matchMod);

            ExtLoader::trigger('tpext_match_module', [$matchMod, $url[0]]);

            return true;
        } else {
            return false;
        }
    }

    private function matchModule($module, $controller, $action, $ext = true)
    {
        $controller = $controller ? $controller : config('route.empty_controller');

        $controller = Str::studly($controller);

        $action = $action ? $action : config('route.default_action');

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

        $class = '\\' . $module . '\\' . $url_controller_layer . '\\' . Str::studly($controller);

        if (!class_exists($namespace . $class)) {
            return null;
        }

        $reflectionClass = new \ReflectionClass($namespace . $class);

        if (!$reflectionClass->hasMethod($action) && !$ext) {
            return null;
        }

        class_alias($namespace . $class, 'app' . $class);

        $this->app->bind([
            'app' . $class => $namespace . $class,
        ]);

        $mod['namespace'] = $namespace;

        $mod['rootPath'] = $rootpath;

        return $mod;
    }
}
