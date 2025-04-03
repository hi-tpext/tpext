<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006~2021 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------

namespace think;

use Workerman\Worker;

class Request extends \Webman\Http\Request
{
    protected $method = '';

    /**
     * 当前SERVER参数
     * @var array
     */
    protected $server = [];
    /**
     * 当前REQUEST参数
     * @var array
     */
    protected $request = [];
    /**
     * 是否合并Param
     * @var bool
     */
    protected $mergeParam = false;
    /**
     * 当前请求参数
     * @var array
     */
    protected $param = [];

    public function decode()
    {
        $_POST = $_GET = $_COOKIE = $_REQUEST = $_SESSION = $_FILES = [];

        $_GET = parent::get() ?: [];
        $_COOKIE = parent::cookie() ?: [];
        $_SESSION = parent::session()->all() ?: [];

        $httpHost = parent::header('Host');
        $httpArr = explode(':', $httpHost);

        $microtime = \microtime(true);

        $_SERVER = [
            'QUERY_STRING'         => parent::queryString(),
            'REQUEST_METHOD'       => strtoupper(parent::method()),
            'REQUEST_URI'          => parent::uri(),
            'SERVER_PROTOCOL'      => 'HTTP/' . parent::parseProtocolVersion(),
            'SERVER_SOFTWARE'      => 'workerman/' . Worker::VERSION,
            'SERVER_NAME'          => $httpArr[0],
            'SERVER_PORT'          => $httpArr[1] ?? 80,
            'HTTP_HOST'            => $httpHost,
            'HTTP_USER_AGENT'      => parent::header('User-Agent'),
            'HTTP_ACCEPT'          => parent::header('Accept', ''),
            'HTTP_ACCEPT_LANGUAGE' => parent::header('Accept-Language', ''),
            'HTTP_ACCEPT_ENCODING' => parent::header('Accept-Encoding', ''),
            'HTTP_COOKIE'          => parent::header('Cookie', ''),
            'HTTP_CONNECTION'      => parent::header('Connection', ''),
            'CONTENT_TYPE'         => parent::header('content-type', ''),
            'CONTENT_LENGTH'       => parent::header('Content-Length', ''),
            'REMOTE_ADDR'          => $this->connection->getRemoteIp(),
            'REMOTE_PORT'          => $this->connection->getRemotePort(),
            'REQUEST_TIME'         => (int)$microtime,
            'REQUEST_TIME_FLOAT'   => $microtime //compatible php5.4
        ];

        if ($_SERVER['REQUEST_METHOD'] != 'GET') {
            $_POST = parent::post() ?: [];
        }

        $GLOBALS['HTTP_RAW_REQUEST_DATA'] = $GLOBALS['HTTP_RAW_POST_DATA'] = $this->rawBody();

        $_REQUEST = array_merge($_GET, $_POST);

        $this->server = $_SERVER;
        $this->request = $_REQUEST;
    }

    /**
     * Get method.
     *
     * @return string
     */
    public function method(): string
    {
        $method = strtoupper(parent::method());

        if ($this->method) {
            return $this->method;
        }

        if ($method == 'GET') {
            $this->method = 'GET';
        } else if ($method == 'POST') {

            $this->method = 'POST';

            if (!isset($this->data['post'])) {
                $this->parsePost();
            }

            if (isset($this->data['post']['_method'])) {
                $method = strtolower($this->data['post']['_method']);
                unset($this->data['post']['_method']);
                if (in_array($method, ['put', 'patch', 'delete'])) {
                    $this->method = strtoupper($method);
                }
            }
        }

        return $this->method;
    }

    /**
     * 是否为PUT请求
     * @access public
     * @return bool
     */
    public function isPut(): bool
    {
        return $this->method() == 'PUT';
    }

    /**
     * 是否为DELTE请求
     * @access public
     * @return bool
     */
    public function isDelete(): bool
    {
        return $this->method() == 'DELETE';
    }

    /**
     * 是否为HEAD请求
     * @access public
     * @return bool
     */
    public function isHead(): bool
    {
        return $this->method() == 'HEAD';
    }

    /**
     * 是否为PATCH请求
     * @access public
     * @return bool
     */
    public function isPatch(): bool
    {
        return $this->method() == 'PATCH';
    }

    /**
     * 是否为OPTIONS请求
     * @access public
     * @return bool
     */
    public function isOptions(): bool
    {
        return $this->method() == 'OPTIONS';
    }

    /**
     * 是否为cli
     * @access public
     * @return bool
     */
    public function isCli(): bool
    {
        return true;
    }

    /**
     * 是否为cgi
     * @access public
     * @return bool
     */
    public function isCgi(): bool
    {
        return false;
    }

