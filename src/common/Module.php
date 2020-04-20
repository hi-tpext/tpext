<?php

namespace tpext\common;

use think\facade\Request;

class Module extends Extension
{
    /**
     * 模块定义，如 $modules = ['module1' => ['controller1','controller2']]
     *
     * @var array
     */
    protected $modules = [];

    /**
     * 后台菜单
     *
     * @var array
     */
    protected $menus = [];

    final public function getModules()
    {
        return $this->modules;
    }

    final public function getMenus()
    {
        return $this->menus;
    }

    public function extInit($info = [])
    {
        $this->pubblish();
        return true;
    }

    public function install()
    {
        $success = parent::install();

        if ($success && !empty($this->menus)) {
            ExtLoader::trigger('tpext_menus', ['create', $this->menus]);
        }

        return $success;
    }

    public function uninstall()
    {
        $success = parent::uninstall();

        if ($success && !empty($this->meuns)) {
            ExtLoader::trigger('tpext_menus', ['delete', $this->meuns]);
        }

        return $success;
    }

    public function pubblish()
    {
        $name = $this->assetsDirName();

        $base_file = Request::baseFile();

        $base_dir = substr($base_file, 0, strripos($base_file, '/') + 1);

        $PUBLIC_PATH = $base_dir;

        $tpl_replace_string = config('template.tpl_replace_string');

        if (empty($tpl_replace_string)) {

            $tpl_replace_string = [];
        }

        $tpl_replace_string = array_merge($tpl_replace_string, [
            '__ASSETS__' => $PUBLIC_PATH . 'assets',
            '__M_NAME__' => $name,
            '__MODULE__' => $PUBLIC_PATH . 'assets/' . $name,
            strtoupper('__' . $name . '__') => $PUBLIC_PATH . 'assets/' . $name,
        ]);

        config('template.tpl_replace_string', $tpl_replace_string);
    }
}
