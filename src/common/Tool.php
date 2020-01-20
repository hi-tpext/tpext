<?php

namespace tpext\common;

use think\facade\Env;

class Tool
{

    public static $autoload_psr4 = [];

    public static function install($class)
    {

    }

    public static function copyDir($src = '', $dst = '')
    {
        if (empty($src) || empty($dst)) {
            return false;
        }

        if (!is_dir($src)) {
            throw new \InvalidArgumentException('传入的不是一个目录');
            return false;
        }

        $dir = opendir($src);

        static::mkdirs($dst);

        while (false !== ($file = readdir($dir))) {

            if (($file != '.') && ($file != '..')) {

                if (is_dir($src . DIRECTORY_SEPARATOR . $file)) {
                    static::copyDir($src . DIRECTORY_SEPARATOR . $file, $dst . DIRECTORY_SEPARATOR . $file);
                } else {
                    copy($src . DIRECTORY_SEPARATOR . $file, $dst . DIRECTORY_SEPARATOR . $file);
                }
            }
        }
        closedir($dir);

        return true;
    }

    public static function mkdirs($path = '', $mode = 0777, $recursive = true)
    {
        clearstatcache();

        if (!is_dir($path)) {

            mkdir($path, $mode, $recursive);
        }

        return true;
    }

    public static function checkAssetsDir($dirName)
    {
        $dirs = ['', 'assets', $dirName, ''];

        $scriptName = $_SERVER['SCRIPT_FILENAME'];

        $assetsDir = realpath(dirname($scriptName)) . implode(DIRECTORY_SEPARATOR, $dirs);

        if (is_dir($assetsDir)) {

            return false;
        }

        mkdir($assetsDir, 0777, true);

        return $assetsDir;
    }

    public static function getNameSpaceMap($class)
    {

        if (empty(static::$autoload_psr4)) {

            $composerPath = Env::get('root_path') . 'vendor' . DIRECTORY_SEPARATOR . 'composer' . DIRECTORY_SEPARATOR;

            if (is_file($composerPath . 'autoload_psr4.php')) {
                static::$autoload_psr4 = require $composerPath . 'autoload_psr4.php';
            }
        }

        if (!empty(static::$autoload_psr4)) {

            foreach (static::$autoload_psr4 as $namespace => $paths) {

                if (false !== strpos(strtolower($class), strtolower($namespace))) {
                    return [$namespace, '..' . DIRECTORY_SEPARATOR . preg_replace('/.*[\/\\\](vendor[\/\\\].+$)/i', '$1', $paths[0])];
                }
            }
        }

        return [];
    }
}
