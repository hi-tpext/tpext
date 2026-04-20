<?php

namespace tpext;

class Install
{
    const WEBMAN_PLUGIN = true;

    /**
     * @var array
     */
    protected static $pathRelation = array(
        'webman/config/app.php' => 'config/plugin/tpext/core/app.php',
        'webman/config/lang.php' => 'config/plugin/tpext/core/lang.php',
        'webman/config/bootstrap.php' => 'config/plugin/tpext/core/bootstrap.php',
        'webman/config/middleware.php' => 'config/plugin/tpext/core/middleware.php',
    );

    /**
     * Install
     * @return void
     */
    public static function install()
    {
        $appConfig = file_get_contents(config_path() . '/app.php');

        file_put_contents(config_path() . '/app.php', preg_replace('/([\'\"]request_class[\'\"]\s*=>\s*)[:\w\\\]+/i', '$1Request::class', $appConfig));

        echo "use [support\\Request::class] as [request_class] in config/app.php\n";

        $request = file_get_contents(base_path() . '/support/Request.php');

        file_put_contents(base_path() . '/support/Request.php', preg_replace('/(class\s+Request\s+extends\s+)[\w\\\]+/i', '$1\\think\\Request', $request));

        echo "let [support\\Request] extends [think\\Request] in support/Request.php\n";

        static::installByRelation();

        static::composer();
    }

    /**
     * Uninstall
     * @return void
     */
    public static function uninstall()
    {
        $appConfig = file_get_contents(base_path() . '/app.php');

        file_put_contents(base_path() . '/app.php', preg_replace('/([\'\"]request_class[\'\"]\s*=>\s*)[:\w\\\]+/i', '$1Request::class', $appConfig));

        echo "use [support\\Request::class] as [request_class] in config/app.php\n";

        $request = file_get_contents(base_path() . '/support/Request.php');

        file_put_contents(base_path() . '/support/Request.php', preg_replace('/(class\s+Request\s+extends\s+)[\w\\\]+/i', '$1\\Webman\\Http\\Request', $request));

        echo "let [support\\Request] extends [Webman\\Http\\Request] in support/Request.php\n";

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
                    mkdir($parent_dir, 0755, true);
                }
            }
            if (($source == 'webman/config/app.php' || $source == 'webman/config/lang.php')
                && is_file(base_path() . "/$dest")
            ) {
                continue;
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

    public static function composer()
    {
        if (!is_dir(base_path() . '/extend/')) {
            mkdir(base_path() . '/extend/', 0775);
        }

        $json = json_decode(file_get_contents(base_path() . '/composer.json'), true);

        $rewrite = false;
        if (empty($json['autoload'])) {
            $json['autoload'] = [
                "psr-0" => [
                    "" => "extend/"
                ]
            ];
            $rewrite = true;
        } else {
            if (empty($json['autoload']['psr-0'])) {
                $json['autoload']['psr-0'] = [
                    "" => "extend/"
                ];
                $rewrite = true;
            } else {
                if (!in_array('extend/', $json['autoload']['psr-0'])) {
                    $json['autoload']['psr-0'][''] = "extend/";
                    $rewrite = true;
                }
            }
        }

        if (!$rewrite) {
            return;
        }

        echo 'regist path [/extend] succeeded, composer.json was updated' . "\n";

        file_put_contents(base_path() . '/composer.json', json_encode($json, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT));
    }
}
