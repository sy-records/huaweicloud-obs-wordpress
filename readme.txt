=== OBS HuaWeiCloud ===
Contributors: shenyanzhi
Donate link: https://qq52o.me/sponsor.html
Tags: OBS, 华为云, 对象存储, HuaWei
Requires at least: 4.2
Tested up to: 5.5
Requires PHP: 5.6.0
Stable tag: 1.2.0
License: Apache 2.0
License URI: http://www.apache.org/licenses/LICENSE-2.0.html

使用华为云对象存储服务 OBS 作为附件存储空间。（This is a plugin that uses HuaWei Cloud Object Storage Service for attachments remote saving.）

== Description ==

使用华为云对象存储服务 OBS 作为附件存储空间。（This is a plugin that uses HuaWei Cloud Object Storage Service for attachments remote saving.）

* 依赖华为云OBS服务：https://www.huaweicloud.com/product/obs.html
* 使用说明：https://support.huaweicloud.com/productdesc-obs/zh-cn_topic_0045829060.html

## 插件特点

1. 可配置是否上传缩略图和是否保留本地备份
2. 本地删除可同步删除华为云对象存储OBS中的文件
3. 支持华为云对象存储OBS绑定的用户域名
4. 支持替换数据库中旧的资源链接地址
5. 支持华为云对象存储OBS完整地域使用
6. 支持同步历史附件到华为云对象存储OBS
7. 插件更多详细介绍和安装：[https://github.com/sy-records/huaweicloud-obs-wordpress](https://github.com/sy-records/huaweicloud-obs-wordpress)

## 其他插件

腾讯云COS：[GitHub](https://github.com/sy-records/wordpress-qcloud-cos)，[WordPress Plugins](https://wordpress.org/plugins/sync-qcloud-cos)
七牛云KODO：[GitHub](https://github.com/sy-records/qiniu-kodo-wordpress)，[WordPress Plugins](https://wordpress.org/plugins/kodo-qiniu)
又拍云USS：[GitHub](https://github.com/sy-records/upyun-uss-wordpress)，[WordPress Plugins](https://wordpress.org/plugins/uss-upyun)
阿里云OSS：[GitHub](https://github.com/sy-records/aliyun-oss-wordpress)，[WordPress Plugins](https://wordpress.org/plugins/oss-aliyun)

## 作者博客

[沈唁志](https://qq52o.me "沈唁志")

QQ交流群：887595381

== Installation ==

1. Upload the folder `huaweicloud-obs-wordpress` or `obs-huaweicloud` to the `/wp-content/plugins/` directory
2. Activate the plugin through the 'Plugins' menu in WordPress
3. That's all

== Screenshots ==

1. screenshot-1.png
2. screenshot-2.png

== Frequently Asked Questions ==

= 怎么替换文章中之前的旧资源地址链接 =

这个插件已经加上了替换数据库中之前的旧资源地址链接功能，只需要填好对应的链接即可

== Changelog ==

= 1.2.0 =
* 优化同步上传路径获取
* 修复多站点上传原图失败，缩略图正常问题
* 优化上传路径获取
* 增加数据库题图链接替换

= 1.1.0 =
* 修复勾选不在本地保存图片后媒体库显示默认图片问题
* 修改删除为批量删除
* 修复本地文件夹为根目录时路径错误

= 1.0.1 =
* 修复勾选不在本地保存图片后媒体库显示默认图片问题

= 1.0.0 =
* First version
