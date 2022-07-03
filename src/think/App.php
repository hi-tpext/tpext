<?php

namespace tpext\think;

class App
{
    /**
     * Undocumented function
     *
     * @return string
     */
    public static function getAppPath()
    {
        return app()->getAppPath();
    }

    /**
     * Undocumented function
     *
     * @return string
     */
    public static function getConfigPath()
    {
        return app()->getConfigPath();
    }

    /**
     * Undocumented function
     *
     * @return string
     */
    public static function getExtendPath()
    {
        return app()->getRootPath() . 'extend' . DIRECTORY_SEPARATOR;
    }

    /**
     * Undocumented function
     *
     * @return string
     */
    public static function getPublicPath()
    {
        return app()->getRootPath() . 'public' . DIRECTORY_SEPARATOR;
    }

    /**
     * Undocumented function
     *
     * @return string
     */
    public static function getRuntimePath()
    {
        return app()->getRuntimePath();
    }

    /**
     * Undocumented function
     *
     * @return string
     */
    public static function getRootPath()
    {
        return app()->getRootPath();
    }

    /**
     * Undocumented function
     *
     * @return string
     */
    public static function getDefaultLang()
    {
        return config('default_lang');
    }
}
