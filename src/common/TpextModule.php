<?php

namespace tpext\common;

use tpext\common\Module as baseModule;

class TpextModule extends baseModule
{
    protected $name = 'tpext.core';

    protected $title = 'tpext核心';

    protected $description = '提供对扩展代管理';

    protected $__root__ = __DIR__ . '/../../';

    protected $assets = '';

    protected $modules = [
        'admin' => ['tpext'],
    ];

    public function moduleInit($info = [])
    {
        return parent::moduleInit($info);
    }
}
