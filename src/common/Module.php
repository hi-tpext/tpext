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
     * 数据库表保护，禁止代码生成以及修改表结构
     *
     * @var array 
     */
    protected static $protectedTables = [];

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
     * Undocumented function
     *
     * @return array
     */
    public function getProtectedTables()
    {
        $class = get_called_class();

        if (empty(self::$protectedTables[$class])) {
            $sqlFile = $this->getRoot() . 'data' . DIRECTORY_SEPARATOR . 'install.sql';
            if (is_file($sqlFile)) {
                $content = file_get_contents($sqlFile);
                preg_match_all('/CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?\s*`(\w+)`/is', $content, $matches);
                self::$protectedTables[$class] = isset($matches[1]) && count($matches[1]) > 0 ? $matches[1] : ['_empty_'];
            } else {
                self::$protectedTables[$class] = ['_empty_'];
            }
        }

        return self::$protectedTables[$class];
    }

    /**
     * @param string $name
     * @param string $app
     * @return void
     */
    final public function loadLang($name, $app = 'admin')
    {
        if (!$name) {
            return;
        }
        $file = App::getRootPath() . implode(DIRECTORY_SEPARATOR, ['app', $app, 'lang', App::getDefaultLang(), $this->assetsDirName(), $name . '.php']);
        if (!is_file($file)) {
            $file = $this->getRoot() . implode(DIRECTORY_SEPARATOR, [$app, 'lang', App::getDefaultLang(), $name . '.php']);
        }
        Lang::load($file);
    }

    /**
     * @param string $name
     * @param string $app
     * @return array
     */
    final public function getLang($name, $app = 'admin')
    {
        if (!$name) {
            return [];
        }

        $file = App::getRootPath() . implode(DIRECTORY_SEPARATOR, ['app', $app, 'lang', App::getDefaultLang(), $this->assetsDirName(), $name . '.php']);
        if (!is_file($file)) {
            $file = $this->getRoot() . implode(DIRECTORY_SEPARATOR, [$app, 'lang', App::getDefaultLang(), $name . '.php']);
        }

        if (is_file($file)) {
            return include $file;
        }

        return [];
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
