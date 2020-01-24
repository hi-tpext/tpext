<?php

namespace tpext\common;

use tpext\common\Module as baseModule;

class TpextModule extends baseModule
{
    protected $name = 'tpext.core';

    protected $__root__ = __DIR__ . '/../../';

    protected $assets = 'assets';

    protected $modules = [
        'admin' => ['tpext'],
    ];

    public function moduleInit($info = [])
    {
        return parent::moduleInit($info);
    }
}
