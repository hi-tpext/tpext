<?php

namespace tpext\common;

use tpext\common\Module as baseModule;

class TpextModule extends baseModule
{
    protected $name = 'tpext.core';

    protected $modules = [
        'admin' => ['tpext'],
    ];

    public function moduleInit($info = [])
    {
        $rootPath = realpath(dirname(dirname(__DIR__))) . DIRECTORY_SEPARATOR;

        $this->assets = $rootPath . 'assets';

        return parent::moduleInit($info);
    }
}
