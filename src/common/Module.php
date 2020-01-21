<?php

namespace tpext\common;

use think\facade\Request;

class Module extends Extension
{
    public const VERSION = '1.0.1';

    /**
     * 模块定义，如 ['module1' => ['controller1','controller2']]
     *
     * @var array
     */
    protected $modules = [];

    /**
     * @var array
     */
    protected $menus = [];

    /**
     * @var array
     */
    protected $permissions = [];

    final public function getMenus()
    {
        return $this->menus;
    }

    final public function getPermissionss()
    {
        return $this->permissions;
    }

    final public function getModules()
    {
        return $this->modules;
    }

    public function moduleInit($info = [])
    {
        return true;
    }

    public function autoCheck()
    {
        $this->assets = $this->getAssets();

        if (!empty($this->assets)) {

            $this->copyAssets($this->assets);

            $name = $this->assetsDir();

            $base_file = Request::baseFile();

            $base_dir = substr($base_file, 0, strripos($base_file, '/') + 1);

            $PUBLIC_PATH = $base_dir;

            $tpl_replace_string = [
                '__ASSETS__' => $PUBLIC_PATH . 'assets',
                '__M_DIR__' => $name,
                '__MODULE__' => $PUBLIC_PATH . 'assets/' . $name,
            ];

            config('template.tpl_replace_string', $tpl_replace_string);
        }
    }
}
