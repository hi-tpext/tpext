<?php

namespace tpext\common;

use think\Service as BaseService;
use tpext\common\middleware\AppRun;
use think\event\AppInit;

/**
 * for tp6
 */
class Service extends BaseService
{
    public function boot()
    {
        include realpath(dirname(__DIR__)) . DIRECTORY_SEPARATOR . 'common.php';

        $this->app->event->listen(AppInit::class, function () {
            // AppInit无法在Service中监听，后续跟进，暂时放在`HttpRun`中
            // ExtLoader::bindExtensions();
            // ExtLoader::trigger('tpext_modules_loaded');
        });

        $this->app->event->listen('HttpRun', function () {
            ExtLoader::bindExtensions();
            ExtLoader::trigger('tpext_modules_loaded');

            $this->app->middleware->add(AppRun::class);
        });
    }
}
