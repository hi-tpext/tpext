<?php

namespace tpext\common\behavior;

use tpext\common\ExtLoader;

/**
 * for tp5
 */

class AppInit
{
    public function run()
    {
        include realpath(dirname(dirname(__DIR__))) . DIRECTORY_SEPARATOR . 'common.php';

        ExtLoader::watch('app_dispatch', AppDispatch::class, true, 'tpext路由处理');

        ExtLoader::bindExtensions();

        ExtLoader::trigger('tpext_modules_loaded');
    }
}