    /**
     * 重写 \Webman\Http\Request::input，不支持$filter参数
     * @param string $name
     * @param mixed $default
     * @return mixed|null
     */
    public function input(string $name, $default = null)
    {
        $key = $name;
        if (0 === strpos($key, '?')) {
            $key = substr($key, 1);
            $has = true;
        }

        if ($pos = strpos($key, '.')) {
            // 指定参数来源
            $method = substr($key, 0, $pos);
            if (in_array($method, ['get', 'post', 'put', 'patch', 'delete', 'route', 'param', 'request', 'session', 'cookie', 'server', 'header', 'file'])) {
                $key = substr($key, $pos + 1);
                if ('server' == $method && is_null($default)) {
                    $default = '';
                }
            } else {
                $method = 'param';
            }
        } else {
            // 默认为自动判断
            $method = 'param';
        }

        if (isset($has)) {
            return $this->has($key, $method, $default);
        } else {
            return $this->$method($key, $default);
        }
    }

    /**
     * Get query.
     *
     * @param string|null $name
     * @param mixed $default
     * @return mixed
     */
    public function get(?string $name = null, mixed $default = null): mixed
    {
        if (!isset($this->data['get'])) {
            $this->parseGet();
        }

        if (is_array($name)) {
            return $this->_only($name, $this->data['get']);
        }

        return $this->_input($this->data['get'], $name, $default);
    }

    /**
     * Get post.
     *
     * @param string|null $name
     * @param mixed $default
     * @return mixed
     */
    public function post(?string $name = null, mixed $default = null): mixed
    {
        if (!isset($this->data['post'])) {
            $this->parsePost();
        }

        if (is_array($name)) {
            return $this->_only($name, $this->data['post']);
        }

        return $this->_input($this->data['post'], $name, $default);
    }

    /**
     * 获取PUT参数
     * @access public
     * @param  string|false      $name 变量名
     * @param  mixed             $default 默认值
     * @return mixed
     */
    public function put($name = '', $default = null)
    {
        return $this->_post($name, $default);
    }

    /**
     * 获取DELETE参数
     * @access public
     * @param  string|false      $name 变量名
     * @param  mixed             $default 默认值
     * @return mixed
     */
    public function delete($name = '', $default = null)
    {
        return $this->_post($name, $default);
    }

    /**
     * 获取PATCH参数
     * @access public
     * @param  string|false      $name 变量名
     * @param  mixed             $default 默认值
     * @return mixed
     */
    public function patch($name = '', $default = null)
    {
        return $this->_post($name, $default);
    }

    /**
     * 获取当前请求的参数
     * @access public
     * @param  string|array $name 变量名
     * @param  mixed        $default 默认值
     * @return mixed
     */
    public function param($name = '', $default = null)
    {
        if (empty($this->mergeParam)) {

            $method = strtoupper(parent::method());

            if ($method == 'POST') {
                $this->param = parent::all();
            } else {
                $this->param = parent::get();
            }

            $this->mergeParam = true;
        }

        if (is_array($name)) {
            return $this->_only($name, $this->param);
        }

        return $this->_input($this->param, $name, $default);
    }

    /**
     * 获取request变量
     * @access public
     * @param  string|array $name 数据名称
     * @param  mixed        $default 默认值
     * @return mixed
     */
    public function request($name = '', $default = null, $filter = '')
    {
        if (is_array($name)) {
            return $this->_only($name, $this->request, $filter);
        }

        return $this->_input($this->request, $name, $default, $filter);
    }

    /**
     * 获取server参数
     * @access public
     * @param  string $name 数据名称
     * @param  string $default 默认值
     * @return mixed
     */
    public function server(string $name = '', string $default = '')
    {
        if (empty($name)) {
            return $this->server;
        } else {
            $name = strtoupper($name);
        }

        return $this->server[$name] ?? $default;
    }

    protected function getFilter($filter, $default): array
    {
        if (is_null($filter)) {
            $filter = [];
        } else {
            $filter = $filter ?: $this->filter;
            if (is_string($filter) && false === strpos($filter, '/')) {
                $filter = explode(',', $filter);
            } else {
                $filter = (array) $filter;
            }
        }

        $filter[] = $default;

        return $filter;
    }

    /**
     * 递归过滤给定的值
     * @access public
     * @param  mixed $value 键值
     * @param  mixed $key 键名
     * @param  array $filters 过滤方法+默认值
     * @return mixed
     */
    public function filterValue(&$value, $key, $filters)
    {
        $default = array_pop($filters);

        foreach ($filters as $filter) {
            if (is_callable($filter)) {
                // 调用函数或者方法过滤
                $value = call_user_func($filter, $value);
            } elseif (is_scalar($value)) {
                if (is_string($filter) && false !== strpos($filter, '/')) {
                    // 正则过滤
                    if (!preg_match($filter, $value)) {
                        // 匹配不成功返回默认值
                        $value = $default;
                        break;
                    }
                } elseif (!empty($filter)) {
                    // filter函数不存在时, 则使用filter_var进行过滤
                    // filter为非整形值时, 调用filter_id取得过滤id
                    $value = filter_var($value, is_int($filter) ? $filter : filter_id($filter));
                    if (false === $value) {
                        $value = $default;
                        break;
                    }
                }
            }
        }

        return $value;
    }

