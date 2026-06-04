<?php

namespace tpext\common;

use think\facade\Lang;
use tpext\think\App;

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
    public function getModules()
    {
        return $this->modules;
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    public function getMenus()
    {
        return $this->menus;
    }

    /**
     * @param string $name
     * @param string $app
     * @return void
     */
    final public function loadLang($name, $app = 'admin')
    {
        $file = $this->getLangPath($name, $app);

        if ($file) {
            Lang::load($file);
        }
    }

    /**
     * @param string $name
     * @param string $app
     * @return array
     */
    final public function getLang($name, $app = 'admin')
    {
        $file = $this->getLangPath($name, $app);

        if ($file) {
            return include $file;
        }

        return [];
    }

    /**
     * @param string $name
     * @param string $app
     * @return string
     */
    final public function getLangPath($name, $app = 'admin')
    {
        if (!$name) {
            return '';
        }

        $file = App::getRootPath() . implode(DIRECTORY_SEPARATOR, ['app', $app, 'lang', App::getDefaultLang(), $this->assetsDirName(), $name . '.php']);

        if (!is_file($file)) {
            $file = $this->getRoot() . implode(DIRECTORY_SEPARATOR, ['src', $app, 'lang', App::getDefaultLang(), $name . '.php']);
        }

        return is_file($file) ? $file : '';
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

        if ($success && !empty($this->modules)) {
            RouteLoader::load(true); //重新生成路由，触发重启
            echo 'reload for module [' . $this->getName() . "]\n";
        }

        return $success;
    }

    /**
     * Undocumented function
     *
     * @param boolean $runSql
     * @return boolean
     */
    public function uninstall($runSql = true)
    {
        $success = parent::uninstall($runSql);

        if ($success && !empty($this->menus)) {

            ExtLoader::trigger('tpext_menus', ['delete', $this->getId(), $this->menus]);
        }

        if ($success && !empty($this->modules)) {
            RouteLoader::load(true); //重新生成路由，触发重启
            echo 'reload for module [' . $this->getName() . "]\n";
        }

        return $success;
    }

    public function upgrade()
    {
        $success = parent::upgrade();

        if ($success && !empty($this->modules)) {
            RouteLoader::load(true); //重新生成路由，触发重启
            echo 'reload for module [' . $this->getName() . "]\n";
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
        $success = parent::enabled($state);

        if ($success && !empty($this->menus)) {

            ExtLoader::trigger('tpext_menus', [$state ? 'enable' : 'disable', $this->getId(), $this->menus]);
        }

        if ($success && !empty($this->modules)) {
            RouteLoader::load(true); //重新生成路由，触发重启
            echo 'reload for module [' . $this->getName() . "]\n";
        }

        return $success;
    }

    public function pubblish()
    {
    }
}
