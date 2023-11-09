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
        $this->name = $this->app->http->getName();
        $this->path = $this->app->http->getPath();
        $this->request = $this->app->request;

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

            $module_bind = $this->app->config->get('app.domain_bind', []);

            if (!empty($module_bind)) {
                // 获取当前子域名
                $subDomain = $this->app->request->subDomain();
                $domain = $this->app->request->host(true);

                if (isset($module_bind[$domain])) {
                    $bind = $module_bind[$domain];
                } elseif (isset($module_bind[$subDomain])) {
                    $bind = $module_bind[$subDomain];
                } elseif (isset($module_bind['*'])) {
                    $bind = $module_bind['*'];
                }
            } else {
                $bind = $this->app->route->getDomainBind('-'); //绑定到模块　Route::bind('admin');
            }
        }

        $url = str_replace(config('route.pathinfo_depr'), '|', $this->path());

        if ($this->cherckModule($url, $bind)) {
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

        $result = explode('|', $url);

        $module = strip_tags(isset($result[0]) && !empty($result[0]) ? strtolower($result[0]) : config('app.default_app'));
        $controller = strip_tags(isset($result[1]) && !empty($result[1]) ? $result[1] : config('route.default_controller'));
        $action = strip_tags(isset($result[2]) && !empty($result[2]) ? $result[2] : config('route.default_action'));

        $controller = Str::studly($controller);

        $matchMod = $this->matchModule($module, $controller, $action, false);

        if ($module == 'admin') {
            $this->app->middleware->add(\think\middleware\SessionInit::class, 'app');
        }

        if ($matchMod) {

            $path = $this->app->request->pathinfo();

            $this->app->setAppPath($matchMod['rootPath'] . DIRECTORY_SEPARATOR . $module);

            $this->app->http->path($matchMod['rootPath'] . DIRECTORY_SEPARATOR . $module);

            if (is_file($matchMod['rootPath'] . DIRECTORY_SEPARATOR . 'common.php')) {
                include_once $matchMod['rootPath'] . DIRECTORY_SEPARATOR . 'common.php';
            }

            $this->app->config->set(['app_namespace' => $matchMod['namespace'] . '\\' . strtolower($module)], 'app');

            $this->app->http->name('');

            $this->app->request->setRoot('/' . $module);

            $this->request->setController(Str::studly($controller))->setAction($action);

            $this->app->request->setPathinfo($module . '/' . (strpos($path, '/') ? ltrim(strstr($path, '/'), '/') : ''));

            $instance = $matchMod['classname']::getInstance();

            $instance->extInit($matchMod);

            ExtLoader::trigger('tpext_match_module', [$matchMod, $module]);

            return true;
        } else {
            return false;
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
