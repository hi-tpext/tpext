<?php

namespace tpext\common;

use think\db;
use think\facade\Env;
use think\facade\Log;

class Tool
{

    public static $autoload_psr4 = [];

    public static function copyDir($src = '', $dst = '')
    {
        if (empty($src) || empty($dst)) {
            return false;
        }

        if (!is_dir($src)) {
            throw new \InvalidArgumentException('传入的不是一个目录:' . $src);
            return false;
        }

        $dir = opendir($src);

        static::mkdirs($dst);

        while (false !== ($file = readdir($dir))) {

            if (($file != '.') && ($file != '..')) {

                $sonDir = $src . DIRECTORY_SEPARATOR . $file;

                if (is_dir($sonDir)) {
                    static::copyDir($sonDir, $dst . DIRECTORY_SEPARATOR . $file);
                } else {
                    copy($sonDir, $dst . DIRECTORY_SEPARATOR . $file);
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

    public static function clearAssetsDir($dirName)
    {
        $dirs = ['', 'assets', $dirName, ''];

        $scriptName = $_SERVER['SCRIPT_FILENAME'];

        $assetsDir = realpath(dirname($scriptName)) . implode(DIRECTORY_SEPARATOR, $dirs);

        if (is_dir($assetsDir)) {
            static::deleteDir($assetsDir);
        }

        return true;
    }

    public static function deleteDir($path)
    {
        if (is_dir($path)) {

            $dir = opendir($path);

            while (false !== ($file = readdir($dir))) {

                if (($file != '.') && ($file != '..')) {

                    $sonDir = $path . DIRECTORY_SEPARATOR . $file;
                    if (is_dir($sonDir)) {
                        static::deleteDir($sonDir);
                    } else {
                        unlink($sonDir);
                    }
                }
            }
            closedir($dir);
            rmdir($path);
        }
    }

    public static function checkAssetsDir($dirName)
    {
        $dirs = ['public', 'assets', $dirName, ''];

        $assetsDir = app()->getRootPath() . implode(DIRECTORY_SEPARATOR, $dirs);

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

    public static function executeSqlFile($file, &$errors = [])
    {
        $content = file_get_contents($file);

        if (!$content) {
            return false;
        }

        $prefix = config('database.prefix');

        $content = preg_replace('/\r\n|\r/', "\n", $content);

        $content = preg_replace('/__prefix__/is', $prefix, $content);

        $content = preg_replace('/\n?\s*--\s.*?\n/', "\n", $content);

        //$content = preg_replace('/\n?\s*#.*?\n/', "\n", $content);

        $content = preg_replace('/\/\*.*?\*\//s', "\n", $content);

        $content = preg_replace('/\n{2,}/s', "\n", $content);

        $sqls = explode(";\n", $content);

        $success = 0;

        foreach ($sqls as $sql) {
            if ($sql == '') {
                continue;
            }
            try {
                db::execute($sql);
                $success += 1;
            } catch (\Exception $e) {
                Log::error($e->__toString());
                $errors[] = $e;
            }
        }

        return $success;
    }
}
