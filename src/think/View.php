<?php

namespace tpext\think;

use think\Response;
use think\Template;
use think\Loader;

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

    // 模板引擎参数
    protected $config = [
        'auto_rule'   => 1,
        'view_base'   => '',
        'view_path'   => '',
        'view_suffix' => 'html',
        'view_depr'   => DIRECTORY_SEPARATOR,
        'tpl_cache'   => true,
    ];

    public function __construct($data = '', $vars = [], $config = [])
    {
        $this->data = $data;
        $this->vars = $vars;

        $this->app = app();
        $this->config['view_path'] = $this->app->getModulePath() . 'view' . DIRECTORY_SEPARATOR;
        $this->engine = new Template($this->app, array_merge($this->config, $config));
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
    private function parseTemplate($template)
    {
        // 分析模板文件规则
        $request = $this->app['request'];

        // 获取视图根目录
        if (strpos($template, '@')) {
            // 跨模块调用
            list($module, $template) = explode('@', $template);
        }

        if ($this->config['view_base']) {
            // 基础视图目录
            $module = isset($module) ? $module : $request->module();
            $path   = $this->config['view_base'] . ($module ? $module . DIRECTORY_SEPARATOR : '');
        } else {
            $path = isset($module) ? $this->app->getAppPath() . $module . DIRECTORY_SEPARATOR . 'view' . DIRECTORY_SEPARATOR : $this->config['view_path'];
        }

        $depr = $this->config['view_depr'];

        if (0 !== strpos($template, '/')) {
            $template   = str_replace(['/', ':'], $depr, $template);
            $controller = Loader::parseName($request->controller());

            if ($controller) {
                if ('' == $template) {
                    // 如果模板文件名为空 按照默认规则定位
                    $template = str_replace('.', DIRECTORY_SEPARATOR, $controller) . $depr . $this->getActionTemplate($request);
                } elseif (false === strpos($template, $depr)) {
                    $template = str_replace('.', DIRECTORY_SEPARATOR, $controller) . $depr . $template;
                }
            }
        } else {
            $template = str_replace(['/', ':'], $depr, substr($template, 1));
        }

        return $path . ltrim($template, '/') . '.' . ltrim($this->config['view_suffix'], '.');
    }

    protected function getActionTemplate($request)
    {
        $rule = [$request->action(true), Loader::parseName($request->action(true)), $request->action()];
        $type = $this->config['auto_rule'];

        return isset($rule[$type]) ? $rule[$type] : $rule[0];
    }
}
