<?php
namespace tpext\admin\controller;

use think\Controller;

class Tpext extends Controller
{
    public function index($id='')
    {
        return $this->fetch();
    }
}
