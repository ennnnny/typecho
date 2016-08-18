<?php
/**
 * 评论者认证
 *
 * @package CommentApprove
 * @author kuye
 * @version 0.1.0
 * @update: 2016.08.48
 * @link http://www.yuzhiwei.com.cn/
 */
class CommentApprove_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     *
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function activate(){}

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     *
     * @static
     * @access public
     * @return void
     * @throws Typecho_Plugin_Exception
     */
    public static function deactivate(){}

    /**
     * 获取插件配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $type = new Typecho_Widget_Helper_Form_Element_Radio('type',array(
            '1'        =>  '使用自带样式',
            '2'        =>  '使用自填样式'),
            '1', _t('角色样式选择'), _t('如选择自填样式，则在角色名称那边带入样式') );
        $form->addInput($type);
        //角色1
        $name_1 = new Typecho_Widget_Helper_Form_Element_Text('name_1',
            NULL,'博主',_t('角色1'),_t('填入角色1的名称'));
        $form->addInput($name_1);
        $color_1 = new Typecho_Widget_Helper_Form_Element_Text('color_1',
            NULL,'#1ba1e2',_t('角色1样式颜色'),_t('填入颜色代码，只有使用自带样式时才生效'));
        $form->addInput($color_1);
        $email_1 = new Typecho_Widget_Helper_Form_Element_Textarea('email_1',
            NULL,'',_t('邮箱地址列表1'),_t('每个邮箱地址之间以英文半角逗号隔开'));
        $form->addInput($email_1);

        //角色2
        $name_2 = new Typecho_Widget_Helper_Form_Element_Text('name_2',
            NULL,'好友',_t('角色2'),_t('填入角色2的名称'));
        $form->addInput($name_2);
        $color_2 = new Typecho_Widget_Helper_Form_Element_Text('color_2',
            NULL,'#1ba1e2',_t('角色2样式颜色'),_t('填入颜色代码，只有使用自带样式时才生效'));
        $form->addInput($color_2);
        $email_2 = new Typecho_Widget_Helper_Form_Element_Textarea('email_2',
            NULL,'',_t('邮箱地址列表2'),_t('每个邮箱地址之间以英文半角逗号隔开'));
        $form->addInput($email_2);

        //角色3
        $name_3 = new Typecho_Widget_Helper_Form_Element_Text('name_3',
            NULL,'',_t('角色3'),_t('填入角色3的名称'));
        $form->addInput($name_3);
        $color_3 = new Typecho_Widget_Helper_Form_Element_Text('color_3',
            NULL,'#1ba1e2',_t('角色3样式颜色'),_t('填入颜色代码，只有使用自带样式时才生效'));
        $form->addInput($color_3);
        $email_3 = new Typecho_Widget_Helper_Form_Element_Textarea('email_3',
            NULL,'',_t('邮箱地址列表3'),_t('每个邮箱地址之间以英文半角逗号隔开'));
        $form->addInput($email_3);
    }

    /**
     * 个人用户的配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form){}

    /**
     * 插件实现方法
     *
     * @access public
     * @param str $email 评论者邮箱地址
     * @return void
     */
    public static function identify($email = NULL)
    {
        if (empty($email)){
            return;
        }
        $status = 0;
        $type = Typecho_Widget::widget('Widget_Options')->plugin('CommentApprove')->type;
        //判断角色1
        $email_1 = Typecho_Widget::widget('Widget_Options')->plugin('CommentApprove')->email_1;
        if (!empty($email_1)){
            $email_1 = explode(',',$email_1);
            if (in_array($email, $email_1)) {
                $status = 1;
            }
        }
        //判断角色2
        $email_2 = Typecho_Widget::widget('Widget_Options')->plugin('CommentApprove')->email_2;
        if (!empty($email_2)){
            $email_2 = explode(',',$email_2);
            if (in_array($email, $email_2)) {
                $status = 2;
            }
        }
        //判断角色3
        $email_3 = Typecho_Widget::widget('Widget_Options')->plugin('CommentApprove')->email_3;
        if (!empty($email_3)){
            $email_2 = explode(',',$email_3);
            if (in_array($email, $email_3)) {
                $status = 3;
            }
        }
        //角色名
        switch ($status){
            case 0: $name="";break;
            case 1: $name = Typecho_Widget::widget('Widget_Options')->plugin('CommentApprove')->name_1;break;
            case 2: $name = Typecho_Widget::widget('Widget_Options')->plugin('CommentApprove')->name_2;break;
            case 3: $name = Typecho_Widget::widget('Widget_Options')->plugin('CommentApprove')->name_3;break;
        }
        if (!empty($name)){
            if ($type == 1){
                switch ($status){
                    case 0: $color = "";break;
                    case 1: $color = Typecho_Widget::widget('Widget_Options')->plugin('CommentApprove')->color_1;break;
                    case 2: $color = Typecho_Widget::widget('Widget_Options')->plugin('CommentApprove')->color_2;break;
                    case 3: $color = Typecho_Widget::widget('Widget_Options')->plugin('CommentApprove')->color_3;break;
                }
                $str = '<span class="commentapprove" '.
                    'style="color: #FFF;padding: 2px 4px;font-size: 12px;border-radius: 3px;'.
                    'background-color: '.$color.';" >'.$name.'</span>';
                echo $str;
            }else{
                echo $name;
            }
        }else{
            return;
        }
    }
}