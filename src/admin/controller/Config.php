<?php
namespace tpext\admin\controller;

use think\Controller;
use tpext\admin\model\WebConfig;
use tpext\builder\common\Builder;
use tpext\builder\Common\Form;
use tpext\builder\common\Table;
use tpext\common\ExtLoader;

class Config extends Controller
{
    protected $extensions = [];

    protected $dataModel;

    protected function initialize()
    {
        $this->extensions = ExtLoader::getModules();
        ksort($this->extensions);

        $this->dataModel = new WebConfig;
    }

    public function index($confkey = '')
    {
        $builder = Builder::getInstance('配置管理', '配置修改');

        $installed = ExtLoader::getInstalled();

        if (request()->isPost()) {
            $data = request()->post();

            $config_key = $data['config_key'];

            unset($data['__config__']);
            unset($data['config_key']);

            $res = $this->seveConfig($data, $config_key);

            if ($res) {
                $this->success('修改成功', url('index', ['confkey' => $config_key]));
            } else {
                $this->error('修改失败，或无变化');
            }
        } else {
            $tab = $builder->tab();
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

                $config_key = $instance->getId();

                $configData = $this->dataModel->where(['key' => $config_key])->find();

                $saved = $configData ? json_decode($configData['config'], 1) : [];
                $form = $tab->add($instance->getTitle(), $confkey == $config_key)->form();
                $form->formId('the-from' . $instance->getId());
                $form->hidden('config_key')->value($config_key);
                $this->buildConfig($form, $config, $saved, $instance->getId());
            }

            $table = $tab->add('更多设置', $confkey == 'config_list')->table();

            $this->buildList($table);

            return $builder->render();
        }
    }

    private function buildList(Table &$table)
    {
        $table->show('id', 'ID');
        $table->show('key', '键');
        $table->text('title', '标题')->autoPost()->getWapper()->addStyle('max-width:80px');
        $table->show('config', '内容')->getWapper()->addStyle('width:200px');

        $table->show('create_time', '添加时间')->getWapper()->addStyle('width:180px');
        $table->show('update_time', '修改时间')->getWapper()->addStyle('width:180px');

        $data = $this->dataModel->order('key')->select();

        $table->data($data);

        $table->paginator(998, 999);
    }

    private function buildConfig(Form &$form, $config, $saved = [])
    {
        $savedKeys = array_keys($saved);

        $fiedTypes = [];

        if (isset($config['__config__'])) {
            $fiedTypes = $config['__config__'];
        }

        foreach ($config as $key => $val) {

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

                    $field = $form->$fieldType($key, $label, $colSize)->options($type['options'])->required($required)->default($val)->help($help)->size($size[0], $size[1]);
                } else {
                    $field = $form->$fieldType($key, $label, $colSize)->required($required)->default($val)->help($help)->size($size[0], $size[1]);
                }
            } else {

                $field = $form->text($key)->default($val)->help($help);
            }

            if (in_array($key, $savedKeys)) {
                $field->value($saved[$key]);
            }
        }
    }

    public function extConfig($key = '')
    {
        if (empty($key)) {
            return Builder::getInstance('扩展管理')->layer()->close(0, '参数有误！');
        }

        $id = str_replace('.', '\\', $key);

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
            $data = request()->post();

            $res = $this->seveConfig($data, $instance->getId());

            if ($res) {
                return $builder->layer()->closeRefresh(1, '修改成功');
            } else {
                return $builder->layer()->closeRefresh(0, '修改失败，或无变化');
            }

        } else {

            $form = $builder->form();

            $configData = $this->dataModel->where(['key' => $instance->getId()])->find();

            $saved = $configData ? json_decode($configData['config'], 1) : [];

            $this->buildConfig($form, $config, $saved);

            return $builder->render();
        }
    }

    private function seveConfig($data, $key)
    {
        if ($this->dataModel->where(['key' => $key])->find()) {
            return $this->dataModel->where(['key' => $key])->update(['config' => json_encode($data)]);
        }

        return $this->dataModel->create(['key' => $key, 'config' => json_encode($data)]);
    }

}
