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
     * 扩展的根目录
     * 代码放在 src 里面的 为 __DIR__ . '/../../'
     * --/assets/
     * --/src/common/Module.php
     *
     * 否则为 __DIR__ . '/../'
     * --/assets/
     * --/common/Module.php
     *
     * @var string
     */
    protected $__root__ = '';

    protected $root = '';

    /**
     * 名称标识 ，英文字母，如 hello.world
     *
     * @var string
     */
    protected $name = '';

    /**
     * 分类标记，用 , 分割 如 'template,mobile'
     *
     * @var string
     */
    protected $tags = '';

    /**
     * 显示名称，如 你好世界
     *
     * @var string
     */
    protected $title = '';

    /**
     * css\js资源路径
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

    protected $config = [];

    

    /**
     * 获取实列
     *
     * @return self
     */
    final public static function getInstance()
    {
        $class = get_called_class();

        if (!isset(self::$extensions[$class]) || !self::$extensions[$class] instanceof $class) {
            self::$extensions[$class] = new static();
        }

        return self::$extensions[$class];
    }

    final public static function extensionsList()
    {
        return self::$extensions;
    }

    final public function getRoot()
    {
        if (!$this->root) {

            if (!$this->__root__) {

                $this->__root__ = __DIR__ . '/../../';
            }

            $this->root = realpath($this->__root__) . DIRECTORY_SEPARATOR;
        }

        return $this->root;
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

    final public function copyAssets($src)
    {
        if (empty($src)) {
            return false;
        }

        $name = $this->assetsDirName();

        $assetsDir = Tool::checkAssetsDir($name);

        if (!$assetsDir) {

            return true;
        }

        return Tool::copyDir($src, $assetsDir);
    }

    final public function assetsDirName()
    {
        $name = $this->getName();

        if (empty($name)) {
            $name = get_called_class();
        }

        $name = preg_replace('/\W/', '', $name);

        return $name;
    }

    public function configPath()
    {
        return realpath($this->getRoot() . 'src' . DIRECTORY_SEPARATOR . 'config.php');
    }

    public function loadConfig()
    {
        if(empty($this->config))
        {
            $configPath = $this->configPath();

            if (is_file($configPath)) {

                $this->config = include $configPath;
            }
        }

        config($this->getId(), $this->config);

        return $this->config;
    }

    public function setConfig($data = [])
    {
        $this->config = array_merge($this->config, $data);

        config($this->getId(), $this->config);

        return $this->config;
    }

    public function preInstall()
    {
        return true;
    }

    public function install()
    {
        return true;
    }

    public function afterInstall()
    {
        return true;
    }

    public function preUninstall()
    {
        return true;
    }

    public function uninstall()
    {
        return true;
    }

    public function afterUninstall()
    {
        return true;
    }

    abstract public function autoCheck();
}
