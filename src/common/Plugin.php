<?php

namespace tpext\common;

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
        }
    }
}
