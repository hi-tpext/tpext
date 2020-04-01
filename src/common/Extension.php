<?php

namespace tpext\common;

use tpext\common\model\Extension as ExtensionModel;
use tpext\common\model\WebConfig;

abstract class Extension
{
    protected static $extensions = [];

    protected $version = '1.0.1';

    protected $__root__ = null;

    protected $__ID__ = null;

    protected $root = null;

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
    protected $tags = '未归类';

    /**
     * 显示名称，如 你好世界
     *
     * @var string
     */
    protected $title = '未填写';

    /**
     * 显示介绍，如 你好世界是一个什么
     *
     * @var string
     */
    protected $description = '未填写';

    /**
     * css\js资源路径
     * @var string
     */
    protected $assets = '';

    /**
     * 命名空间和路径，一般不用填写 如 ['namespace', 'codepath']
     *
     * @var array
     */
    protected $namespaceMap = [];

    protected $config = null;

    protected $errors = [];

    final public function getName()
    {
        return $this->name;
    }

    final public function getTitle()
    {
        return empty($this->title) ? $this->getName() : $this->title;
    }

    final public function getDescription()
    {
        return $this->description;
    }

    final public function getTags()
    {
        return $this->tags;
    }

    final public function getVersion()
    {
        return $this->version;
    }

    final public function getId()
    {
        if (empty($this->__ID__)) {
            $this->__ID__ = strtolower(preg_replace('/\W/', '_', get_called_class()));
        }

        return $this->__ID__;
    }

    final public function getAssets()
    {
        return $this->assets;
    }

    final public function getNameSpaceMap()
    {
        return $this->namespaceMap;
    }

    final public static function extensionsList()
    {
        return self::$extensions;
    }

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

    final public function getRoot()
    {
        if (empty($this->__root__)) {

            if (empty($this->root)) {
                throw new \UnexpectedValueException('root未设置:' . get_called_class());
            }

            $this->__root__ = realpath($this->root) . DIRECTORY_SEPARATOR;
        }

        return $this->__root__;
    }

    final public function copyAssets($force = false)
    {
        if (empty($this->assets)) {
            return true;
        }

        $src = $this->getRoot() . $this->assets . DIRECTORY_SEPARATOR;

        $name = $this->assetsDirName();

        $assetsDir = Tool::checkAssetsDir($name);

        if (!$assetsDir) {

            if (!$force) {
                return true;
            }

            Tool::clearAssetsDir($name);
        }
        $res = Tool::copyDir($src, $assetsDir);

        if ($res) {
            file_put_contents($assetsDir . 'tpext-warning.txt',
                '此目录是存放扩展静态资源的，' . "\n"
                . '不要替换文件或上传新文件到此目录及子目录，' . "\n"
                . '否则刷新扩展资源后文件将还原或丢失，' . "\n"
                . '文件建议传到根目录的`public/static`目录下。'
            );
        }

        return $res;
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

    final public function configPath()
    {
        return realpath($this->getRoot() . 'src' . DIRECTORY_SEPARATOR . 'config.php');
    }

    final public function defaultConfig()
    {
        $configPath = $this->configPath();

        if (is_file($configPath)) {

            return include $configPath;
        } else {
            return [];
        }
    }

    final public function getConfig()
    {
        if ($this->config === null) {

            $defaultConfig = $this->defaultConfig();

            $this->config = $defaultConfig;

            if (!empty($defaultConfig)) {

                $installed = ExtLoader::getInstalled();

                foreach ($installed as $install) {
                    if ($install['key'] == get_called_class()) {
                        $config = WebConfig::where(['key' => $this->getId()])->find();
                        if ($config) {
                            $this->setConfig(json_decode($config['config'], 1));
                        }
                        unset($this->config['__config__']);
                        break;
                    }
                }
            }

            config($this->getId(), $this->config);
        }

        return $this->config;
    }

    final public function setConfig($data = [])
    {
        $this->config = array_merge($this->defaultConfig(), $data);

        config($this->getId(), $this->config);
    }

    public function install()
    {
        $sqlFile = realpath($this->getRoot() . 'data' . DIRECTORY_SEPARATOR . 'install.sql');

        $success = true;

        if (is_file($sqlFile)) {
            $success = Tool::executeSqlFile($sqlFile, $this->errors);
        }

        if ($success) {
            $this->copyAssets();

            ExtensionModel::create([
                'key' => get_called_class(),
                'name' => $this->getName(),
                'title' => $this->getTitle(),
                'description' => $this->getDescription(),
                'tags' => $this->getTags(),
                'install' => 1,
                'enable' => 1,
            ]);

            $config = $this->defaultConfig();

            if (!empty($config)) {
                unset($config['__config__']);
                $filePath = str_replace(app()->getRootPath(), '', $this->configPath());

                WebConfig::create(['key' => $this->getId(), 'file' => $filePath, 'title' => $this->getTitle(), 'config' => json_encode($config)]);
            }

            ExtLoader::getInstalled(true);
            ExtLoader::clearCache();
        }

        return $success;
    }

    public function uninstall()
    {
        $sqlFile = realpath($this->getRoot() . 'data' . DIRECTORY_SEPARATOR . 'uninstall.sql');

        $success = true;

        if (is_file($sqlFile)) {
            $success = Tool::executeSqlFile($sqlFile, $this->errors);
        }

        if ($success) {
            ExtensionModel::where(['key' => get_called_class()])->delete();

            WebConfig::where(['key' => $this->getId()])->delete();

            ExtLoader::getInstalled(true);

            ExtLoader::clearCache();
        }

        return $success;
    }

    final public function getErrors()
    {
        return $this->errors;
    }

    abstract public function pubblish();
}
