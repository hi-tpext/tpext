<?php

namespace think\route;

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
