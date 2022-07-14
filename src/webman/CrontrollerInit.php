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
}
