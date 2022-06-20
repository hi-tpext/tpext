<?php

namespace think\route;

/**
 * 控制器基础类
 */
class Url
{
    protected $url;

    public function __construct($url)
    {
        $this->url = $url;
    }

    public function __toString()
    {
        return $this->url;
    }
}
