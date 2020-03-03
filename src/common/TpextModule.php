<?php

namespace tpext\common;

use tpext\common\Module as baseModule;

class TpextModule extends baseModule
{
    protected $version = '1.0.1';
    
    protected $name = 'tpext.core';

    protected $title = 'tpext核心';

    protected $description = '提供对扩展的管理';

    protected $__root__ = __DIR__ . '/../../';

    protected $modules = [
        'admin' => ['tpext'],
    ];
}
