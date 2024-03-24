# typecho的插件
typecho plugins

#### UserAgent for typecho 
> 显示评论人使用的操作系统和浏览器 信息
> 
> 使用方法：
* 在你想显示的位置上加上这段代码：
* 请根据自己的模板来判断是使用$this或$comments！(如果不清楚，可以都试下)
```php
<?php UserAgent_Plugin::render($this->agent);?>
```

#### CommentApprove for typecho
> 根据评论人留的邮箱来进行认证身份
> 
> 使用方法：
* 在你想显示的位置上加上这段代码：
* 请根据自己的模板来判断是使用$this或$comments！(如果不清楚，可以都试下)
```php
<?php CommentApprove_Plugin::identify($this->mail);?>
```

#### CaptchaPlus for typecho
> 评论及后台登录新增验证码
> 支持geetest（3/4）、hcaptcha、turnstile
> 使用方法：
* 后台登录直接插件开启就行，无需改动文件
* 评论页面验证码需在主题的评论的模板 comments.php 中“提交按钮上面”添加如下字段：
```php
<?php CaptchaPlus_Plugin::commentCaptchaRender(); ?>
```
* 评论页面验证码还需给评论区域的提交按钮新加唯一ID，以便绑定评论框事件

- 插件修改自：https://github.com/scenery/typecho-plugins