    /**
     * 是否存在某个请求参数
     * @access public
     * @param  string    $name 变量名
     * @param  string    $type 变量类型
     * @param  bool      $checkEmpty 是否检测空值
     * @return mixed
     */
    public function has($name, $type = 'param', $checkEmpty = false)
    {
        if (!in_array($type, ['param', 'get', 'post', 'request', 'put', 'patch', 'file', 'session', 'cookie', 'header'])) {
            return false;
        }

        $param = $this->$type;

        // 按.拆分成多维数组进行判断
        foreach (explode('.', $name) as $val) {
            if (isset($param[$val])) {
                $param = $param[$val];
            } else {
                return false;
            }
        }

        return ($checkEmpty && '' === $param) ? false : true;
    }

    /**
     * 获取指定的参数
     * @access public
     * @param  string|array  $name 变量名
     * @return mixed
     */
    public function only(array $name): array
    {
        return $this->_only($name, $this->param());
    }

    protected function _input(array $data = [], $name = '', $default = null, $filter = '')
    {
        if (false === $name) {
            // 获取原始数据
            return $data;
        }

        $name = (string) $name;
        if ('' != $name) {
            // 解析name
            if (strpos($name, '/')) {
                [$name, $type] = explode('/', $name);
            }

            $data = $this->getData($data, $name);

            if (is_null($data)) {
                return $default;
            }

            if (is_object($data)) {
                return $data;
            }
        }

        if (isset($type) && $data !== $default) {
            // 强制类型转换
            $this->typeCast($data, $type);
        }

        return $data;
    }

    /**
     * 获取指定的参数
     * @access public
     * @param  array        $name 变量名
     * @param  mixed        $data 数据或者变量类型
     * @return array
     */
    public function _only(array $name, $data = 'param'): array
    {
        $data = is_array($data) ? $data : $this->$data();

        $item = [];
        foreach ($name as $key => $val) {

            if (is_int($key)) {
                $default = null;
                $key     = $val;
                if (!isset($data[$key])) {
                    continue;
                }
            } else {
                $default = $val;
            }

            $item[$key] = $data[$key] ?? $default;
        }

        return $item;
    }

    /**
     * 获取数据
     * @access public
     * @param  array         $data 数据源
     * @param  string|false  $name 字段名
     * @return mixed
     */
    protected function getData(array $data, $name)
    {
        foreach (explode('.', $name) as $val) {
            if (isset($data[$val])) {
                $data = $data[$val];
            } else {
                return;
            }
        }

        return $data;
    }

    /**
     * 强制类型转换
     * @access protected
     * @param  mixed  $data
     * @param  string $type
     * @return mixed
     */
    protected function typeCast(&$data, string $type)
    {
        switch (strtolower($type)) {
            // 数组
            case 'a':
                $data = (array) $data;
                break;
            // 数字
            case 'd':
                $data = (int) $data;
                break;
            // 浮点
            case 'f':
                $data = (float) $data;
                break;
            // 布尔
            case 'b':
                $data = (bool) $data;
                break;
            // 字符串
            case 's':
                if (is_scalar($data)) {
                    $data = (string) $data;
                } else {
                    throw new \InvalidArgumentException('variable type error：' . gettype($data));
                }
                break;
        }
    }

    /**
     * 当前是否JSON请求
     * @access public
     * @return bool
     */
    public function isJson(): bool
    {
        $acceptType = $this->type();

        return false !== strpos($acceptType, 'json');
    }

    /**
     * IsAjax
     * @return bool
     */
    public function isAjax(): bool
    {
        return $this->param('_ajax') ? true : parent::isAjax();
    }

    /**
     * 获取客户端IP地址
     * @access public
     * @return string
     */
    public function ip(): string
    {
        return parent::getRealIp(true);
    }

    /**
     * 当前请求URL地址中的port参数
     * @access public
     * @return int
     */
    public function port(): int
    {
        return parent::getLocalPort();
    }

    /**
     * 当前请求 HTTP_CONTENT_TYPE
     * @access public
     * @return string
     */
    public function contentType(): string
    {
        $contentType = $this->header('content-type', '');

        if ($contentType) {
            if (strpos($contentType, ';')) {
                [$type] = explode(';', $contentType);
            } else {
                $type = $contentType;
            }
            return trim($type);
        }

        return '';
    }

    /**
     * 设置GET数据
     * @access public
     * @param  array $get 数据
     * @return $this
     */
    public function withGet(array $get)
    {
        foreach ($get as $key => $val) {
            $this->setGet($key, $val);
        }
        return $this;
    }

    /**
     * 设置POST数据
     * @access public
     * @param  array $post 数据
     * @return $this
     */
    public function withPost(array $post)
    {
        foreach ($post as $key => $val) {
            $this->setPost($key, $val);
        }
        return $this;
    }
}
