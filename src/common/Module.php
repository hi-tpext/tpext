<?php

namespace tpext\common;

use think\facade\Request;

class Module extends Extension
{
    public static $current = '';
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

            ExtLoader::trigger('tpext_menus', ['create', $this->getId(), $this->menus]);
        }

        return $success;
    }

    /**
     * Undocumented function
     *
     * @return boolean
     */
    public function uninstall($runSql = true)
    {
        $success = parent::uninstall($runSql);

        if ($success && !empty($this->menus)) {

            ExtLoader::trigger('tpext_menus', ['delete', $this->getId(), $this->menus]);
        }

        return $success;
    }

    /**
     * Undocumented function
     *
     * @param boolean|int $state
     * @return boolean
     */
    public function enabled($state)
    {
        if (!empty($this->menus)) {

            ExtLoader::trigger('tpext_menus', [$state ? 'enable' : 'disable', $this->getId(), $this->menus]);
        }

        return parent::enabled($state);
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

        static::$current = $this->getId();
    }
}
