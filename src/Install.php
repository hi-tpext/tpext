<?php

namespace tpext;

class Install
{
    const WEBMAN_PLUGIN = true;

    /**
     * @var array
     */
    protected static $pathRelation = array(
        'webman/config' => 'config/plugin/tpext/core',
    );

    /**
     * Install
     * @return void
     */
    public static function install()
    {
        $appConfig = file_get_contents(config_path() . '/app.php');

        file_put_contents(config_path() . '/app.php', preg_replace('/([\'\"]request_class[\'\"]\s*=>\s*)[\w\\\]+::class/', '$1\\think\\Request::class', $appConfig));

        echo "use [\\think\\Request::class] as [request_class] in config/app.php\n";

        $exceptionConfig = file_get_contents(config_path() . '/exception.php');

        file_put_contents(config_path() . '/exception.php', preg_replace('/([\'\"][\'\"]\s*=>\s*)[\w\\\]+Handler::class/', '$1\\think\\exception\\ExceptionHandler::class', $exceptionConfig));

        echo "use [\\think\\exception\\ExceptionHandler::class] as [handler] in config/exception.php\n";

        static::installByRelation();
    }

    /**
     * Uninstall
     * @return void
     */
    public static function uninstall()
    {
        $appConfig = file_get_contents(config_path() . '/app.php');

        file_put_contents(config_path() . '/app.php', preg_replace('/([\'\"]request_class[\'\"]\s*=>\s*)[\w\\\]+::class/', '$1\\support\\Request::class', $appConfig));

        echo "revert [\\support\\Request::class] as [request_class] in config/app.php\n";

        $exceptionConfig = file_get_contents(config_path() . '/exception.php');

        file_put_contents(config_path() . '/exception.php', preg_replace('/([\'\"][\'\"]\s*=>\s*)[\w\\\]+Handler::class/', '$1\\support\\exception\\Handler::class', $exceptionConfig));

        echo "revert [\\support\\exception\\Handler::class] as [handler] in config/exception.php\n";

        self::uninstallByRelation();
    }

    /**
     * installByRelation
     * @return void
     */
    public static function installByRelation()
    {
        foreach (static::$pathRelation as $source => $dest) {
            if ($pos = strrpos($dest, '/')) {
                $parent_dir = base_path() . '/' . substr($dest, 0, $pos);
                if (!is_dir($parent_dir)) {
                    mkdir($parent_dir, 0777, true);
                }
            }
            copy_dir(__DIR__ . "/$source", base_path() . "/$dest");
            echo "Create $dest
";
        }
    }

    /**
     * uninstallByRelation
     * @return void
     */
    public static function uninstallByRelation()
    {
        foreach (static::$pathRelation as $source => $dest) {
            $path = base_path() . "/$dest";
            if (!is_dir($path) && !is_file($path)) {
                continue;
            }
            echo "Remove $dest
";
            if (is_file($path) || is_link($path)) {
                unlink($path);
                continue;
            }
            remove_dir($path);
        }
    }
}
