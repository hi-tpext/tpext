<?php

namespace tpext\common;

use Webman\Context;

class Share
{
    /**
     * Undocumented function
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public static function set($key, $value)
    {
        Context::set($key, $value);
    }

    /**
     * Undocumented function
     *
     * @param string $key
     * @return mixed
     */
    public static function get($key)
    {
        return Context::get($key);
    }
}
