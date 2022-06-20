<?php

namespace tpext;

use Webman\Bootstrap;
use tpext\common\ExtLoader;

class TpextWebman implements Bootstrap
{
    public static function start($worker)
    {
        if ($worker->name == 'monitor') {
            return;
        }

        ExtLoader::bindExtensions();
        ExtLoader::trigger('tpext_modules_loaded');
    }
}
