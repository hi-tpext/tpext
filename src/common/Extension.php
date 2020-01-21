<?php

namespace tpext\common;

abstract class Extension
{
    /**
     * Extensions.
     *
     * @var Extension
     */
    protected static $extensions = [];

    public const VERSION = '1.0.1';

    /**
     * 名称标识 ，英文字母，如 hello.world
     *
     * @var string
     */
    protected $name = '';

    /**
     * 显示名称，如 你好世界
     *
     * @var string
     */
    protected $title = '';

    /**
     * css\js资源路径，绝对路径
     * @var string
     */
    protected $assets = '';

    /**
     * 安装时的sql路径，绝对路径
     * @var string
     */
    protected $installSql = '';

    /**
     * 卸载时的sql路径，绝对路径
     * @var string
     */
    protected $uninstallSql = '';

    /**
     * 命名空间和路径，一般不用填写 如 ['namespace', 'codepath']
     *
     * @var array
     */
    protected $namespaceMap = [];

    /**
     * 获取实列
     *
     * @return self
     */
    public static function getInstance()
    {
        $class = get_called_class();

        if (!isset(self::$extensions[$class]) || !self::$extensions[$class] instanceof $class) {
            self::$extensions[$class] = new static();
        }

        return self::$extensions[$class];
    }

    final public function getName()
    {
        return $this->name;
    }

    final public function getTitle()
    {
        return empty($this->title) ? $this->getName() : $this->title;
    }

    final public function getId()
    {
        return preg_replace('/\W/', '', get_called_class());
    }

    final public function getAssets()
    {
        return $this->assets;
    }

    final public function getNameSpaceMap()
    {
        return $this->namespaceMap;
    }

    public function copyAssets($src)
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

    public function assetsDir()
    {
        $name = static::getName();

        if (empty($name)) {
            $name = get_called_class();
        }

        $name = preg_replace('/\W/', '', $name);

        return $name;
    }

    public function install()
    {

    }

    public function uninstall()
    {

    }

    abstract public function autoCheck();
}
