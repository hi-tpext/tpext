<?php

use tpext\common\ExtLoader;

ExtLoader::watch('app_init', tpext\common\behavior\AppInit::class, true, '初始化tpext');

$classMap = [
    'tpext\\common\\TpextCore'
];

ExtLoader::addClassMap($classMap);
