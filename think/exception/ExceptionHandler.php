<?php

/**
 * This file is part of webman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link      http://www.workerman.net/
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

declare(strict_types=1);

namespace think\exception;

use Webman\Http\Request;
use Webman\Http\Response;
use Throwable;
use Webman\Exception\ExceptionHandlerInterface;
use support\exception\BusinessException;

/**
 * 系统异常处理类
 */
class ExceptionHandler implements ExceptionHandlerInterface
{
    /**
     * @var LoggerInterface
     */
    protected $_logger = null;

    /**
     * @var bool
     */
    protected $_debug = false;

    /**
     * @var array
     */
    public $dontReport = [
        HttpResponseException::class,
        BusinessException::class,
    ];

    /**
     * ExceptionHandler constructor.
     * @param $logger
     * @param $debug
     */
    public function __construct($logger, $debug)
    {
        $this->_logger = $logger;
        $this->_debug = $debug;
    }

    /**
     * @param Throwable $exception
     * @return void
     */
    public function report(Throwable $exception)
    {
        if ($this->shouldntReport($exception)) {
            return;
        }
        $logs = '';
        $request = \request();
        if ($request) {
            $logs = $request->getRealIp() . ' ' . $request->method() . ' ' . trim($request->fullUrl(), '/');
        }
        $this->_logger->error($logs . "\n" . $exception);
    }

    /**
     * @param Request $request
     * @param Throwable $exception
     * @return Response
     */
    public function render(Request $request, Throwable $exception): Response
    {
        if ($exception instanceof HttpResponseException) {
            return $exception->getResponse();
        }

        $code = $exception->getCode();
        if ($request->expectsJson()) {
            $json = ['code' => $code ? $code : 500, 'msg' => $exception->getMessage()];
            $this->_debug && $json['traces'] = (string)$exception;
            return new Response(
                200,
                ['Content-Type' => 'application/json'],
                json_encode($json, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
            );
        }
        $error = $this->_debug ? nl2br((string)$exception) : 'Server internal error';
        return new Response(500, [], $error);
    }

    /**
     * @param Throwable $e
     * @return bool
     */
    protected function shouldntReport(Throwable $e)
    {
        foreach ($this->dontReport as $type) {
            if ($e instanceof $type) {
                return true;
            }
        }
        return false;
    }
}
