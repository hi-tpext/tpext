<?php

namespace tpext\common;

use think\facade\Lang;
use tpext\common\model\Extension as ExtensionModel;
use tpext\common\model\WebConfig;
use tpext\think\App;

abstract class Extension
{
    protected static $extensions = [];

    /**
     * @var string
     */
    protected $__root__ = null;

    /**
     * @var string
     */
    protected $__ID__ = null;

    /**
     * @var array
     */
    protected $__config__ = null;

    /**
     * @var string
     */
    protected $__config_path__ = null;

    protected $errors = [];

    /***以下为需要设置的字段***/

    /**
     * 版本号，不必每次调整都修改。一般在[assets]静态资源、[data]数据脚本更新后修改版本号。
     *
     * @var string
     */
    protected $version = '1.0.1';

    /**
     * 扩展包类型:extend|composer
     *
     * @var string
     */
    protected $packgeType = '';

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
     * 扩展根目录
     *
     * @var string
     */
    protected $root = null; // 请设置 如: __DIR__ . '/../../'

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

    /**
     * 版本列表，版本号 => 升级脚本
     * 没有数据库改动的版本可以留空，未列出的版本不影响升级脚本的执行
     * 升级时会自动按版本号升序执行所有大于已安装版本、且不超过当前版本的脚本
     *
     * @var array
     */
    protected $versions = [
        // '1.0.1' => '',
        // '1.0.2' => 'upgrade-1.0.2.sql',
        // '1.0.3' => '', //如果升级不涉及数据库改动，留空
    ];

    /**
     * 获取扩展包类型:extend|composer
     *
     * @return string
     */
    final public function getPackgeType()
    {
        if (empty($this->packgeType)) {
            $this->packgeType = stripos($this->getRoot(), 'vendor') === false ? 'extend' : 'composer';
        }

        return $this->packgeType;
    }

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
        if (empty($this->namespaceMap)) {
            if ($this->isExtend()) {
                $path = $this->getRoot();
                $namespace = trim(str_replace(App::getRootPath() . 'extend', '', $path), DIRECTORY_SEPARATOR);
                $this->namespaceMap = [str_replace('/', '\\', $namespace), $path];
            } else {
                $this->namespaceMap = Tool::getNameSpaceMap(get_called_class());
            }
        }

