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

        $pagezise = 4;

        $table = $builder->table();

        $page = input('__page__/d', 1);

        $table->paginator(count($this->extensions), $pagezise);

        $list = array_slice($this->extensions, ($page - 1) * $pagezise, $pagezise);

        $data = [];

        $install = Extension::readInstall();

        foreach ($list as $key => $li) {
            $is_install = isset($install[$key]) ? $install[$key]['install'] : 0;
            $is_enable = isset($install[$key]) ? $install[$key]['enable'] : 0;
            $data[$key] = [
                'id' => str_replace('\\', '.', $key),
                'install' => $is_install,
                'enable' => $is_enable,
                'name' => $li->getName(),
                'title' => $li->getTitle(),
                'description' => $li->getDescription(),
                'exttype' => $li->getExtType(),
                '__disable_install__' => $is_install,
                '__disable_uninstall__' => !$is_install,
                '__disable_disable__' => !$is_enable,
                '__disable_enable__' => $is_enable,
            ];
        }

        $table->field('name', '标识');
        $table->field('title', '标题');
        $table->match('exttype', '类型')->options(
            ['plugin' => '<label class="label label-success">插件</label>',
                'module' => '<label class="label label-info">模块</label>',
            ]);
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
            ->btnEnable()
            ->btnDisable()
            ->btnRefresh();

        $table->getActionbar()
            ->btnLink('install', url('install', ['id' => '__data.id__']), '', 'btn-primary', 'mdi-plus', 'title="安装"')
            ->btnLink('uninstall', url('uninstall', ['id' => '__data.id__']), '', 'btn-danger', 'mdi-delete', 'title="卸载"')
            ->btnEnable()
            ->btnDisable()
            ->mapClass([
                'install' => ['hidden' => '__disable_install__'],
                'uninstall' => ['hidden' => '__disable_uninstall__'],
                'disable' => ['hidden' => '__disable_disable__'],
                'enable' => ['hidden' => '__disable_enable__'],
            ]);

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

        $id = str_replace('.', '\\', $id);

        if (!isset($this->extensions[$id])) {
            $this->error('扩展不存在！', url('index'));
        }

        $instance = $this->extensions[$id];

        $builder = Builder::getInstance('扩展管理', '安装-' . $instance->getTitle());

        if (request()->isPost()) {
            $res = $instance->install();

            if ($res) {
                return $builder->layer()->closeRefresh(1, '安装成功');
            } else {
                $errors = $instance->getErrors();

                $text = '';
                foreach ($errors as $err) {
                    $text .= $err->getMessage();
                }

                return $builder->content()->display($$text);
            }
        } else {

            $form = $builder->form();

            $form->raw('name', '标识')->value($instance->getName());
            $form->raw('title', '标题')->value($instance->getTitle());
            $form->match('type', '类型')->value($instance->getExtType())->options(
                ['plugin' => '<label class="label label-success">插件</label>',
                    'module' => '<label class="label label-info">模块</label>',
                ]);
            $form->raw('desc', '介绍')->value($instance->getDescription());

            $form->html('', '', 6)->showLabel(false);

            $form->btnSubmit('安装');

            $form->btnLayerClose();

            return $builder->render();
        }

    }

    public function uninstall($id = '')
    {
        $builder = Builder::getInstance('扩展管理');

        return $builder->layer()->closeRefresh();

        if (empty($id)) {
            $this->error('参数有误', url('index'));
        }

        $id = str_replace('.', '\\', $id);

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
        $ids = str_replace('.', '\\', $ids);

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
        $ids = str_replace('.', '\\', $ids);

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
