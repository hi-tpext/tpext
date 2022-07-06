<?php

namespace tpext\common;

class Resource extends Extension
{
    public function extInit($info = [])
    {
        return true;
    }

    /**
     * Undocumented function
     *
     * @return boolean
     */
    public function install()
    {
        $success = parent::install();

        if ($success) {
            ExtLoader::reloadWebman('reload for resource [' . $this->getName() . ']'); //重启
        }

        return $success;
    }

    /**
     * Undocumented function
     *
     * @param boolean $runSql
     * @return boolean
     */
    public function uninstall($runSql = true)
    {
        $success = parent::uninstall($runSql);

        if ($success) {
            ExtLoader::reloadWebman('reload for resource [' . $this->getName() . ']'); //重启
        }

        return $success;
    }

    public function upgrade()
    {
        $success = parent::upgrade();

        if ($success) {
            ExtLoader::reloadWebman('reload for resource [' . $this->getName() . ']'); //重启
        }

        return $success;
    }

    /**
     * Undocumented function
     *
     * @param boolean|int $state
     * @return boolean
     */
    public function enabled($state)
    {
        $success = parent::enabled($state);

        if ($success) {
            ExtLoader::reloadWebman('reload for resource [' . $this->getName() . ']'); //重启
        }

        return $success;
    }
}
