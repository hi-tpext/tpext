<?php

namespace tpext\common;

use think\facade\Request;

class Module extends Extension
{
    /**
     * 模块定义，如 ['module1' => ['controller1','controller2']]
     * 或者 ['module1' => ['controllerdir\\controller1','controllerdir\\controller2']] (controller 目录下又分目录的情况)
     *
     * @var array
     */
    protected $modules = [];

    final public function getModules()
    {
        return $this->modules;
    }

    public function moduleInit($info = [])
    {
        return true;
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

    public function editConfig()
    {
        return false;
    }
}
