<?php

namespace tpsc\common;

abstract class Plugin
{
    protected $name = 'tpsc.plugin';

    protected $hooks = [
        //...
    ];

    public function getName()
    {
        return $this->name;
    }

    public function getHooks()
    {
        return $this->hooks;
    }

    abstract public function pluginInit();
}
