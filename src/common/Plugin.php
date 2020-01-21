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
        $this->assets = $this->getAssets();

        if (!empty($this->assets)) {

            $this->copyAssets($this->assets);

        }
    }
}
