<?php

declare(strict_types=1);

namespace think;

use think\App;
use think\Response;
use think\Validate;
use think\Container;
use tpext\common\TpextCore;
use think\exception\ValidateException;
use think\exception\HttpResponseException;

/**
 * 控制器基础类
 */
abstract class Controller
{
    /**
     * Request实例
     * @var \think\Request
     */
    protected $request;

    /**
     * 应用实例
     * @var \think\App
     */
    protected $app;

    /**
     * 是否批量验证
     * @var bool
     */
    protected $batchValidate = false;

    /**
     * 控制器中间件
     * @var array
     */
    protected $middleware = [];

    /**
     * 视图类实例
     * @var \think\View
     */
    protected $view;

    protected $vars  = [];

    /**
     * 构造方法
     * @access public
     * @param  App  $app  应用对象
     */
    public function __construct(App $app)
    {
        $this->app = $app;
        $this->request = $this->app->request;

        // 控制器初始化
        $this->initialize();
    }

    // 初始化
    protected function initialize()
    {
    }

    /**
     * 验证数据
     * @access protected
     * @param  array        $data     数据
     * @param  string|array $validate 验证器名或者验证规则数组
     * @param  array        $message  提示信息
     * @param  bool         $batch    是否批量验证
     * @return array|string|true
     * @throws ValidateException
     */
    protected function validate(array $data, $validate, array $message = [], bool $batch = false)
    {
        if (is_array($validate)) {
            $v = new Validate();
            $v->rule($validate);
        } else {
            if (strpos($validate, '.')) {
                // 支持场景
                [$validate, $scene] = explode('.', $validate);
            }
            $class = false !== strpos($validate, '\\') ? $validate : $this->app->parseClass('validate', $validate);
            $v = new $class();
            if (!empty($scene)) {
                $v->scene($scene);
            }
        }

        $v->message($message);

        // 是否批量验证
        if ($batch || $this->batchValidate) {
            $v->batch(true);
        }

        if (!$v->failException(false)->check($data)) {
            return $v->getError();
        }

        return true;
    }

    /** tp5兼容 **/

    /**
     * 渲染模板输出
     * @param string   $template 模板文件
     * @param array    $vars     模板变量
     * @param array  $config   模板参数
     * @return string
     */
    protected function fetch(string $template = '', $vars = [], $config = [])
    {
        $engine = $this->app->view->engine();
        $engine->config($config);
        return $this->app->view->fetch($template, array_merge($this->vars, $vars));
    }

    /**
     * 渲染模板输出
     * @param string   $content 渲染内容
     * @param array    $vars    模板变量
     * @param array  $config   模板参数
     * @return string
     */
    protected function display(string $content, $vars = [], $config = [])
    {
        $engine = $this->app->view->engine();
        $engine->config($config);
        return $this->app->view->display($content, array_merge($this->vars, $vars));
    }

    /**
     * 模板变量赋值
     * @access protected
     * @param  mixed $name  要显示的模板变量
     * @param  mixed $value 变量的值
     * @return $this
     */
    protected function assign($name, $value = '')
    {
        $this->app->view->assign($name, $value);

        return $this;
    }

    /**
     * 操作成功跳转的快捷方法
     * @access protected
     * @param  mixed     $msg 提示信息
     * @param  string    $url 跳转的URL地址
     * @param  mixed     $data 返回的数据
     * @param  integer   $wait 跳转等待时间
     * @param  array     $header 发送的Header信息
     * @return void
     */
    protected function success($msg = '', $url = null, $data = '', $wait = 3, array $header = [])
    {
        if (is_null($url) && $referer = request()->header('REFERER')) {
            $url = $referer;
        } elseif ('' !== $url) {
            $url = (string) $url;
            $url = (strpos($url, '://') || 0 === strpos($url, '/')) ? $url : url($url)->__toString();
        }

        $result = [
            'code' => 1,
            'msg' => $msg,
            'data' => $data,
            'url' => $url,
            'wait' => $wait,
        ];

        $response = null;

        if ($this->getResponseType() == 'json') {
            $response = json($result);
        } else {
            $rootPath = TpextCore::getInstance()->getRoot();
            $tplPath = $rootPath . implode(DIRECTORY_SEPARATOR, ['think', 'tpl', 'dispatch_jump']) . '.tpl';
            $response = view($tplPath, $result);
        }
        $response->header($header);

        throw new HttpResponseException($response);
    }

    /**
     * 操作错误跳转的快捷方法
     * @access protected
     * @param  mixed     $msg 提示信息
     * @param  string    $url 跳转的URL地址
     * @param  mixed     $data 返回的数据
     * @param  integer   $wait 跳转等待时间
     * @param  array     $header 发送的Header信息
     * @return void
     */
    protected function error($msg = '', $url = null, $data = '', $wait = 3, array $header = [])
    {
        $type = $this->getResponseType();

        if (is_null($url)) {
            $url = $type == 'json' ? '' : 'javascript:history.back(-1);';
        } else if ('' !== $url) {
            $url = (string) $url;
            $url = (strpos($url, '://') || 0 === strpos($url, '/')) ? $url : url($url)->__toString();
        }

        $result = [
            'code' => 0,
            'msg' => $msg,
            'data' => $data,
            'url' => $url,
            'wait' => $wait,
        ];

        $response = null;

        if ($type == 'json') {
            $response = json($result);
        } else {
            $rootPath = TpextCore::getInstance()->getRoot();
            $tplPath = $rootPath . implode(DIRECTORY_SEPARATOR, ['think', 'tpl', 'dispatch_jump']) . '.tpl';
            $response = view($tplPath, $result);
        }
        $response->header($header);

        throw new HttpResponseException($response);
    }

    /**
     * 返回封装后的API数据到客户端
     * @access protected
     * @param  mixed     $data 要返回的数据
     * @param  integer   $code 返回的code
     * @param  mixed     $msg 提示信息
     * @param  string    $type 返回数据格式
     * @param  array     $header 发送的Header信息
     * @return void
     */
    protected function result($data, $code = 0, $msg = '', $type = '', array $header = [])
    {
        $result = [
            'code' => $code,
            'msg' => $msg,
            'time' => time(),
            'data' => $data,
        ];

        $type = $type ?: $this->getResponseType();
        $response = Response::create($result, $type)->header($header);

        throw new HttpResponseException($response);
    }

    /**
     * URL重定向
     * @access protected
     * @param  string         $url 跳转的URL表达式
     * @param  array|integer  $params 其它URL参数
     * @param  integer        $code http code
     * @param  array          $with 隐式传参
     * @return void
     */
    protected function redirect($url, $params = [], $code = 302, $with = [])
    {
        $response = Response::create($url, 'redirect', $code);

        if (is_integer($params)) {
            $code = $params;
            $params = [];
        }

        $response->code($code);

        throw new HttpResponseException($response);
    }

    /**
     * 获取当前的response 输出类型
     * @access protected
     * @return string
     */
    protected function getResponseType()
    {
        if (!$this->app) {
            $this->app = Container::get('app');
        }

        $isAjax = $this->app['request']->isAjax();

        return $isAjax ? 'json' : 'html';
    }
}
