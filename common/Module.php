<?php

namespace tpsc\common;

abstract class Module
{
    protected $name = 'tpsc.module';

    protected $modules = [];

    protected $namespaceMap = [];

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

    public function getModules()
    {
        return $this->modules;
    }

    public function getNameSpaceMap()
    {
        return $this->namespaceMap;
    }

    abstract public function moduleInit();
}
