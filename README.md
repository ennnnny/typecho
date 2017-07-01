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
