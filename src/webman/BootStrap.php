<?php

namespace tpext\webman;

use think\Validate;
use tpext\think\App;
use think\facade\Lang;
use tpext\common\ExtLoader;
use tpext\common\TpextCore;
use tpext\common\RouteLoader;
use think\Container;
use tpext\Install;

class BootStrap implements \Webman\Bootstrap
{
    public static function start($worker)
    {
        if ($worker->name == 'monitor') {
            return;
        }

        Validate::maker(function (Validate $validate) {
            $validate->setLang(Lang::getInstance());
        });

        Container::getInstance()->bind('think\DbManager', \Webman\ThinkOrm\DbManager::class);
        ExtLoader::bindExtensions();
        Lang::load(TpextCore::getInstance()->getRoot() . implode(DIRECTORY_SEPARATOR, ['think', 'lang', App::getDefaultLang() . '.php']));
        ExtLoader::trigger('tpext_modules_loaded');

        if ($worker->id === 0) {
            RouteLoader::load();
            Install::composer();
        }
    }
}
