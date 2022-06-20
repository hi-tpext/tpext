<?php

namespace tpext;

class Install
{
    const WEBMAN_PLUGIN = true;

    /**
     * @var array
     */
    protected static $pathRelation = array(
        'config' => 'config/plugin/tpext',
    );

    /**
     * Install
     * @return void
     */
    public static function install()
    {
        static::installByRelation();
        
        $config_file = config_path() . '/bootstrap.php';
        $config = include $config_file;
        if (!in_array(\tpext\TpextWebman::class, $config ?? [])) {
            $config_file_content = file_get_contents($config_file);
            $config_file_content = preg_replace('/\];/', "    tpext\TpextWebman::class,\n];", $config_file_content);
            file_put_contents($config_file, $config_file_content);
        }
    }

    /**
     * Uninstall
     * @return void
     */
    public static function uninstall()
    {
        $config_file = config_path() . '/bootstrap.php';
        $config = include $config_file;
        if (in_array(\tpext\TpextWebman::class, $config ?? [])) {
            $config_file = config_path() . '/bootstrap.php';
            $config_file_content = file_get_contents($config_file);
            $config_file_content = preg_replace('/ {0,4}tpext\\\\TpextWebman::class,?\r?\n?/', '', $config_file_content);
            file_put_contents($config_file, $config_file_content);
        }

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
            //symlink(__DIR__ . "/$source", base_path()."/$dest");
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
