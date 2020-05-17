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

    /**
     * Undocumented function
     *
     * @return array
     */
    final public function getModules()
    {
        return $this->modules;
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    final public function getMenus()
    {
        return $this->menus;
    }

    /**
     * Undocumented function
     *
     * @return boolean
     */
    public function extInit($info = [])
    {
        $this->pubblish();
        return true;
    }

    /**
     * Undocumented function
     *
     * @return boolean
     */
    public function install()
    {
        $success = parent::install();

        if ($success && !empty($this->menus)) {

            $menus = [];
            foreach ($this->menus as $menu) {
                $menu['module'] = $this->getId();
                $menus[] = $menu;
            }

            ExtLoader::trigger('tpext_menus', ['create', $menus]);
        }

        return $success;
    }

    /**
     * Undocumented function
     *
     * @return boolean
     */
    public function uninstall()
    {
        $success = parent::uninstall();

        if ($success && !empty($this->menus)) {

            $menus = [];
            foreach ($this->menus as $menu) {
                $menu['module'] = $this->getId();
                $menus[] = $menu;
            }

            ExtLoader::trigger('tpext_menus', ['delete', $menus]);
        }

        return $success;
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    public function enabled($state)
    {
        if (!empty($this->menus)) {

            ExtLoader::trigger('tpext_menus', [$state ? 'enable' : 'disable', $this->menus]);
        }
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
