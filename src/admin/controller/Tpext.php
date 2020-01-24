<?php
namespace tpext\admin\controller;

use think\Controller;
use tpext\common\Extension;

class Tpext extends Controller
{
    protected $extensions = [];

    public function __construct()
    {
        $this->extensions = Extension::extensionsList();
    }

    public function index($type = 'module')
    {
        return $this->fetch();
    }

    public function install($id = '')
    {
        if (empty($id)) {
            $this->error('参数有误！', url('index'));
        }

        $class = preg_replace('/\./', '\\', $id);

        if (!isset($this->extensions[$class])) {
            $this->error('扩展不存在！', url('index'));
        }

        return $this->fetch();
    }

    public function uninstall($id = '')
    {
        if (empty($id)) {
            $this->error('参数有误', url('index'));
        }

        $class = preg_replace('/\./', '\\', $id);

        if (!isset($this->extensions[$class])) {
            $this->error('扩展不存在！', url('index'));
        }

        return $this->fetch();
    }
}
