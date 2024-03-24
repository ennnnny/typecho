<?php
/**
 * 验证插件Plus
 *
 * @package CaptchaPlus
 * @author ennnnny
 * @version 1.0.0
 * @link https://www.yuzhiwei.com.cn
 *
 */
if (!defined('__TYPECHO_ROOT_DIR__')) {
    exit;
}

use Typecho\Plugin\PluginInterface;
use Typecho\Widget;
use Typecho\Widget\Exception;
use Typecho\Widget\Helper\Form;
use Typecho\Widget\Helper\Form\Element\Text;
use Typecho\Widget\Helper\Form\Element\Radio;
use Typecho\Widget\Helper\Form\Element\Textarea;
use Typecho\Cookie;
use Widget\Options;

class CaptchaPlus_Plugin implements PluginInterface
{

    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     */
    public static function activate()
    {
        Helper::addAction('captchaplus', 'CaptchaPlus_Action');
        // 注册后台底部结束钩子
        \Typecho\Plugin::factory('admin/footer.php')->end = [__CLASS__, 'renderLoginCaptcha'];
        // 注册用户登录成功钩子
        \Typecho\Plugin::factory('Widget_User')->loginSucceed = [__CLASS__, 'verifyLoginCaptcha'];

        // 评论钩子
        \Typecho\Plugin::factory('Widget_Feedback')->comment = [__CLASS__, 'verifyCommentCaptcha'];
        \Typecho\Plugin::factory('Widget_Feedback')->trackback = [__CLASS__, 'verifyCommentCaptcha'];
        \Typecho\Plugin::factory('Widget_XmlRpc')->pingback = [__CLASS__, 'verifyCommentCaptcha'];
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     */
    public static function deactivate()
    {
    }

    /**
     * 获取插件配置面板
     *
     * @param Form $form
     */
    public static function config(Form $form)
    {
        $captcha_choose = new Radio('captcha_choose', [
            "geetest3" => "geetest(极验)3.0",
            "geetest4" => "geetest(极验)4.0",
            "hcaptcha" => "hCaptcha",
            "turnstile" => "Turnstile"
        ], "geetest3", _t('验证工具'), _t('选择使用 Geetest、hCpatcha 或者 Cloudflare Turnstile 验证'));
        $form->addInput($captcha_choose);

        $site_key = new Text('site_key', NULL, '', _t('Site Key(ID)'), _t('需要注册 <a href="https://www.geetest.com/" target="_blank">Geetest</a> 或者 <a href="https://www.hcaptcha.com/" target="_blank">hCaptcha</a> 或者 <a href="https://dash.cloudflare.com/sign-up" target="_blank">Cloudflare</a> 账号以获取 <b>site key</b> 和 <b>secret key</b>'));
        $form->addInput($site_key);

        $secret_key = new Text('secret_key', NULL, '', _t('Secret Key(KEY)'), _t(''));
        $form->addInput($secret_key);

        $widget_theme = new Radio('widget_theme', ["light" => "浅色", "dark" => "深色"], "light", _t('主题'), _t('(非Geetest)设置验证工具主题颜色，默认为浅色'));
        $form->addInput($widget_theme);

        $widget_size = new Radio('widget_size', ["normal" => "常规", "compact" => "紧凑"], "normal", _t('样式'), _t('(非Geetest)设置验证工具布局样式，默认为常规'));
        $form->addInput($widget_size);

        $isOpenPage = new Form\Element\Checkbox('isOpenPage', [
            "typechoLogin" => _t('登录界面'),
            "typechoComment" => _t('评论页面')
        ], [], _t('开启验证码的页面，勾选则开启'), _t('开启评论验证码后需在主题的评论的模板 comments.php 中“提交按钮上面”添加如下字段：<textarea><?php CaptchaPlus_Plugin::commentCaptchaRender(); ?></textarea>如果没有jQuery，记得加上：<textarea><script src="https://cdn.jsdelivr.net/npm/jquery@2.2.4/dist/jquery.min.js"></script></textarea>'));
        $form->addInput($isOpenPage);

        $commentButtonId = new Text('commentButtonId', NULL, '', _t('评论框ID'), _t('评论框的ID，用于绑定评论框事件，查看模板 comments.php 文件，如果没有请手动加上个唯一ID'));
        $form->addInput($commentButtonId);

        $opt_noru = new Radio(
            'opt_noru',
            ["none" => "无动作", "waiting" => "标记为待审核", "spam" => "标记为垃圾", "abandon" => "评论失败"],
            "abandon",
            _t('俄文评论操作'),
            _t('如果评论中包含俄文，则强行按该操作执行')
        );
        $form->addInput($opt_noru);

        $opt_nocn = new Radio(
            'opt_nocn',
            ["none" => "无动作", "waiting" => "标记为待审核", "spam" => "标记为垃圾", "abandon" => "评论失败"],
            "waiting",
            _t('非中文评论操作'),
            _t('如果评论中不包含中文，则强行按该操作执行')
        );
        $form->addInput($opt_nocn);

        $opt_ban = new Radio(
            'opt_ban',
            ["none" => "无动作", "waiting" => "标记为待审核", "spam" => "标记为垃圾", "abandon" => "评论失败"],
            "abandon",
            _t('禁止词汇操作'),
            _t('如果评论中包含禁止词汇列表中的词汇，将执行该操作')
        );
        $form->addInput($opt_ban);

        $words_ban = new Textarea(
            'words_ban',
            NULL,
            "fuck\n傻逼\ncnm",
            _t('禁止词汇'),
            _t('多条词汇请用换行符隔开')
        );
        $form->addInput($words_ban);

        $opt_chk = new Radio(
            'opt_chk',
            ["none" => "无动作", "waiting" => "标记为待审核", "spam" => "标记为垃圾", "abandon" => "评论失败"],
            "waiting",
            _t('敏感词汇操作'),
            _t('如果评论中包含敏感词汇列表中的词汇，将执行该操作')
        );
        $form->addInput($opt_chk);

        $words_chk = new Textarea(
            'words_chk',
            NULL,
            "http://\nhttps://",
            _t('敏感词汇'),
            _t('多条词汇请用换行符隔开<br />注意：如果词汇同时出现于禁止词汇，则执行禁止词汇操作')
        );
        $form->addInput($words_chk);
    }

    /**
     * 个人用户的配置面板
     *
     * @param Form $form
     */
    public static function personalConfig(Form $form)
    {
    }

    public static function renderLoginCaptcha()
    {
        //判断插件是否激活
        $options = Widget::widget('Widget_Options');
        if (!isset($options->plugins['activated']['CaptchaPlus'])) {
            return;
        }
        // 判断是否登录页面
        $widgetOptions = Widget::widget('Widget_Options');
        $widgetRequest = $widgetOptions->request;
        $currentRequestUrl = $widgetRequest->getRequestUrl();
        if (!stripos($currentRequestUrl, 'login.php')) {
        } else {
            $pluginOptions = Options::alloc()->plugin('CaptchaPlus');
            $captcha_choose = $pluginOptions->captcha_choose;
            $site_key = $pluginOptions->site_key;
            $secret_key = $pluginOptions->secret_key;
            $widget_theme = $pluginOptions->widget_theme;
            $widget_size = $pluginOptions->widget_size;
            $isOpenPage = $pluginOptions->isOpenPage;

            $script = '';
            if (in_array('typechoLogin', $isOpenPage) && $site_key != "" && $secret_key != "") {
                switch ($captcha_choose) {
                    case 'hcaptcha':
                        $script .= '<script src="https://js.hcaptcha.com/1/api.js" async defer></script>';
                        $script .= <<<EOF
<script>
$('p.submit').find('button').before('<div class="h-captcha" data-sitekey="{$site_key}" data-theme="{$widget_theme}" data-size="{$widget_size}"></div>');
</script>
EOF;
                        break;
                    case 'turnstile':
                        $script .= '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
                        $script .= <<<EOF
<script>
$('p.submit').find('button').before('<div class="cf-turnstile" data-sitekey="{$site_key}" data-theme="{$widget_theme}" data-size="{$widget_size}"></div>');
</script>
EOF;
                        break;
                    case 'geetest3':
                        $script .= '<script src="https://static.geetest.com/static/js/gt.0.4.9.js"></script>';
                        $ajaxUri = '/index.php/action/captchaplus?do=ajaxResponseCaptchaData';
                        $script .= <<<EOF
<script>
$(document).ready(function () {
    var jqForm = $('form[name="login"]');
    var jqFormSubmit = jqForm.find(":submit");
    jqFormSubmit.before('<input type="hidden" id="geetest_challenge" name="geetest_challenge" value=""><input type="hidden" id="geetest_validate" name="geetest_validate" value=""><input type="hidden" id="geetest_seccode" name="geetest_seccode" value="">');
    jqForm.on('submit', function(e) {
      e.preventDefault();
      alert("等待验证码加载完毕");
    });
    var gtInitCallback = function (captchaObj) {
       captchaObj.onReady(function () {
           jqForm.off('submit');
           jqForm.on('submit', function(e) {
              e.preventDefault();
              captchaObj.verify();
            });
        }).onSuccess(function () {
            var result = captchaObj.getValidate();
            if (!result) {
                alert('请完成验证');
            } else {
                $('#geetest_challenge').val(result.geetest_challenge);
                $('#geetest_validate').val(result.geetest_validate);
                $('#geetest_seccode').val(result.geetest_seccode);
                jqForm.off('submit');
                jqForm.submit();
            }
            captchaObj.reset()
        }).onError(function(error){
            console.log(error)
          alert("验证码加载失败");
        });
    }
    $.ajax({
        url: "{$ajaxUri}&t=" + (new Date()).getTime(),
        type: "get",
        dataType: "json",
        success: function (data) {
            // console.log(data);
            initGeetest({
                gt: data.gt,
                challenge: data.challenge,
                new_captcha: data.new_captcha,
                product: "bind",
                offline: !data.success,
                width: '100%'
            }, gtInitCallback);
        }
    });
})
</script>
EOF;

                        break;
                    case 'geetest4':
                        $script .= '<script src="https://static.geetest.com/v4/gt4.js"></script>';
                        $script .= <<<EOF
<script>
$(document).ready(function () {
    var jqForm = $('form[name="login"]');
    var jqFormSubmit = jqForm.find(":submit");
    jqFormSubmit.before('<input type="hidden" id="lot_number" name="lot_number" value=""><input type="hidden" id="captcha_output" name="captcha_output" value=""><input type="hidden" id="pass_token" name="pass_token" value=""><input type="hidden" id="gen_time" name="gen_time" value="">');
    jqForm.on('submit', function(e) {
      e.preventDefault();
      alert("等待验证码加载完毕");
    });
    initGeetest4({
        captchaId: '{$site_key}',
        product: 'bind'
    }, function(captchaObj){
        captchaObj.onReady(function(){
           jqForm.off('submit');
           jqForm.on('submit', function(e) {
              e.preventDefault();
              captchaObj.showCaptcha();
            });
        }).onSuccess(function(){
            var result = captchaObj.getValidate();
            if (!result) {
                alert('请完成验证');
            } else {
                $('#lot_number').val(result.lot_number);
                $('#captcha_output').val(result.captcha_output);
                $('#pass_token').val(result.pass_token);
                $('#gen_time').val(result.gen_time);
                jqForm.off('submit');
                jqForm.submit();
            }
            captchaObj.reset()
        }).onError(function(error){
            console.log(error)
            alert("验证码加载失败");
        })
    });
});
</script>
EOF;
                        break;
                }
            }
            echo $script;
        }
    }

    public static function verifyLoginCaptcha()
    {
        //判断插件是否激活
        $options = Widget::widget('Widget_Options');
        if (!isset($options->plugins['activated']['CaptchaPlus'])) {
            return;
        }
        $pluginOptions = Options::alloc()->plugin('CaptchaPlus');
        $isOpenPage = $pluginOptions->isOpenPage;
        if (in_array("typechoLogin", $isOpenPage)) {
            if (!self::_verifyCaptcha()) {
                Widget::widget('Widget_Notice')->set(_t('验证码错误'), 'error');
                Widget::widget('Widget_User')->logout();
                Widget::widget('Widget_Options')->response->goBack();
            }
        }
    }

    public static function commentCaptchaRender()
    {
        //判断插件是否激活
        $options = Widget::widget('Widget_Options');
        if (!isset($options->plugins['activated']['CaptchaPlus'])) {
            return;
        }

        $pluginOptions = Options::alloc()->plugin('CaptchaPlus');
        $captcha_choose = $pluginOptions->captcha_choose;
        $site_key = $pluginOptions->site_key;
        $secret_key = $pluginOptions->secret_key;
        $widget_theme = $pluginOptions->widget_theme;
        $widget_size = $pluginOptions->widget_size;
        $isOpenPage = $pluginOptions->isOpenPage;
        $commentButtonId = $pluginOptions->commentButtonId;

        $script = '';
        if (in_array('typechoComment', $isOpenPage) && $site_key != "" && $secret_key != "" && $commentButtonId != "") {
            switch ($captcha_choose) {
                case 'hcaptcha':
                    $script .= '<script src="https://js.hcaptcha.com/1/api.js" async defer></script>';
                    $script .= <<<EOF
<script>
$('#{$commentButtonId}').before('<div class="h-captcha" data-sitekey="{$site_key}" data-theme="{$widget_theme}" data-size="{$widget_size}"></div>');
</script>
EOF;
                    break;
                case 'turnstile':
                    $script .= '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
                    $script .= <<<EOF
<script>
$('#{$commentButtonId}').before('<div class="cf-turnstile" data-sitekey="{$site_key}" data-theme="{$widget_theme}" data-size="{$widget_size}"></div>');
</script>
EOF;
                    break;
                case 'geetest3':
                    $script .= '<script src="https://static.geetest.com/static/js/gt.0.4.9.js"></script>';
                    $ajaxUri = '/index.php/action/captchaplus?do=ajaxResponseCaptchaData';
                    $script .= <<<EOF
<script>
$(document).ready(function () {
    var jqFormSubmit = $('#{$commentButtonId}');
    var originalOnClick = jqFormSubmit.attr('onclick');
    if (originalOnClick !== undefined) {
        jqFormSubmit.removeAttr('onclick');
    }
    jqFormSubmit.before('<input type="hidden" id="geetest_challenge" name="geetest_challenge" value=""><input type="hidden" id="geetest_validate" name="geetest_validate" value=""><input type="hidden" id="geetest_seccode" name="geetest_seccode" value="">');
    jqFormSubmit.on('click', function(e) {
      e.preventDefault();
      alert("等待验证码加载完毕");
    });
    var gtInitCallback = function (captchaObj) {
       captchaObj.onReady(function () {
           jqFormSubmit.off('click');
           jqFormSubmit.on('click', function(e) {
              e.preventDefault();
              captchaObj.verify();
            });
        }).onSuccess(function () {
            var result = captchaObj.getValidate();
            if (!result) {
                alert('请完成验证');
            } else {
                $('#geetest_challenge').val(result.geetest_challenge);
                $('#geetest_validate').val(result.geetest_validate);
                $('#geetest_seccode').val(result.geetest_seccode);
                jqFormSubmit.off('click');
                if (originalOnClick !== undefined) {
                    jqFormSubmit.attr('onclick', originalOnClick);
                }
                jqFormSubmit.click();
                if (originalOnClick !== undefined) {
                    jqFormSubmit.removeAttr('onclick');
                }
                jqFormSubmit.on('click', function(e) {
                  e.preventDefault();
                  captchaObj.verify();
                });
            }
        }).onError(function(error){
            console.log(error)
          alert("验证码加载失败");
        });
    }
    $.ajax({
        url: "{$ajaxUri}&t=" + (new Date()).getTime(),
        type: "get",
        dataType: "json",
        success: function (data) {
            // console.log(data);
            initGeetest({
                gt: data.gt,
                challenge: data.challenge,
                new_captcha: data.new_captcha,
                product: "bind",
                offline: !data.success,
                width: '100%'
            }, gtInitCallback);
        }
    });
})
</script>
EOF;
                    break;
                case 'geetest4':
                    $script .= '<script src="https://static.geetest.com/v4/gt4.js"></script>';
                    $script .= <<<EOF
<script>
$(document).ready(function () {
    var jqFormSubmit = $('#{$commentButtonId}');
    var originalOnClick = jqFormSubmit.attr('onclick');
    if (originalOnClick !== undefined) {
        jqFormSubmit.removeAttr('onclick');
    }
    jqFormSubmit.before('<input type="hidden" id="lot_number" name="lot_number" value=""><input type="hidden" id="captcha_output" name="captcha_output" value=""><input type="hidden" id="pass_token" name="pass_token" value=""><input type="hidden" id="gen_time" name="gen_time" value="">');
    jqFormSubmit.on('click', function(e) {
      e.preventDefault();
      alert("等待验证码加载完毕");
    });
    initGeetest4({
        captchaId: '{$site_key}',
        product: 'bind'
    }, function(captchaObj){
        captchaObj.onReady(function(){
           jqFormSubmit.off('click');
           jqFormSubmit.on('click', function(e) {
              e.preventDefault();
              captchaObj.showCaptcha();
            });
        }).onSuccess(function(){
            var result = captchaObj.getValidate();
            if (!result) {
                alert('请完成验证');
            } else {
                $('#lot_number').val(result.lot_number);
                $('#captcha_output').val(result.captcha_output);
                $('#pass_token').val(result.pass_token);
                $('#gen_time').val(result.gen_time);
                jqFormSubmit.off('click');
                if (originalOnClick !== undefined) {
                    jqFormSubmit.attr('onclick', originalOnClick);
                }
                jqFormSubmit.click();
                if (originalOnClick !== undefined) {
                    jqFormSubmit.removeAttr('onclick');
                }
                jqFormSubmit.on('click', function(e) {
                  e.preventDefault();
                  captchaObj.showCaptcha();
                });
            }
        }).onError(function(error){
            console.log(error)
            alert("验证码加载失败");
        })
    });
});
</script>
EOF;
                    break;
            }
        }
        echo $script;
    }

    public static function verifyCommentCaptcha($comment)
    {
        //判断插件是否激活
        $options = Widget::widget('Widget_Options');
        if (!isset($options->plugins['activated']['CaptchaPlus'])) {
            return $comment;
        }
        $pluginOptions = Options::alloc()->plugin('CaptchaPlus');
        $isOpenPage = $pluginOptions->isOpenPage;
        if (in_array("typechoComment", $isOpenPage)) {
            $user = Widget::widget('Widget_User');
            if ($user->hasLogin() && $user->pass('administrator', true)) {
                return $comment;
            } else {
                if (!self::_verifyCaptcha()) {
                    throw new Exception(_t('验证失败，请重试！'));
                } else {
                    //评论过滤
                    $opt = "none";
                    $error = "";
                    // 俄文评论处理
                    if ($opt == "none" && $pluginOptions->opt_noru != "none") {
                        if (preg_match("/([\x{0400}-\x{04FF}]|[\x{0500}-\x{052F}]|[\x{2DE0}-\x{2DFF}]|[\x{A640}-\x{A69F}]|[\x{1C80}-\x{1C8F}])/u", $comment['text']) > 0) {
                            $error = "Error.";
                            $opt = $pluginOptions->opt_noru;
                        }
                    }
                    // 非中文评论处理
                    if ($opt == "none" && $pluginOptions->opt_nocn != "none") {
                        if (preg_match("/[\x{4e00}-\x{9fa5}]/u", $comment['text']) == 0) {
                            $error = "At least one Chinese character is required.";
                            $opt = $pluginOptions->opt_nocn;
                        }
                    }
                    // 禁止词汇处理
                    if ($opt == "none" && $pluginOptions->opt_ban != "none") {
                        if (CaptchaPlus_Plugin::contains($comment['text'], $pluginOptions->words_ban)) {
                            $error = "More friendly, plz :)";
                            $opt = $pluginOptions->opt_ban;
                        }
                    }
                    // 敏感词汇处理
                    if ($opt == "none" && $pluginOptions->opt_chk != "none") {
                        if (CaptchaPlus_Plugin::contains($comment['text'], $pluginOptions->words_chk)) {
                            $error = "Error.";
                            $opt = $pluginOptions->opt_chk;
                        }
                    }
                    // 执行操作
                    if ($opt == "abandon") {
                        Cookie::set('__typecho_remember_text', $comment['text']);
                        throw new Exception($error);
                    } elseif ($opt == "spam") {
                        $comment['status'] = 'spam';
                    } elseif ($opt == "waiting") {
                        $comment['status'] = 'waiting';
                    }
                    Cookie::delete('__typecho_remember_text');
                    return $comment;
                }
            }
        }
    }

    private static function _verifyCaptcha()
    {
        require_once __DIR__.'/lib/GeetestLib.php';
        $pluginOptions = Options::alloc()->plugin('CaptchaPlus');
        $captcha_choose = $pluginOptions->captcha_choose;
        $site_key = $pluginOptions->site_key;
        $secret_key = $pluginOptions->secret_key;
        switch ($captcha_choose) {
            case 'hcaptcha':
                if (!empty($_POST['h-captcha-response'])) {
                    $post_token = $_POST['h-captcha-response'];
                    $url_path = "https://api.hcaptcha.com/siteverify";
                    $class = new GeetestLib($site_key, $secret_key);
                    $resBody = $class->httpPost($url_path, [
                        'secret' => $secret_key,
                        'response' => $post_token,
                    ]);
                    $response_data = json_decode($resBody);
                    if ($response_data->success) {
                        return true;
                    }
                }
                break;
            case 'turnstile':
                if (!empty($_POST['cf-turnstile-response'])) {
                    $post_token = $_POST['cf-turnstile-response'];
                    $url_path = "https://challenges.cloudflare.com/turnstile/v0/siteverify";
                    $class = new GeetestLib($site_key, $secret_key);
                    $resBody = $class->httpPost($url_path, [
                        'secret' => $secret_key,
                        'response' => $post_token,
                    ]);
                    $response_data = json_decode($resBody);
                    if ($response_data->success) {
                        return true;
                    }
                }
                break;
            case 'geetest3':
                if (!empty($_POST['geetest_challenge']) && !empty($_POST['geetest_validate']) && !empty($_POST['geetest_seccode'])) {
                    $challenge = $_POST['geetest_challenge'];
                    $validate = $_POST['geetest_validate'];
                    $seccode = $_POST['geetest_seccode'];
                    $gtLib = new GeetestLib($site_key, $secret_key);
                    $result = $gtLib->successValidate($challenge, $validate, $seccode, null);
                    if ($result->getStatus() === 1) {
                        return true;
                    }
                }
                break;
            case 'geetest4':
                if (!empty($_POST['lot_number']) && !empty($_POST['captcha_output']) && !empty($_POST['pass_token']) && !empty($_POST['gen_time'])) {
                    $lot_number = $_POST['lot_number'];
                    $captcha_output = $_POST['captcha_output'];
                    $pass_token = $_POST['pass_token'];
                    $gen_time = $_POST['gen_time'];
                    $gtLib = new GeetestLib($site_key, $secret_key);
                    $sign_token = $gtLib->hmac_sha256_encode($lot_number, $secret_key);
                    $url_path = "http://gcaptcha4.geetest.com/validate?captcha_id=".$site_key;
                    $resBody = $gtLib->httpPost($url_path, [
                        "lot_number" => $lot_number,
                        "captcha_output" => $captcha_output,
                        "pass_token" => $pass_token,
                        "gen_time" => $gen_time,
                        "sign_token" => $sign_token
                    ]);
                    $response_data = json_decode($resBody, true);
                    if (isset($response_data['result']) && $response_data['result'] === 'success') {
                        return true;
                    }
                }
                break;
        }
        return false;
    }

    public static function contains($haystack, $needles, $ignoreCase = true)
    {
        if ($ignoreCase) {
            $haystack = mb_strtolower($haystack);
        }
        if (is_string($needles)) {
            $needles = explode("\n", $needles);
        }
        if (! is_iterable($needles)) {
            $needles = (array) $needles;
        }

        foreach ($needles as $needle) {
            if ($ignoreCase) {
                $needle = mb_strtolower($needle);
            }

            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }
}
