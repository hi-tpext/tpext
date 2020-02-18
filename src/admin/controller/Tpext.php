<?php
namespace tpext\admin\controller;

use think\Controller;
use tpext\builder\common\Builder;
use tpext\common\Extension;

class Tpext extends Controller
{
    protected $extensions = [];

    public function __construct()
    {
        $this->extensions = Extension::extensionsList();

        ksort($this->extensions);
    }

    public function index($type = 'module')
    {
        $builder = Builder::getInstance('扩展管理', '列表');

        $pagezise = 10;

        $table = $builder->table();

        $page = input('__page__/d', 1);

        $table->paginator(count($this->extensions), $pagezise);

        $list = array_slice($this->extensions, ($page - 1) * $pagezise, $pagezise);

        $data = [];

        $install = Extension::readInstall();

        foreach ($list as $key => $li) {
            $data[$key] = [
                'id' => $key,
                'install' => isset($install[$key]) ? $install[$key]['install'] : 0,
                'enable' => isset($install[$key]) ? $install[$key]['enable'] : 0,
                'name' => $li->getName(),
                'title' => $li->getTitle(),
                'description' => $li->getDescription(),
                'exttype' => $li->getExtType(),
            ];
        }

        $table->match('exttype', '类型')->options(
            ['plugin' => '<label class="label label-success">插件</label>',
                'module' => '<label class="label label-info">模块</label>',
            ]);

        $table->field('name', '标识');
        $table->field('title', '名称');
        $table->field('description', '介绍');

        $table->match('install', '安装')->options(
            [
                0 => '<label class="label label-secondary">未安装</label>',
                1 => '<label class="label label-success">已安装</label>',
            ]);

        $table->match('enable', '启用')->options(
            [
                0 => '<label class="label label-secondary">未启用</label>',
                1 => '<label class="label label-success">已启用</label>',
            ]);

        $table->getToolbar()
            ->btnDisable()
            ->btnEnable()
            ->btnRefresh();

        $table->getActionbar()
            ->btnLink(url('install'), '', 'btn-primary', 'mdi-plus', true, 'title="安装"')
            ->btnLink(url('uninstall'), '', 'btn-danger', 'mdi-delete', true, 'title="卸载"')
            ->btnDisable()
            ->btnEnable();

        $table->data($data);

        if (request()->isAjax()) {

            return $table->partial()->render();
        }

        return $builder->render();
    }

    public function install($id = '')
    {
        if (empty($id)) {
            $this->error('参数有误！', url('index'));
        }

        if (!isset($this->extensions[$id])) {
            $this->error('扩展不存在！', url('index'));
        }

        $instance = $this->extensions[$id];

        $res = $instance->install();

        if ($res) {
            $this->success('成功');
        }

        $this->error('失败');
    }

    public function uninstall($id = '')
    {
        if (empty($id)) {
            $this->error('参数有误', url('index'));
        }

        if (!isset($this->extensions[$id])) {
            $this->error('扩展不存在！', url('index'));
        }

        $instance = $this->extensions[$id];

        $res = $instance->uninstall();

        if ($res) {
            $this->success('成功');
        }

        $this->error('失败');
    }

    public function enable()
    {
        $ids = input('ids', '');

        $ids = explode(',', $ids);

        if (empty($ids)) {
            $this->error('参数有误');
        }

        $install = Extension::readInstall();

        foreach ($install as $key => &$data) {
            if (in_array($key, $ids)) {
                $data['enable'] = 1;
            }
        }

        Extension::writeInstall($install);

        $this->success('成功');
    }

    public function disable()
    {
        $ids = input('ids', '');

        $ids = explode(',', $ids);

        if (empty($ids)) {
            $this->error('参数有误');
        }

        $install = Extension::readInstall();

        foreach ($install as $key => &$data) {
            if (in_array($key, $ids)) {
                $data['enable'] = 0;
            }
        }

        Extension::writeInstall($install);

        $this->success('成功');
    }
}
