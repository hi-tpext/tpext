<?php

namespace tpext\common;

use think\facade\Request;

class Plugin extends Extension
{
    public function pluginInit($info = [])
    {
        return true;
    }

    public function autoCheck()
    {
        if (!empty($this->assets)) {

            $this->copyAssets($this->getRoot() . $this->assets . DIRECTORY_SEPARATOR);

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
                strtoupper('__' . $name . '__') => $PUBLIC_PATH . 'assets/' . $name,
            ]);

            config('template.tpl_replace_string', $tpl_replace_string);
        }
    }
}
