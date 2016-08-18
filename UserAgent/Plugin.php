<?php
/**
 * UserAgent for Typecho
 *
 * @package UserAgent
 * @author kuye
 * @version 0.1.0
 * @update: 2015.11.21
 * @link http://yuzhiwei.com.cn/
 */
class UserAgent_Plugin implements Typecho_Plugin_Interface
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
        /** user agent icons **/
        $icons = new Typecho_Widget_Helper_Form_Element_Radio( 'icons',  array(
            '16'        =>  '16px 大小',
            '24'        =>  '24px 大小'),
            '16', _t('选择图标尺寸大小'), _t('') );
        $form->addInput($icons->multiMode());

        /** user agent show **/
        $show = new Typecho_Widget_Helper_Form_Element_Radio( 'show',  array(
            '1'     =>  '只显示图标',
            '2'     =>  '只显示文字',
            '3'     =>  '显示图片和文字'),
            '1', _t('选择显示的内容'), _t('') );
        $form->addInput($show->multiMode());
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
     * @return void
     */
    public static function render($agent)
    {
        $options = Typecho_Widget::widget('Widget_Options');
        $url_plugin = $options->pluginUrl . '/UserAgent/';  //插件地址  ->  http://domain.com/usr/plugins/UserAgent/
        global $url_img, $icons;
        $url_img = $url_plugin."img/";

        $icons = Typecho_Widget::widget('Widget_Options')->plugin('UserAgent')->icons;
        $show = Typecho_Widget::widget('Widget_Options')->plugin('UserAgent')->show;

        require_once 'useragent-os.php';
        $os = detect_os($agent);
        $os_img = self::img($os['code'],"/os/",$os['title']);
        $os_title = $os['title'];

        require_once 'useragent-webbrowser.php';
        $wb = detect_webbrowser($agent);
        $wb_img = self::img($wb['code'],"/net/",$wb['title']);
        $wb_title = $wb['title'];

        switch($show){
            case 1:
                $ua = "&nbsp;&nbsp;".$os_img."&nbsp;".$wb_img;
                break;
            case 2:
                $ua = "&nbsp;&nbsp;(".$os_title."&nbsp;/&nbsp;".$wb_title.")";
                break;
            case 3:
                $ua = "&nbsp;&nbsp;".$os_img."(".$os_title.")&nbsp;/&nbsp;".$wb_img."(".$wb_title.")";
                break;
            default :
                $ua = "&nbsp;&nbsp;".$os_img.$wb_img;
                break;
        }

        echo $ua;
    }

    /**
     * 图标
     *
     * @access public
     * @return void
     */
    public static function img($code, $type, $title)
    {
        global $icons, $url_img;
        // We need to default icons to size 16 or 24, we'll just use 16.
        if ($icons == "") {
            $icons = 16;
        }
        $img = "<img src='" . $url_img . $icons . $type . $code . ".png' title='" . $title . "' alt='" . $title . "' height='" . $icons . "' width='" . $icons . "' />";

        return $img;
    }
}