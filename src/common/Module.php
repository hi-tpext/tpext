<?php

namespace tpext\common;

use think\facade\Request;

class Module
{
    /**
     * 名称标识 ，英文字母，如 hello.world
     *
     * @var string
     */
    protected static $name = null;

    /**
     * 显示名称，如 你好世界
     *
     * @var string
     */
    protected static $title = '';

    /**
     * 模块定义，如 ['module1' => ['controller1','controller2']]
     *
     * @var array
     */
    protected static $modules = [];

    /**
     * @var string
     */
    protected static $assets = '';

    /**
     * @var array
     */
    protected static $menus = [];

    /**
     * @var array
     */
    protected static $permissions = [];

    /**
     * 命名空间和路径，一般不用填写 如 ['namespace', 'codepath']
     *
     * @var array
     */
    protected static $namespaceMap = [];

    public static function getName()
    {
        return static::$name;
    }

    public static function getTitle()
    {
        return empty(static::$title) ? static::getName() : static::$title;
    }

    final public static function getId()
    {
        return preg_replace('/\W/', '', get_called_class());
    }

    public static function getAssets()
    {
        return static::$assets;
    }

    public static function getMenus()
    {
        return static::$menus;
    }

    public static function getPermissionss()
    {
        return static::$permissions;
    }

    public static function getModules()
    {
        return static::$modules;
    }

    public static function getNameSpaceMap()
    {
        return static::$namespaceMap;
    }

    public static function moduleInit($info = [])
    {
        return true;
    }

    final public static function autoCheck()
    {
        static::$assets = static::getAssets();

        if (!empty(static::$assets)) {

            static::copyAssets(static::$assets);

            $name = static::assetsDir();

            $base_file = Request::baseFile();

            $base_dir = substr($base_file, 0, strripos($base_file, '/') + 1);

            $PUBLIC_PATH = $base_dir;

            $tpl_replace_string = [
                '__ASSETS__' => $PUBLIC_PATH . 'assets',
                '__MDULE__' => $name,
            ];

            config('template.tpl_replace_string', $tpl_replace_string);
        }
    }

    public static function copyAssets($src)
    {
        if (empty($src)) {
            return false;
        }

        $name = static::assetsDir();

        $assetsDir = Tool::checkAssetsDir($name);

        if (!$assetsDir) {

            return true;
        }

        return Tool::copyDir($src, $assetsDir);
    }

    public static function assetsDir()
    {
        $name = static::getName();

        if (empty($name)) {
            $name = get_called_class();
        }

        $name = preg_replace('/\W/', '', $name);

        return $name;
    }
}
