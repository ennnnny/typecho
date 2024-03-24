<?php

use Widget\Options;
/**
 * 极验验证插件执行
 */
class CaptchaPlus_Action extends Typecho_Widget implements Widget_Interface_Do
{
    public function execute()
    {
    }

    public function action()
    {
        $this->on($this->request->is('do=ajaxResponseCaptchaData'))->ajaxResponseCaptchaData();
    }

    public function ajaxResponseCaptchaData()
    {
        if (!$this->request->isAjax()) {
            $this->response->redirect('/');
        }
//        \Typecho\Plugin::factory('CaptchaPlus')->responseCaptchaData();
        $pluginOptions = Options::alloc()->plugin('CaptchaPlus');
        $captcha_choose = $pluginOptions->captcha_choose;
        $site_key = $pluginOptions->site_key;
        $secret_key = $pluginOptions->secret_key;

        if ($captcha_choose != 'geetest3') {
            echo '[]';
        } else {
            require_once __DIR__.'/lib/GeetestLib.php';
            $gtLib = new GeetestLib($site_key, $secret_key);
            $digestmod = "md5";
            $params = [
                "digestmod" => $digestmod,
                "user_id" => rand(1000, 9999),
            ];
            $result = $gtLib->register($digestmod, $params);
            echo $result->getData();
        }
    }
}
