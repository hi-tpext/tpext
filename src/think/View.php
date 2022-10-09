<?php

namespace tpext\think;

use think\Response;
use think\Template;
use think\helper\Str;

class View extends Response
{
    protected $vars = [];

    protected static $shareVars = [];

    protected $isContent = false;

    /**
     * Undocumented variable
     *
     * @var \think\App;
     */
    protected $app;

    /**
     * Undocumented variable
     *
     * @var Template
     */
    protected $engine;

    protected $config = [
        'auto_rule'     => 1,
        'view_dir_name' => 'view',
        'view_path'     => '',
        'view_suffix'   => 'html',
        'view_depr'     => DIRECTORY_SEPARATOR,
        'tpl_cache'     => true,
    ];

    public function __construct($data = '', $vars = [], $config = [])
    {
        $this->data = $data;
        $this->vars = $vars;

        $this->app = app();

        $this->config['cache_path'] = $this->app->getRuntimePath() . 'temp' . DIRECTORY_SEPARATOR;
        $this->config['view_path'] = $this->app->getAppPath() . 'view' . DIRECTORY_SEPARATOR;

        $this->engine = new Template(array_merge($this->config, $config));
        $this->engine->setCache($this->app->cache);
    }

    protected function output($data = '')
    {
        return $this->fetch($data);
    }

    public function isContent($content = true)
    {
        $this->isContent = $content;
        return $this;
    }

    public function assign($name, $value = '')
    {
        if (is_array($name)) {
            $this->vars = array_merge($this->vars, $name);
        } else {
            $this->vars[$name] = $value;
        }

        return $this;
    }

    public static function share($name, $value = '')
    {
        if (is_array($name)) {
            self::$shareVars = array_merge(self::$shareVars, $name);
        } else {
            self::$shareVars[$name] = $value;
        }

        \think\facade\View::assign($name, $value);
    }

    public function clear()
    {
        self::$shareVars  = [];
        $this->data = [];
        $this->vars = [];

        return $this;
    }

    protected function fetch($template = '')
    {
        ob_start();

        if (PHP_VERSION > 8.0) {
            ob_implicit_flush(false);
        } else {
            ob_implicit_flush(0);
        }

        $vars = array_merge(self::$shareVars, $this->vars);

        try {
            if ($this->isContent) {
                $this->engine->display($template, $vars);
            } else {
                if ('' == pathinfo($template, PATHINFO_EXTENSION)) {
                    // 获取模板文件名
                    $template = $this->parseTemplate($template);
                }

                $this->engine->fetch($template, $vars);
            }
        } catch (\Exception $e) {
            ob_end_clean();
            throw $e;
        }

        $content = ob_get_clean();

        return $content;
    }

    /**
     * 自动定位模板文件
     * @access private
     * @param  string $template 模板文件规则
     * @return string
     */
    private function parseTemplate(string $template): string
    {
        // 分析模板文件规则
        $request = $this->app['request'];

        // 获取视图根目录
        if (strpos($template, '@')) {
            // 跨模块调用
            list($app, $template) = explode('@', $template);
        }

        if (isset($app)) {
            $view     = $this->config['view_dir_name'];
            $viewPath = $this->app->getBasePath() . $app . DIRECTORY_SEPARATOR . $view . DIRECTORY_SEPARATOR;

            if (is_dir($viewPath)) {
                $path = $viewPath;
            } else {
                $path = $this->app->getRootPath() . $view . DIRECTORY_SEPARATOR . $app . DIRECTORY_SEPARATOR;
            }

            $this->template->view_path = $path;
        } else {
            $path = $this->config['view_path'];
        }

        $depr = $this->config['view_depr'];

        if (0 !== strpos($template, '/')) {
            $template   = str_replace(['/', ':'], $depr, $template);
            $controller = $request->controller();

            if (strpos($controller, '.')) {
                $pos        = strrpos($controller, '.');
                $controller = substr($controller, 0, $pos) . '.' . Str::snake(substr($controller, $pos + 1));
            } else {
                $controller = Str::snake($controller);
            }

            if ($controller) {
                if ('' == $template) {
                    // 如果模板文件名为空 按照默认模板渲染规则定位
                    if (2 == $this->config['auto_rule']) {
                        $template = $request->action(true);
                    } elseif (3 == $this->config['auto_rule']) {
                        $template = $request->action();
                    } else {
                        $template = Str::snake($request->action());
                    }

                    $template = str_replace('.', DIRECTORY_SEPARATOR, $controller) . $depr . $template;
                } elseif (false === strpos($template, $depr)) {
                    $template = str_replace('.', DIRECTORY_SEPARATOR, $controller) . $depr . $template;
                }
            }
        } else {
            $template = str_replace(['/', ':'], $depr, substr($template, 1));
        }

        return $path . ltrim($template, '/') . '.' . ltrim($this->config['view_suffix'], '.');
    }
}