        return $this->namespaceMap;
    }

    final public static function extensionsList()
    {
        return self::$extensions;
    }

    /**
     * 获取实列
     *
     * @return static|Module|Resource
     */
    final public static function getInstance()
    {
        $class = get_called_class();

        if (!isset(self::$extensions[$class])) {
            $instance = new static();
            $instance->i18n();
            $instance->created();
            self::$extensions[$class] = $instance;
        }

        return self::$extensions[$class];
    }

    final public function getRoot()
    {
        if (empty($this->__root__)) {

            if (empty($this->root)) {
                throw new \UnexpectedValueException('root is unset:' . get_called_class());
            }

            $this->__root__ = realpath($this->root) . DIRECTORY_SEPARATOR;
        }

        return $this->__root__;
    }

    final public function isComposer()
    {
        return $this->getPackgeType() == 'composer';
    }

    final public function isExtend()
    {
        return $this->getPackgeType() == 'extend';
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
            $lang = TpextCore::getInstance()->getLang('common');
            file_put_contents($assetsDir . 'tpext-warning.txt', $lang['copy_assets_alert'] ?? '');
        }

        $this->afterCopyAssets();

        ExtLoader::trigger('tpext_copy_assets', $this->getId());

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

    /**
     * 获取配置文件放置目录
     *
     * @return string
     */
    public function configPath()
    {
        if (!$this->__config_path__) {
            if (is_file($this->getRoot() . 'config.php')) {
                $this->__config_path__ = $this->getRoot() . 'config.php';
            } else {
                //composer包，可能为src目录下
                //建议composer包取消src目录，代码直接放在扩展根目录，可以同时支持composer和extend两种模式
                $this->__config_path__ = $this->getRoot() . 'src' . DIRECTORY_SEPARATOR . 'config.php';
            }
        }

        return $this->__config_path__;
    }

    /**
     * Undocumented function
     *
     * @param boolean $all 是否包含__config__|__saving__两个参数
     * @return array
     */
    final public function defaultConfig($all = false)
    {
        $configPath = $this->configPath();

        if (is_file($configPath)) {

            $config = include $configPath;

            if (!$all) {
                unset($config['__config__'], $config['__saving__']);
            }

            return $config;
        } else {
            return [];
        }
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    final public function getConfig()
    {
        if ($this->__config__ === null) {

            $defaultConfig = $this->defaultConfig();

            $this->__config__ = $defaultConfig;

            $saved = WebConfig::config($this->getId());

            if (!empty($saved)) {
                $this->__config__ = array_merge($this->__config__, $saved);
            }
        }

        return $this->__config__;
    }

    /**
     * Undocumented function
     *
     * @param string $key
     * @return mixed
     */
    final public static function config($key = null, $default = '')
    {
        $config = static::getInstance()->getConfig();

        if ($key) {
            return isset($config[$key]) ? $config[$key] : $default;
        }

        return $config;
    }

    /**
     * Undocumented function
     *
     * @param array $data
     * @return array
     */
    final public function setConfig($data = [])
    {
        $this->__config__ = array_merge($this->getConfig(), $data);

        return $this->__config__;
    }

    /**
     * Undocumented function
     *
     * @return void
     */
    final public function clearConfig()
    {
        $this->__config__ = null;
    }

    /**
     * Undocumented function
     *
     * @return boolean
     */
    public function install()
    {
        $sqlFile = $this->getRoot() . 'data' . DIRECTORY_SEPARATOR . 'install.sql';

        $success = true;

        if (is_file($sqlFile)) {
            $success = Tool::executeSqlFile($sqlFile, $this->errors);
        }

        if ($success) {
            $ekey = get_called_class();
            $extData = [
                'key' => $ekey,
                'name' => $this->getName(),
                'version' => $this->getVersion(),
                'title' => $this->getTitle(),
                'description' => $this->getDescription(),
                'tags' => $this->getTags(),
                'install' => 1,
                'enable' => 1,
            ];

            if (ExtensionModel::where(['key' => $ekey])->find()) {

                ExtensionModel::where(['key' => $ekey])->update($extData);
            } else {
                $extension = new ExtensionModel;
                $res = $extension->save($extData);
                if (!$res) {
                    return false;
                }
            }

            $config = $this->defaultConfig();

            if (!empty($config)) {

                $filePath = str_replace(App::getRootPath(), '', $this->configPath());

                $confData = [
                    'key' => $this->getId(),
                    'file' => $filePath,
                    'title' => $this->getTitle(),
                    'config' => json_encode($config),
                ];

                if (WebConfig::where(['key' => $this->getId()])->find()) {

                    WebConfig::where(['key' => $this->getId()])->update($confData);
                } else {

                    WebConfig::create($confData);
                }
            }
        }
        $this->copyAssets(true);
        ExtLoader::clearCache();
        ExtLoader::getInstalled(true);

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
        $sqlFile = $this->getRoot() . 'data' . DIRECTORY_SEPARATOR . 'uninstall.sql';

        $success = true;

        if ($runSql && is_file($sqlFile)) {
            $success = Tool::executeSqlFile($sqlFile, $this->errors);
        }

        if ($success) {
            if (get_called_class() != TpextCore::class) {
                ExtensionModel::where(['key' => get_called_class()])->update(['install' => 0, 'enable' => 0]);
                WebConfig::where(['key' => $this->getId()])->delete();
            }
        }

        ExtLoader::clearCache();
        ExtLoader::getInstalled(true);

        return $success;
    }

    /**
     * Undocumented function
     *
     * @return boolean
     */
    public function upgrade()
    {
        $ekey = get_called_class();

        $extension = ExtensionModel::where(['key' => $ekey])->find();

        if (!$extension) {
            $this->errors[] = new \Exception('Not found in the installed extensions : ' . $ekey);
            return false;
        }

        if (version_compare($extension['version'], $this->version) >= 0) {
            $lang = TpextCore::getInstance()->getLang('common');
            $this->errors[] = new \Exception(($lang['lower_version_error'] ?? '') . " original{$extension['version']} new{$this->version}");
            return false;
        }

        if (!$this->onUpgrade($extension['version'], $this->version)) {
            return false;
        }

        ExtensionModel::where(['key' => $ekey])->update(['version' => $this->version]);

        $this->copyAssets(true);
        ExtLoader::clearCache();
        ExtLoader::getInstalled(true);

        return true;
    }

    /**
     * Undocumented function
     *
     * @param string $oldVer
     * @param string $newVer
     * @return boolean
     */
    protected function onUpgrade($oldVer, $newVer)
    {
        $versions = $this->versions;
        if (empty($versions)) {
            return true;
        }

        //按版本号升序排列，保证升级脚本按版本顺序执行
        uksort($versions, 'version_compare');

        $success = 1;
        $sqlPath = $this->getRoot() . 'data' . DIRECTORY_SEPARATOR;
        $sqlFile = '';
        $errors = [];

        foreach ($versions as $key => $sql) {
            //只执行大于旧版本、且不超过目标版本的脚本，即使旧版本未在列表中列出
            if (version_compare($key, $oldVer) <= 0 || version_compare($key, $newVer) > 0) {
                continue;
            }

            if (empty($sql)) {
                $success += 1;
                continue;
            }

            $sqlFile = $sqlPath . $sql;
            if (is_file($sqlFile)) {
                if (Tool::executeSqlFile($sqlFile, $errors)) {
                    $success += 1;
                } else {
                    $this->errors += $errors;
                }
            } else {
                $this->errors[] = new \Exception('file path error : ' . $sqlFile);
                return false;
            }

            if ($key == $newVer) {
                break;
            }
        }

        return $success > 0;
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
     * @param string $region
     * @return string
     */
    final public function getLangPath($name, $app = 'admin', $region = '')
    {
        if (!$name) {
            return '';
        }

        $lang = $region ?: App::getDefaultLang();

        $file = App::getRootPath() . implode(DIRECTORY_SEPARATOR, ['app', $app, 'lang', $lang, $this->assetsDirName(), $name . '.php']);

        if (!is_file($file)) {
            $file = $this->getRoot() . implode(DIRECTORY_SEPARATOR, ['src', $app, 'lang', $lang, $name . '.php']);
        }

        if (!$region && !is_file($file)) {
            return $this->getLangPath($name, $app, 'en');
        }

        return is_file($file) ? $file : '';
    }

    /**
     * Undocumented function
     *
     * @return array
     */
    final public function getErrors()
    {
        return $this->errors;
    }

    /**
     * Undocumented function
     *
     * @return boolean
     */
    public function afterCopyAssets()
    {
        return true;
    }

    /**
     * Undocumented function
     *
     * @param boolean|int $state
     * @return boolean
     */
    public function enabled($state)
    {
        ExtLoader::clearCache();
        ExtLoader::getInstalled(true);

        return true;
    }

    /**
     * 实例被创建以后调用
     *
     * @return $this
     */
    public function created()
    {
        return $this;
    }

    public function i18n()
    {
        $lang = static::getLang('extinfo');
        if ($lang) {
            $this->title = $lang['title'] ?? $this->title;
            $this->tags = $lang['tags'] ?? $this->tags;
            $this->description = $lang['description'] ?? $this->description;
        }
    }

    /**
     * 实例安装并启用，查找到之后调用
     *
     * @return $this
     */
    public function loaded()
    {
        return $this;
    }

    /**
     * @return array
     */
    public function getProtectedTables()
    {
        return [];
    }

    /**
     * Undocumented function
     *
     * @param array $info
     * @return boolean
     */
    abstract public function extInit($info = []);

    public static function __callStatic($method, $params)
    {
        return call_user_func_array([static::getInstance(), $method], $params);
    }
}
