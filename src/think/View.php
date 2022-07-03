<?php

namespace tpext\think;

use think\Template;
use tpext\think\App;
use Webman\Http\Response;

class View
{
    protected static $shareVars = [];
    protected $vars = [];
    protected $content = null;
    protected $isContent = false;
    protected $response = null;

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

        $config = [
            'cache_path'     => App::getRuntimePath() . 'temp' . DIRECTORY_SEPARATOR,
            'view_suffix'   => 'html',
            'tpl_cache'     => true,
        ];

        $this->engine = new Template($config);
    }

    /**
     * 获取输出数据
     * @access public
     * @return Response
     */
    public function getContent()
    {
        if (null == $this->content) {
            $this->content = $this->fetch($this->data) ?: '';
        }

        return $this->content;
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
    }

    public static function clearShareVars()
    {
        self::$shareVars  = [];
    }

    public function clear()
    {
        $this->vars = [];
        $this->content = null;

        return $this;
    }

    protected function fetch($template = '')
    {
        if (empty($template)) {
            return '';
        }

        ob_start();

        $vars = array_merge(self::$shareVars, $this->vars);

        if ($this->isContent) {
            $this->engine->display($template, $vars);
        } else {
            $this->engine->fetch($template, $vars);
        }

        $content = ob_get_clean();

        return $content;
    }

    public function __toString()
    {
        return $this->getContent();
    }
}
