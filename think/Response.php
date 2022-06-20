<?php

namespace think;

use support\Response as baseResponse;

class Response extends baseResponse
{
    /**
     * 处理数据
     * @access protected
     * @param  mixed $data 要处理的数据
     * @return mixed
     */
    protected function output($data)
    {
        return $data;
    }

    /**
     * 获取输出数据
     * @access public
     * @return string
     */
    public function getContent(): string
    {
        if (null == $this->_body) {
            $content = $this->output($this->data);

            if (
                null !== $content && !is_string($content) && !is_numeric($content) && !is_callable([
                    $content,
                    '__toString',
                ])
            ) {
                throw new \InvalidArgumentException(sprintf('variable type error： %s', gettype($content)));
            }

            $this->_body = (string) $content;
        }

        return $this->_body;
    }
}
