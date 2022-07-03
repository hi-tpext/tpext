<?php

namespace tpext\think;

use think\Response;
use think\Template;

class View extends Response
{
    protected $vars = [];

    protected static $shareVars = [];

    protected $isContent = false;

    protected $app;

    /**
     * Undocumented variable
     *
     * @var Template
     */
    protected $engine;

    public function __construct($data = '', $vars = [])
    {
        $this->data = $data;
        $this->vars = $vars;

        $this->app = app();
        $this->engine = new Template($this->app);
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
                $this->engine->fetch($template, $vars);
            }
        } catch (\Exception $e) {
            ob_end_clean();
            throw $e;
        }

        $content = ob_get_clean();

        return $content;
    }
}
