<?php

namespace tpext\common;

use think\Service as BaseService;
use tpext\common\middleware\AppRun;

/**
 * for tp6
 */
class Service extends BaseService
{
    public function boot()
    {
        include realpath(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'common.php';

        $this->app->event->listen('HttpRun', function () {
            $this->app->middleware->add(AppRun::class);
        });

        ExtLoader::bindExtensions();

        ExtLoader::trigger('tpext_modules_loaded');
    }
}
