<?php

namespace tpext\webman;

use support\Container;
use Webman\Http\Request;
use Webman\Http\Response;
use Webman\MiddlewareInterface;
use tpext\builder\common\Builder;
use think\exception\HttpResponseException;

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

            if ($exception = $response->exception()) {
                if ($exception instanceof HttpResponseException) {
                    return $exception->getResponse();
                }

                if ($request->isAjax()) {
                    $json = ['code' => 0, 'msg' => '[' . basename($exception->getFile()) . '#' . $exception->getLine() . ']' . $exception->getMessage()];
                    return new Response(
                        200,
                        ['Content-Type' => 'application/json'],
                        json_encode($json, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                    );
                }
            }

            return $response;
        }

        return $next($request);
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
    protected function error($msg = '', $url = null, $data = '', $wait = 3, $header = array())
    {
        $type = $this->getResponseType();

        if (is_null($url)) {
            $url = $type == 'json' ? '' : 'javascript:history.back(-1);';
        } elseif ('' !== $url) {
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
            $response = new Response(200, ['Content-Type' => 'application/json'], json_encode($result, JSON_UNESCAPED_UNICODE));
        } else {
            $view = new View(self::getDispatchJumpTemplate(), $result);
            $response = new Response(200, $header, $view->getContent());
        }

        throw new HttpResponseException($response);
    }
}
