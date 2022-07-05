<?php

namespace tpext\common\model;

use think\Model;
use tpext\think\App;
use think\facade\Cache;
use tpext\common\ExtLoader;
use think\db\exception\DbException;

class WebConfig extends Model
{
    protected $autoWriteTimestamp = 'datetime';

    public static function clearCache($configKey)
    {
        Cache::set('web_config_' . $configKey, null);
        ExtLoader::trigger('clear_cache_web_config_' . $configKey);
        ExtLoader::trigger('clear_cache_web_config', $configKey);
    }

    public static function config($configKey, $reget = false)
    {
        $cache = Cache::get('web_config_' . $configKey);

        if ($cache && !$reget) {
            return $cache;
        }

        $theConfig = [];

        try {
            $theConfig = static::where(['key' => $configKey])->find();
            if (!$theConfig) {
                return [];
            }
        } catch (DbException $e) {
            return [];
        }

        $config = json_decode($theConfig['config'], 1);

        $rootPath = App::getRootPath();
        $filePath = $rootPath . str_replace(['\\', '/'], DIRECTORY_SEPARATOR, $theConfig['file']);

        if (!is_file($filePath)) {
            return $config;
        }

        $default = include $filePath;

        if (empty($config)) {
            return $default;
        }

        $values = [];
        foreach ($default as $key => $val) {

            if ($key == '__config__' || $key == '__saving__') {
                continue;
            }
            $values[$key] = $config[$key] ?? $val;
        }
        if (!empty($values)) {
            Cache::set('web_config_' . $configKey, $values);
        }

        return $values;
    }
}
