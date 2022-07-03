<?php

namespace tpext\webman;

use support\Container;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;
use tpext\builder\common\Builder;
use ReflectionMethod;
use ReflectionFunctionAbstract;

/**
 * for webman
 */

class CrontrollerInit implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        Builder::destroyInstance();

        if ($request->controller) {

            $action = strtolower($request->action);

            if (in_array($action, ['_tpextinit', '_tpextdeinit'])) {
                return new Response(404, [], '404 Not Found');
            }

            $instance = Container::get($request->controller);

            if (method_exists($instance, '_tpextinit')) {
                $initResp = $instance->_tpextinit($request);
                if ($initResp && $initResp instanceof Response) {
                    return $initResp;
                }
            }

            $response = $next($request);

            if (method_exists($instance, '_tpextdeinit')) {
                $deinitResp = $instance->_tpextdeinit($request, $response);
                if ($deinitResp && $deinitResp instanceof Response) {
                    return $deinitResp;
                }
            }
            return $response;
        }

        return $next($request);
    }

    // +----------------------------------------------------------------------
    // | ThinkPHP [ WE CAN DO IT JUST THINK ]
    // +----------------------------------------------------------------------
    // | Copyright (c) 2006~2021 http://thinkphp.cn All rights reserved.
    // +----------------------------------------------------------------------
    // | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
    // +----------------------------------------------------------------------
    // | Author: liu21st <liu21st@gmail.com>
    // +----------------------------------------------------------------------

    protected function bind($request, $instance, $action, $vars)
    {
        $reflect = new ReflectionMethod($instance, $action);
        $args = $this->bindParams($request, $reflect, $vars);

        return $args;
    }

    /**
     * 绑定参数
     * @access protected
     * @param ReflectionFunctionAbstract $reflect 反射类
     * @param array                      $vars    参数
     * @return array
     */
    protected function bindParams($request, ReflectionFunctionAbstract $reflect, array $vars = []): array
    {
        if ($reflect->getNumberOfParameters() == 0) {
            return [$request];
        }

        // 判断数组类型 数字数组时按顺序绑定参数
        reset($vars);
        $type   = key($vars) === 0 ? 1 : 0;
        $params = $reflect->getParameters();
        $args   = [];

        foreach ($params as $param) {
            $lowerName = $name = $param->getName();

            if ($name == 'request') {
                $args[] = $request;
                continue;
            }

            if (!ctype_lower($name)) {
                $lowerName = preg_replace('/\s+/u', '', ucwords($name));
                $lowerName = mb_strtolower(preg_replace('/(.)(?=[A-Z])/u', '$1' . '_', $lowerName), 'UTF-8');
            }

            $reflectionType = $param->getType();

            if ($reflectionType && $reflectionType->isBuiltin() === false) {
                $args[] = $this->getObjectParam($reflectionType->getName(), $vars);
            } elseif (1 == $type && !empty($vars)) {
                $args[] = array_shift($vars);
            } elseif (0 == $type && array_key_exists($name, $vars)) {
                $args[] = $vars[$name];
            } elseif (0 == $type && array_key_exists($lowerName, $vars)) {
                $args[] = $vars[$lowerName];
            } elseif ($param->isDefaultValueAvailable()) {
                $args[] = $param->getDefaultValue();
            } else {
                throw new \InvalidArgumentException('method param miss:' . $name);
            }
        }

        return $args;
    }

    /**
     * 获取对象类型的参数值
     * @access protected
     * @param string $className 类名
     * @param array  $vars      参数
     * @return mixed
     */
    protected function getObjectParam(string $className, array &$vars)
    {
        $array = $vars;
        $value = array_shift($array);

        if ($value instanceof $className) {
            $result = $value;
            array_shift($vars);
        } else {
            $result = Container::get($className);
        }

        return $result;
    }
}
