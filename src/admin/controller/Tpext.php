<?php
namespace tpext\admin\controller;

use think\Controller;
use tpext\admin\model\Extension;
use tpext\builder\common\Builder;
use tpext\common\ExtLoader;
use tpext\common\TpextModule;

class Tpext extends Controller
{
    protected $extensions = [];

    protected function initialize()
    {
        $this->extensions = ExtLoader::getModules();
        ksort($this->extensions);
    }

    public function index()
    {
        $builder = Builder::getInstance('扩展管理', '列表');

        $pagezise = 10;

        $table = $builder->table();

        $page = input('__page__/d', 1);

        if ($page < 1) {
            $page = 1;
        }

        $table->paginator(count($this->extensions), $pagezise);

        $extensions = array_slice($this->extensions, ($page - 1) * $pagezise, $pagezise);

        $data = [];

        $installed = ExtLoader::getInstalled();

        if (empty($installed)) {
            $builder->notify('已安装扩展为空！请确保数据库连接正常，然后安装[tpext.core]', 'warning', 10000);
        }

        foreach ($extensions as $key => $instance) {
            $is_install = 0;
            $is_enable = 0;
            $has_config = !empty($instance->defaultConfig());

            foreach ($installed as $ins) {
                if ($ins['key'] == $key) {
                    $is_install = $ins['install'];
                    $is_enable = $ins['enable'];
                    break;
                }
            }

            $instance->copyAssets();

            $data[$key] = [
                'id' => str_replace('\\', '.', $key),
                'install' => $is_install,
                'enable' => $is_enable,
                'name' => $instance->getName(),
                'title' => $instance->getTitle(),
                'description' => $instance->getDescription(),
                'version' => $instance->getVersion(),
                'tags' => $instance->getTags(),
                '__h_in__' => $is_install,
                '__h_un__' => !$is_install,
                '__h_st__' => !$is_install || !$has_config,
                '__h_en__' => $is_enable,
                '__h_dis__' => !$is_install || !$is_enable,
                '__h_cp__' => empty($instance->getAssets()),
                '__d_un__' => 0,
            ];

            if ($key == TpextModule::class) {
                $data[$key]['__h_un__'] = 0;
                $data[$key]['__h_st__'] = 1;
                $data[$key]['__h_en__'] = 1;
                $data[$key]['__h_dis__'] = 1;
                $data[$key]['__h_cp__'] = 1;
                $data[$key]['__d_un__'] = 1;
            }
        }

        $table->field('name', '标识');
        $table->field('title', '标题');
        $table->field('tags', '类型');
        $table->field('description', '介绍');
        $table->field('version', '版本号');
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
            ->btnLink('setting', url('config', ['id' => '__data.id__']), '', 'btn-info', 'mdi-settings', 'title="设置"')
            ->btnEnable()
            ->btnDisable()
            ->btnPostRowid('copyAssets', url('copyAssets'), '', 'btn-cyan', 'mdi-refresh', 'title="刷新资源"')
            ->mapClass([
                'install' => ['hidden' => '__h_in__'],
                'uninstall' => ['hidden' => '__h_un__', 'disabled' => '__d_un__'],
                'setting' => ['hidden' => '__h_st__'],
                'enable' => ['hidden' => '__h_en__'],
                'disable' => ['hidden' => '__h_dis__'],
                'copyAssets' => ['hidden' => '__h_cp__'],
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
            return Builder::getInstance('扩展管理')->layer()->close(0, '参数有误！');
        }

        $id = str_replace('.', '\\', $id);

        if (!isset($this->extensions[$id])) {
            return Builder::getInstance('扩展管理')->layer()->close(0, '扩展不存在！');
        }

        $installed = ExtLoader::getInstalled();

        if (empty($installed) && $id != TpextModule::class) {
            return Builder::getInstance('扩展管理')->layer()->close(0, '已安装扩展为空！请确保数据库连接正常，然后安装[tpext.core]');
        }

        $instance = $this->extensions[$id];

        $builder = Builder::getInstance('扩展管理', '安装-' . $instance->getTitle());

        if (request()->isPost()) {
            $res = $instance->install();

            if ($res) {

                $instance->copyAssets();

                $config = $instance->defaultConfig();

                Extension::create([
                    'key' => $id,
                    'name' => $instance->getName(),
                    'title' => $instance->getTitle(),
                    'description' => $instance->getDescription(),
                    'tags' => $instance->getTags(),
                    'install' => 1,
                    'enable' => 1,
                    'config' => json_encode($config),
                ]);

                ExtLoader::getInstalled(true);
                $this->clearCache();

                return $builder->layer()->closeRefresh(1, '安装成功');
            } else {
                $errors = $instance->getErrors();

                $text = [];
                foreach ($errors as $err) {
                    $text[] = $err->getMessage();
                }

                $builder->content()->display('<h5>执行出错：</h5>:' . implode('<br>', $text));

                return $builder->render();
            }
        } else {

            $form = $builder->form();

            $modules = $instance->getModules();
            $bindModules = [];

            foreach ($modules as $module => $controlers) {
                foreach ($controlers as $controler) {
                    $bindModules[] = '/' . $module . '/' . $controler . '/*';
                }
            }

            $form->raw('name', '标识')->value($instance->getName());
            $form->raw('title', '标题')->value($instance->getTitle());
            $form->raw('tags', '类型')->value($instance->getTags());
            $form->raw('modules', '提供模块')->value(!empty($bindModules) ? implode('<br>', $bindModules) : '无');
            $form->raw('desc', '介绍')->value($instance->getDescription());
            $form->raw('version', '版本号')->value($instance->getVersion());
            $form->html('', '', 6)->showLabel(false);
            $form->btnSubmit('安&nbsp;&nbsp;装');
            $form->btnLayerClose();

            $form->ajax(false);

            return $builder->render()->getContent();
        }

    }

    public function uninstall($id = '')
    {
        if (empty($id)) {
            return Builder::getInstance('扩展管理')->layer()->close(0, '参数有误！');
        }

        $id = str_replace('.', '\\', $id);

        if (!isset($this->extensions[$id])) {
            return Builder::getInstance('扩展管理')->layer()->close(0, '扩展不存在！');
        }

        $instance = $this->extensions[$id];

        $builder = Builder::getInstance('扩展管理', '安装-' . $instance->getTitle());

        if (request()->isPost()) {
            $res = $instance->uninstall();

            if ($res) {

                Extension::where(['key' => $id])->delete();

                ExtLoader::getInstalled(true);

                $this->clearCache();

                return $builder->layer()->closeRefresh(1, '卸载成功');
            } else {
                $errors = $instance->getErrors();

                $text = [];
                foreach ($errors as $err) {
                    $text[] = $err->getMessage();
                }

                $builder->content()->display('<h5>执行出错：</h5>:' . implode('<br>', $text));

                return $builder->render();
            }
        } else {

            $form = $builder->form();

            $modules = $instance->getModules();
            $bindModules = [];

            foreach ($modules as $module => $controlers) {
                foreach ($controlers as $controler) {
                    $bindModules[] = '/' . $module . '/' . $controler . '/*';
                }
            }

            $form->raw('name', '标识')->value($instance->getName());
            $form->raw('title', '标题')->value($instance->getTitle());
            $form->raw('tags', '类型')->value($instance->getTags());
            $form->raw('modules', '提供模块')->value(!empty($bindModules) ? implode('<br>', $bindModules) : '无');
            $form->raw('desc', '介绍')->value($instance->getDescription());
            $form->raw('version', '版本号')->value($instance->getVersion());
            $form->html('', '', 6)->showLabel(false);
            $form->btnSubmit('卸&nbsp;&nbsp;载', 1, 'btn-danger');
            $form->btnLayerClose();
            $form->ajax(false);

            return $builder->render();
        }
    }

    private function clearCache()
    {
        cache('tpext_modules', null);
        cache('tpext_bind_modules', null);
    }

    public function config($id = '')
    {
        if (empty($id)) {
            return Builder::getInstance('扩展管理')->layer()->close(0, '参数有误！');
        }

        $id = str_replace('.', '\\', $id);

        if (!isset($this->extensions[$id])) {
            return Builder::getInstance('扩展管理')->layer()->close(0, '扩展不存在！');
        }

        $instance = $this->extensions[$id];

        $config = $instance->defaultConfig();

        if (empty($config)) {
            return Builder::getInstance('扩展管理')->layer()->close(0, '配置项不存在！');
        }

        $builder = Builder::getInstance('扩展管理', '配置-' . $instance->getTitle());

        if (request()->isPost()) {
            $post = request()->post();

            $res = $this->seveConfig($config, $post, $id);

            if ($res) {
                return $builder->layer()->closeRefresh(1, '修改成功');
            } else {
                return $builder->layer()->closeRefresh(0, '修改失败，或无变化');
            }

        } else {

            $form = $builder->form();

            $ext = Extension::where(['key' => $id])->find();
            $saved = json_decode($ext['config'], 1);

            $this->buildConfig($form, $config, $saved);

            return $builder->render();
        }
    }

    private function seveConfig($config, $post, $id)
    {
        $data = [];

        foreach ($config as $key => $val) {
            if ($key == '__config__') {
                continue;
            }

            $data[$key] = $post[$key];
            if (is_array($val)) {
                $data[$key] = json_encode($post[$key]);
            }
        }

        return Extension::where(['key' => $id])->update(['config' => json_encode($data)]);
    }

    public function setting()
    {
        $builder = Builder::getInstance('配置管理', '配置修改');

        $installed = ExtLoader::getInstalled();

        if (request()->isPost()) {
            $post = request()->post();

            $count = 0;

            foreach ($this->extensions as $key => $instance) {
                $is_install = 0;
                $has_config = !empty($instance->defaultConfig());

                foreach ($installed as $ins) {
                    if ($ins['key'] == $key) {
                        $is_install = $ins['install'];
                        break;
                    }
                }

                if (!$is_install || !$has_config) {
                    continue;
                }

                $id = $instance->getId();
                if (isset($post[$id])) {
                    $config = $instance->defaultConfig();
                    $data = $post[$id];
                    $res = $this->seveConfig($config, $data, $key);
                    if ($res) {
                        $count += 1;
                    }
                }
            }
            
            if ($count) {
                $this->success('修改成功');
            } else {
                $this->error('修改失败，或无变化');
            }
        } else {
            $form = $builder->form();

            foreach ($this->extensions as $key => $instance) {
                $is_install = 0;
                $has_config = !empty($instance->defaultConfig());

                foreach ($installed as $ins) {
                    if ($ins['key'] == $key) {
                        $is_install = $ins['install'];
                        break;
                    }
                }

                if (!$is_install || !$has_config) {
                    continue;
                }

                $config = $instance->defaultConfig();

                if (empty($config)) {
                    continue;
                }

                $ext = Extension::where(['key' => $key])->find();
                $saved = json_decode($ext['config'], 1);

                $form->tab($instance->getTitle());

                $this->buildConfig($form, $config, $saved, $instance->getId());
            }

            return $builder->render();
        }
    }

    private function buildConfig(&$form, $config, $saved = [], $extKey = '')
    {
        $savedKeys = array_keys($saved);

        $fiedTypes = [];

        if (isset($config['__config__'])) {
            $fiedTypes = $config['__config__'];
        }

        foreach ($config as $key => $val) {
            $fieldName = $key;

            if ($extKey) {
                $fieldName = $extKey . '[' . $key . ']';
            }

            if ($key == '__config__') {
                continue;
            }

            if (is_array($val)) {
                $val = json_encode($val);
            }

            if (isset($fiedTypes[$key])) {
                $type = $fiedTypes[$key];

                $fieldType = $type['type'];
                $label = isset($type['label']) ? $type['label'] : '';
                $help = isset($type['help']) ? $type['help'] : '';
                $required = isset($type['required']) ? $type['required'] : false;
                $colSize = isset($type['colSize']) && is_numeric($type['colSize']) ? $type['colSize'] : 12;
                $size = isset($type['size']) && is_array($type['size']) && count($type['size']) == 2 ? $type['size'] : [2, 8];

                if (preg_match('/(radio|select|checkbox|multipleSelect)/i', $type['type'])) {

                    $field = $form->$fieldType($fieldName, $label, $colSize)->options($type['options'])->required($required)->default($val)->help($help)->size($size[0], $size[1]);
                } else {
                    $field = $form->$fieldType($fieldName, $label, $colSize)->required($required)->default($val)->help($help)->size($size[0], $size[1]);
                }
            } else {

                $field = $form->text($key)->default($val)->help($help);
            }

            if (in_array($key, $savedKeys)) {
                $field->value($saved[$key]);
            }
        }
    }

    public function copyAssets()
    {
        $ids = input('ids', '');
        $ids = str_replace('.', '\\', $ids);

        $ids = array_filter(explode(',', $ids), 'strlen');

        if (empty($ids)) {
            $this->error('参数有误');
        }

        $instance = $this->extensions[$ids[0]];

        $instance->copyAssets(true);

        $this->success('刷新成功');
    }

    public function enable()
    {
        $ids = input('ids', '');
        $ids = str_replace('.', '\\', $ids);

        $ids = array_filter(explode(',', $ids), 'strlen');

        if (empty($ids)) {
            $this->error('参数有误');
        }

        $installed = ExtLoader::getInstalled();

        if (empty($installed)) {
            $this->error('已安装扩展为空！请确保数据库连接正常，然后安装[tpext.core]');
        }

        foreach ($ids as $id) {
            Extension::where(['key' => $id])->update(['enable' => 1]);
        }

        ExtLoader::getInstalled(true);

        $this->success('启用成功');
    }

    public function disable()
    {
        $ids = input('ids', '');
        $ids = str_replace('.', '\\', $ids);

        $ids = array_filter(explode(',', $ids), 'strlen');

        if (empty($ids)) {
            $this->error('参数有误');
        }

        $installed = ExtLoader::getInstalled();

        if (empty($installed)) {
            $this->error('已安装扩展为空！请确保数据库连接正常，然后安装[tpext.core]');
        }

        foreach ($ids as $id) {
            if ($id == TpextModule::class) {
                continue;
            }
            Extension::where(['key' => $id])->update(['enable' => 0]);
        }

        ExtLoader::getInstalled(true);

        $this->success('禁用成功');
    }
}
