<?php

namespace tpext\webman;

use think\Validate;
use tpext\think\App;
use think\facade\Lang;
use tpext\common\ExtLoader;
use tpext\common\TpextCore;
use tpext\common\RouteLoader;
use think\Container;

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

        Container::getInstance()->bind('think\CacheManager', \Webman\ThinkCache\CacheManager::class);
        Container::getInstance()->bind('think\DbManager', \Webman\ThinkOrm\DbManager::class);
        ExtLoader::bindExtensions();
        RouteLoader::load();
        Lang::load(TpextCore::getInstance()->getRoot() . implode(DIRECTORY_SEPARATOR, ['think', 'lang', App::getDefaultLang() . '.php']));

        ExtLoader::trigger('tpext_modules_loaded');

        static::composer();
    }

    public static function composer()
    {
        if (!is_dir(base_path() . '/extend/')) {
            mkdir(base_path() . '/extend/', 0775);
        }

        $json = json_decode(file_get_contents(base_path() . '/composer.json'), true);

        $forceWrite = false;
        if (empty($json['autoload'])) {
            $json['autoload'] = [
                "psr-0" => [
                    "" => "extend/"
                ]
            ];
            $forceWrite = true;
        } else {
            if (empty($json['autoload']['psr-0'])) {
                $json['autoload']['psr-0'] = [
                    "" => "extend/"
                ];
                $forceWrite = true;
            } else {
                if (!in_array('extend/', $json['autoload']['psr-0'])) {
                    $json['autoload']['psr-0'][''] = "extend/";
                    $forceWrite = true;
                }
            }
        }

        if (!$forceWrite) {
            return;
        }

        file_put_contents(base_path() . '/composer.json', json_encode($json, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));

        echo 'regist path [/extend] succeeded, composer.json was updated' . "\n";
    }
}
