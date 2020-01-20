<?php

namespace tpext\common;

use tpext\common\Module as baseModule;

class TpextModule extends baseModule
{
    protected static $name = 'tpext.core';

    protected static $modules = [
        'admin' => ['tpext'],
    ];

    public static function moduleInit($info = [])
    {
        $rootPath = realpath(dirname(dirname(__DIR__))) . DIRECTORY_SEPARATOR;

        static::$assets = $rootPath . 'assets';

        return parent::moduleInit($info);
    }
}
