<?php

namespace tpext\common;

class Share
{
    /**
     * Undocumented variable
     *
     * @var array
     */
    protected static $data = [];

    /**
     * Undocumented function
     *
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public static function set($key, $value)
    {
        static::$data[$key] = $value;
    }

    /**
     * Undocumented function
     *
     * @param string $key
     * @return mixed
     */
    public static function get($key)
    {
        return static::$data[$key] ?? null;
    }
}
