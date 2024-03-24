<?php
/**
 * sdk lib包的返回结果信息。
 *
 * @author liuquan@geetest.com
 */
class GeetestLibResult
{
    /**
     * 成功失败的标识码，1表示成功，0表示失败
     */
    private $status = 0;

    /**
     * 返回数据，json格式
     */
    private $data = "";

    /**
     * 备注信息，如异常信息等
     */
    private $msg = "";

    public function getStatus()
    {
        return $this->status;
    }

    public function setStatus($status)
    {
        $this->status = $status;
    }

    public function getData()
    {
        return $this->data;
    }

    public function setData($data)
    {
        $this->data = $data;
    }

    public function getMsg()
    {
        return $this->msg;
    }

    public function setMsg($msg)
    {
        $this->msg = $msg;
    }

    public function setAll($status, $data, $msg)
    {
        $this->setStatus($status);
        $this->setData($data);
        $this->setMsg($msg);
    }

    public function __toString()
    {
        return sprintf("GeetestLibResult{status=%s, data=%s, msg=%s}", $this->status, $this->data, $this->msg);
    }

}

