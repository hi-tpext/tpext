<?php

namespace tpext\webman;

use Throwable;
use think\Controller;
use tpext\think\View;
use support\Container;
use Webman\Http\Request;
use Webman\Http\Response;
use think\facade\Validate;
use tpext\common\ExtLoader;
use tpext\common\TpextCore;
use Webman\MiddlewareInterface;
use support\exception\BusinessException;
use think\exception\HttpResponseException;

/**
 * for webman
 */

class CrontrollerInit implements MiddlewareInterface
{
    public function process(Request $request, callable $next): Response
    {
        Validate::destroyInstance();
        
        ExtLoader::trigger('tpext_webman_run');
        $response = $this->getResponse($request, $next);
        ExtLoader::trigger('tpext_webman_end');

        if ($exception = $response->exception()) {
            if ($exception instanceof HttpResponseException) {
                return $exception->getResponse();
            }

            if (!($exception instanceof BusinessException)) {
                if ($request->expectsJson()) {
                    $json = ['code' => 0, 'msg' => config('app.debug', true) ? '[' . $exception->getFile() . '#' . $exception->getLine() . ']' . $exception->getMessage() : 'Server internal error'];
                    return new Response(
                        200,
                        ['Content-Type' => 'application/json'],
                        json_encode($json, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
                    );
                } else {
                    return new Response(
                        200,
                        [],
                        $this->renderExceptionContent($exception)
                    );
                }
            }
        }

        //php.ini中max_execution_time的值对cli环境无效，但可以在程序中是可以被修改并生效
        @set_time_limit(0); //清除某些第三方库可能会设置超时不为0值对cli环境的影响
        Validate::destroyInstance();
        return $response;
    }

    private function getResponse(Request $request, callable $next): Response
    {
        if ($request->controller) {

            $action = strtolower($request->action);

            if (in_array($action, ['_tpextinit', '_tpextdeinit'])) {
                return new Response(404, [], '404 Not Found');
            }

            $controller_reuse = config('app.controller_reuse', true);
            $response = null;
            if ($controller_reuse) {
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
            } else {

                $response = $next($request);

                if (\is_callable($request->controller . '::getInitializeResult')) {
                    $initResp = $request->controller::getInitializeResult();
                    if ($initResp && $initResp instanceof Response) {
                        return $initResp;
                    }
                }
            }
            Controller::setDispatchJumpTemplate('');
            View::clearShareVars();
            return $response;
        }
        return $next($request);
    }

    protected function renderExceptionContent(Throwable $exception): string
    {
        ob_start();
        $data = $this->convertExceptionToArray($exception);
        extract($data);
        $rootPath = TpextCore::getInstance()->getRoot();
        include $rootPath . implode(DIRECTORY_SEPARATOR, ['think', 'tpl', 'think_exception']) . '.tpl';

        return ob_get_clean();
    }

    /**
     * 收集异常数据
     * @param Throwable $exception
     * @return array
     */
    protected function convertExceptionToArray(Throwable $exception): array
    {
        // 调试模式，获取详细的错误信息
        $traces        = [];
        $nextException = $exception;
        do {
            $traces[] = [
                'name'    => get_class($nextException),
                'file'    => $nextException->getFile(),
                'line'    => $nextException->getLine(),
                'code'    => $nextException->getCode(),
                'message' => $nextException->getMessage(),
                'trace'   => $nextException->getTrace(),
                'source'  => $this->getSourceCode($nextException),
            ];
        } while ($nextException = $nextException->getPrevious());

        $request = tpRequest();

        $data = [
            'code'    => $exception->getCode(),
            'message' => $exception->getMessage(),
            'traces'  => $traces,
            'datas'   => [],
            'tables'  => config('app.debug', true) ? [
                'GET Data'            => $request->get(),
                'POST Data'           => $request->post(),
                'Files'               => $request->file(),
                'Cookies'             => $request->cookie(),
                'Session'             => $request->session()->all() ?: [],
                'Server/Request Data' => $request->server(),
            ] : [],
        ];

        return $data;
    }

    /**
     * 获取出错文件内容
     * 获取错误的前9行和后9行
     * @access protected
     * @param Throwable $exception
     * @return array                 错误文件内容
     */
    protected function getSourceCode(Throwable $exception): array
    {
        // 读取前9行和后9行
        $line  = $exception->getLine();
        $first = ($line - 9 > 0) ? $line - 9 : 1;

        try {
            $contents = file($exception->getFile()) ?: [];
            $source   = [
                'first'  => $first,
                'source' => array_slice($contents, $first - 1, 19),
            ];
        } catch (\Exception $e) {
            $source = [];
        }

        return $source;
    }
}
