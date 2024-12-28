<?php
/*
Plugin Name: OBS HuaWeiCloud
Plugin URI: https://github.com/sy-records/huaweicloud-obs-wordpress
Description: 使用华为云对象存储服务 OBS 作为附件存储空间。（This is a plugin that uses HuaWei Cloud Object Storage Service for attachments remote saving.）
Version: 1.4.1
Author: 沈唁
Author URI: https://qq52o.me
License: Apache 2.0
*/

require_once 'sdk/vendor/autoload.php';

use Obs\ObsClient;
use Obs\ObsException;

define('OBS_VERSION', '1.4.1');
define('OBS_BASEFOLDER', plugin_basename(dirname(__FILE__)));

if (!function_exists('get_home_path')) {
    require_once ABSPATH . 'wp-admin/includes/file.php';
}

// 初始化选项
register_activation_hook(__FILE__, 'obs_set_options');
function obs_get_default_options()
{
    return [
        'bucket' => '',
        'regional' => 'cn-east-3',
        'key' => '',
        'secret' => '',
        'nothumb' => 'false', // 是否上传缩略图
        'nolocalsaving' => 'false', // 是否保留本地备份
        'upload_url_path' => '', // URL前缀
        'update_file_name' => 'false', // 是否更新文件名
    ];
}
function obs_set_options()
{
    add_option('obs_options', obs_get_default_options(), '', 'yes');
}

function obs_get_client()
{
    $obs_opt = get_option('obs_options', obs_get_default_options());
    return new ObsClient([
        'key' => esc_attr($obs_opt['key']),
        'secret' => esc_attr($obs_opt['secret']),
        'endpoint' => obs_get_bucket_endpoint($obs_opt),
    ]);
}

function obs_get_bucket_endpoint($obs_option)
{
    $obs_regional = esc_attr($obs_option['regional']);
    return 'obs.'. $obs_regional . '.myhuaweicloud.com';
}

function obs_get_bucket_name()
{
    $obs_opt = get_option('obs_options', obs_get_default_options());
    return $obs_opt['bucket'];
}

/**
 * @param string $object
 * @param string $file
 * @param bool $no_local_file
 */
function obs_file_upload($object, $file, $no_local_file = false)
{
    //如果文件不存在，直接返回false
    if (!@file_exists($file)) {
        return false;
    }
    $bucket_name = obs_get_bucket_name();
    $obsClient = obs_get_client();
    try {
        $obsClient->putObject([
            'Bucket' => $bucket_name,
            'Key' => ltrim($object, '/'),
            'SourceFile' => $file
        ]);
    } catch (ObsException $e) {
        error_log($e->getMessage());
    }
    if ($no_local_file) {
        obs_delete_local_file($file);
    }
}

/**
 * 是否需要删除本地文件
 *
 * @return bool
 */
function obs_is_delete_local_file()
{
    $obs_options = get_option('obs_options', obs_get_default_options());
    return esc_attr($obs_options['nolocalsaving']) == 'true';
}

/**
 * 删除本地文件
 *
 * @param string $file 本地文件路径
 * @return bool
 */
function obs_delete_local_file($file)
{
    try {
        //文件不存在
        if (!@file_exists($file)) {
            return true;
        }

        //删除文件
        if (!@unlink($file)) {
            return false;
        }

        return true;
    } catch (Exception $ex) {
        return false;
    }
}

/**
 * 删除obs中的单个文件
 * @param $file
 * @return void
 */
function obs_delete_obs_file($file)
{
    $bucket = obs_get_bucket_name();
    $obsClient = obs_get_client();
    $obsClient->deleteObject(array('Bucket' => $bucket, 'Key' => $file));
}

/**
 * 批量删除文件
 * @param $files
 */
function obs_delete_obs_files($files)
{
    $deleteObjects = [];
    foreach ($files as $file) {
        $fileKey = str_replace(["\\", './'], ['/', ''], $file);
        $deleteObjects[] = ['Key' => $fileKey];
    }

    $bucket = obs_get_bucket_name();
    $obsClient = obs_get_client();
    $obsClient->deleteObjects(array('Bucket' => $bucket, 'Objects' => $deleteObjects, 'Quiet' => false));
}

/**
 * 上传附件（包括图片的原图）
 *
 * @param  $metadata
 * @return array
 */
function obs_upload_attachments($metadata)
{
    $mime_types = get_allowed_mime_types();
    $image_mime_types = [
        $mime_types['jpg|jpeg|jpe'],
        $mime_types['gif'],
        $mime_types['png'],
        $mime_types['bmp'],
        $mime_types['tiff|tif'],
        $mime_types['ico']
    ];

    // 例如mp4等格式 上传后根据配置选择是否删除 删除后媒体库会显示默认图片 点开内容是正常的
    // 图片在缩略图处理
    if (!in_array($metadata['type'], $image_mime_types)) {
        //生成object在obs中的存储路径
        if (get_option('upload_path') == '.') {
            $metadata['file'] = str_replace("./", '', $metadata['file']);
        }
        $object = str_replace("\\", '/', $metadata['file']);
        $home_path = get_home_path();
        $object = str_replace($home_path, '', $object);

        //在本地的存储路径
        $file = $home_path . $object; //向上兼容，较早的WordPress版本上$metadata['file']存放的是相对路径

        //执行上传操作
        obs_file_upload('/' . $object, $file, obs_is_delete_local_file());
    }

    return $metadata;
}

//避免上传插件/主题时出现同步到obs的情况
if (substr_count($_SERVER['REQUEST_URI'], '/update.php') <= 0) {
    add_filter('wp_handle_upload', 'obs_upload_attachments', 50);
    add_filter('wp_generate_attachment_metadata', 'obs_upload_thumbs', 100);
}

/**
 * 上传图片的缩略图
 */
function obs_upload_thumbs($metadata)
{
    if (empty($metadata['file'])) {
        return $metadata;
    }

    //获取上传路径
    $wp_uploads = wp_upload_dir();
    $basedir = $wp_uploads['basedir'];
    $upload_path = get_option('upload_path');
    //获取obs插件的配置信息
    $obs_options = get_option('obs_options', obs_get_default_options());
    $no_local_file = esc_attr($obs_options['nolocalsaving']) == 'true';
    $no_thumb = esc_attr($obs_options['nothumb']) == 'true';

    $file = $basedir . '/' . $metadata['file'];
    if ($upload_path != '.') {
        $path_array = explode($upload_path, $file);
        if (count($path_array) >= 2) {
            $object = '/' . $upload_path . end($path_array);
        }
    } else {
        $object = '/' . $metadata['file'];
        $file = str_replace('./', '', $file);
    }
    obs_file_upload($object, $file, $no_local_file);

    //得到本地文件夹和远端文件夹
    $dirname = dirname($metadata['file']);
    $file_path = $dirname != '.' ? "{$basedir}/{$dirname}/" : "{$basedir}/";
    $file_path = str_replace("\\", '/', $file_path);
    if ($upload_path == '.') {
        $file_path = str_replace('./', '', $file_path);
    }
    $object_path = str_replace(get_home_path(), '', $file_path);

    if (!empty($metadata['original_image'])) {
        obs_file_upload("/{$object_path}{$metadata['original_image']}", "{$file_path}{$metadata['original_image']}", $no_local_file);
    }

    //如果禁止上传缩略图，就不用继续执行了
    if ($no_thumb) {
        return $metadata;
    }

    //上传所有缩略图
    if (!empty($metadata['sizes'])) {
        foreach ($metadata['sizes'] as $val) {
            $object = '/' . $object_path . $val['file'];
            $file = $file_path . $val['file'];

            obs_file_upload($object, $file, $no_local_file);
        }
    }

    return $metadata;
}

/**
 * 删除远端文件，删除文件时触发
 * @param $post_id
 */
function obs_delete_remote_attachment($post_id)
{
    $wp_uploads = wp_upload_dir();
    $basedir = $wp_uploads['basedir'];
    $upload_path = str_replace(get_home_path(), '', $basedir);
    $meta = wp_get_attachment_metadata($post_id);

    if (!empty($meta['file'])) {
        $deleteObjects = [];

        // meta['file']的格式为 "2020/01/wp-bg.png"
        $file_path = $upload_path . '/' . $meta['file'];
        $dirname = dirname($file_path) . '/';

        $deleteObjects[] = $file_path;

        // 超大图原图
        if (!empty($meta['original_image'])) {
            $deleteObjects[] = $dirname . $meta['original_image'];
        }

        // 删除缩略图
        if (!empty($meta['sizes'])) {
            foreach ($meta['sizes'] as $val) {
                $deleteObjects[] = $dirname . $val['file'];
            }
        }

        $backup_sizes = get_post_meta($post_id, '_wp_attachment_backup_sizes', true);
        if (is_array($backup_sizes)) {
            foreach ($backup_sizes as $size) {
                $deleteObjects[] = $dirname . $size['file'];
            }
        }

        obs_delete_obs_files($deleteObjects);
    }
}
add_action('delete_attachment', 'obs_delete_remote_attachment');

// 当upload_path为根目录时，需要移除URL中出现的“绝对路径”
function obs_modefiy_img_url($url, $post_id)
{
    // 移除 ./ 和 项目根路径
    $url = str_replace(['./', get_home_path()], '', $url);
    return $url;
}

if (get_option('upload_path') == '.') {
    add_filter('wp_get_attachment_url', 'obs_modefiy_img_url', 30, 2);
}

function obs_sanitize_file_name($filename)
{
    $obs_options = get_option('obs_options', obs_get_default_options());
    switch ($obs_options['update_file_name']) {
        case 'md5':
            return md5($filename) . '.' . pathinfo($filename, PATHINFO_EXTENSION);
        case 'time':
            return gmdate('YmdHis', current_time('timestamp')) . wp_rand(100, 999) . '.' . pathinfo($filename, PATHINFO_EXTENSION);
        default:
            return $filename;
    }
}
add_filter('sanitize_file_name', 'obs_sanitize_file_name', 10, 1);

function obs_function_each(&$array)
{
    $res = [];
    $key = key($array);
    if ($key !== null) {
        next($array);
        $res[1] = $res['value'] = $array[$key];
        $res[0] = $res['key'] = $key;
    } else {
        $res = false;
    }
    return $res;
}

/**
 * @param string $homePath
 * @param string $uploadPath
 * @return array
 */
function obs_read_dir_queue($homePath, $uploadPath)
{
    $dir = $homePath . $uploadPath;
    $dirsToProcess = new SplQueue();
    $dirsToProcess->enqueue([$dir, '']);
    $foundFiles = [];

    while (!$dirsToProcess->isEmpty()) {
        [$currentDir, $relativeDir] = $dirsToProcess->dequeue();

        foreach (new DirectoryIterator($currentDir) as $fileInfo) {
            if ($fileInfo->isDot()) continue;

            $filepath = $fileInfo->getRealPath();

            // Compute the relative path of the file/directory with respect to upload path
            $currentRelativeDir = "{$relativeDir}/{$fileInfo->getFilename()}";

            if ($fileInfo->isDir()) {
                $dirsToProcess->enqueue([$filepath, $currentRelativeDir]);
            } else {
                // Add file path and key to the result array
                $foundFiles[] = [
                    'filepath' => $filepath,
                    'key' => '/' . $uploadPath . $currentRelativeDir
                ];
            }
        }
    }

    return $foundFiles;
}

function obs_get_regional($regional)
{
    $options = [
        'af-south-1' => '非洲-约翰内斯堡',
        'ap-southeast-1' => '中国-香港',
        'ap-southeast-2' => '亚太-曼谷',
        'ap-southeast-3' => '亚太-新加坡',
        'ap-southeast-4' => '亚太-雅加达',
        'cn-east-2' => '华东-上海二',
        'cn-east-3' => '华东-上海一',
        'cn-north-1' => '华北-北京一',
        'cn-north-4' => '华北-北京四',
        'cn-north-9' => '华北-乌兰察布一',
        'cn-south-1' => '华南-广州',
        'cn-south-2' => '华南-深圳',
        'cn-south-4' => '华南-广州-友好用户环境',
        'cn-southwest-2' => '西南-贵阳一',
        'la-north-2' => '拉美-墨西哥城二',
        'la-south-2' => '拉美-圣地亚哥',
        'na-mexico-1' => '拉美-墨西哥城一',
        'sa-brazil-1' => '拉美-圣保罗一',
        'tr-west-1' => '土耳其-伊斯坦布尔',
    ];
    foreach ($options as $value => $info) {
        $selected = $regional == $value ? 'selected="selected"' : '';
        echo '<option value="' . $value . '" ' . $selected . '>' . $info . '</option>';
    }
}

// 在插件列表页添加设置按钮
function obs_plugin_action_links($links, $file)
{
    if ($file == plugin_basename(dirname(__FILE__) . '/huaweicloud-obs-wordpress.php')) {
        $links[] = '<a href="options-general.php?page=' . OBS_BASEFOLDER . '/huaweicloud-obs-wordpress.php">设置</a>';
        $links[] = '<a href="https://qq52o.me/sponsor.html" target="_blank">赞赏</a>';
    }
    return $links;
}
add_filter('plugin_action_links', 'obs_plugin_action_links', 10, 2);

// 在导航栏“设置”中添加条目
function obs_add_setting_page()
{
    add_options_page('华为云OBS设置', '华为云OBS设置', 'manage_options', __FILE__, 'obs_setting_page');
}

add_action('admin_menu', 'obs_add_setting_page');

// 插件设置页面
function obs_setting_page()
{
    if (!current_user_can('manage_options')) {
        wp_die('Insufficient privileges!');
    }
    $options = [];
    if (!empty($_POST) && $_POST['type'] == 'obs_set') {
        $options['bucket'] = isset($_POST['bucket']) ? sanitize_text_field($_POST['bucket']) : '';
        $options['regional'] = isset($_POST['regional']) ? sanitize_text_field($_POST['regional']) : '';
        $options['key'] = isset($_POST['key']) ? sanitize_text_field($_POST['key']) : '';
        $options['secret'] = isset($_POST['secret']) ? sanitize_text_field($_POST['secret']) : '';
        $options['nothumb'] = isset($_POST['nothumb']) ? 'true' : 'false';
        $options['nolocalsaving'] = isset($_POST['nolocalsaving']) ? 'true' : 'false';
        //仅用于插件卸载时比较使用
        $options['upload_url_path'] = isset($_POST['upload_url_path']) ? sanitize_text_field(stripslashes($_POST['upload_url_path'])) : '';
        $options['update_file_name'] = isset($_POST['update_file_name']) ? sanitize_text_field($_POST['update_file_name']) : 'false';
    }

    if (!empty($_POST) && $_POST['type'] == 'huaweicloud_obs_all') {
        $files = obs_read_dir_queue(get_home_path(), get_option('upload_path'));
        foreach ($files as $file) {
            obs_file_upload($file['key'], $file['filepath']);
        }
        echo '<div class="updated"><p><strong>本次操作成功同步' . count($files) . '个文件</strong></p></div>';
    }

    // 替换数据库链接
    if(!empty($_POST) && $_POST['type'] == 'huaweicloud_obs_replace') {
        $old_url = esc_url_raw($_POST['old_url']);
        $new_url = esc_url_raw($_POST['new_url']);

        if (!empty($old_url) && !empty($new_url)) {
            global $wpdb;
            // 文章内容
            $posts_name = $wpdb->prefix . 'posts';
            $posts_result = $wpdb->query($wpdb->prepare("UPDATE $posts_name SET post_content = REPLACE(post_content, '%s', '%s')", [$old_url, $new_url]));

            // 修改题图之类的
            $postmeta_name = $wpdb->prefix . 'postmeta';
            $postmeta_result = $wpdb->query($wpdb->prepare("UPDATE $postmeta_name SET meta_value = REPLACE(meta_value, '%s', '%s')", [$old_url, $new_url]));

            echo '<div class="updated"><p><strong>替换成功！共替换文章内链' . $posts_result . '条、题图链接' . $postmeta_result . '条！</strong></p></div>';
        } else {
            echo '<div class="error"><p><strong>请填写资源链接URL地址！</strong></p></div>';
        }
    }

    // 若$options不为空数组，则更新数据
    if ($options !== []) {
        //更新数据库
        update_option('obs_options', $options);

        $upload_path = sanitize_text_field(trim(stripslashes($_POST['upload_path']), '/'));
        $upload_path = $upload_path == '' ? 'wp-content/uploads' : $upload_path;
        update_option('upload_path', $upload_path);
        $upload_url_path = sanitize_text_field(trim(stripslashes($_POST['upload_url_path']), '/'));
        update_option('upload_url_path', $upload_url_path);
        echo '<div class="updated"><p><strong>设置已保存！</strong></p></div>';
    }

    $obs_options = get_option('obs_options', obs_get_default_options());
    $upload_path = get_option('upload_path');
    $upload_url_path = get_option('upload_url_path');

    $obs_bucket = esc_attr($obs_options['bucket']);
    $obs_regional = esc_attr($obs_options['regional']);
    $obs_key = esc_attr($obs_options['key']);
    $obs_secret = esc_attr($obs_options['secret']);

    $obs_nothumb = esc_attr($obs_options['nothumb']);
    $obs_nothumb = $obs_nothumb == 'true';

    $obs_nolocalsaving = esc_attr($obs_options['nolocalsaving']);
    $obs_nolocalsaving = $obs_nolocalsaving == 'true';
    $obs_update_file_name = esc_attr($obs_options['update_file_name'] ?? 'false');

    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? 'https://' : 'http://';
    ?>
    <div class="wrap" style="margin: 10px;">
        <h1>华为云 OBS 设置 <span style="font-size: 13px;">当前版本：<?php echo OBS_VERSION; ?></span></h1>
        <p>如果觉得此插件对你有所帮助，不妨到 <a href="https://github.com/sy-records/huaweicloud-obs-wordpress" target="_blank">GitHub</a> 上点个<code>Star</code>，<code>Watch</code>关注更新；<a href="https://go.qq52o.me/qm/ccs" target="_blank">欢迎加入云存储插件交流群，QQ群号：887595381</a>；</p>
        <hr/>
        <form name="form1" method="post" action="<?php echo wp_nonce_url('./options-general.php?page=' . OBS_BASEFOLDER . '/huaweicloud-obs-wordpress.php'); ?>">
            <table class="form-table">
                <tr>
                    <th>
                        <legend>桶名称</legend>
                    </th>
                    <td>
                        <input type="text" name="bucket" value="<?php echo $obs_bucket; ?>" size="50" placeholder="请填写桶名称"/>

                        <p>请先访问 <a href="https://storage.huaweicloud.com/obs/?region=cn-east-3#/obs/create" target="_blank">华为云控制台</a> 创建<code>桶</code>，再填写以上内容。</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>区域</legend>
                    </th>
                    <td>
                        <select name="regional"><?php obs_get_regional($obs_regional); ?></select>
                        <p>请选择您创建的<code>桶</code>所在区域</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>key</legend>
                    </th>
                    <td><input type="text" name="key" value="<?php echo $obs_key; ?>" size="50" placeholder="key"/></td>
                </tr>
                <tr>
                    <th>
                        <legend>secret</legend>
                    </th>
                    <td>
                        <input type="text" name="secret" value="<?php echo $obs_secret; ?>" size="50" placeholder="secret"/>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>不上传缩略图</legend>
                    </th>
                    <td>
                        <input type="checkbox" name="nothumb" <?php echo $obs_nothumb ? 'checked="checked"' : ''; ?> />
                        <p>建议不勾选</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>不在本地保留备份</legend>
                    </th>
                    <td>
                        <input type="checkbox" name="nolocalsaving" <?php echo $obs_nolocalsaving ? 'checked="checked"' : ''; ?> />
                        <p>建议不勾选</p>
                    </td>
                </tr>
                <tr>
                  <th>
                    <legend>自动重命名文件</legend>
                  </th>
                  <td>
                    <select name="update_file_name">
                      <option <?php echo $obs_update_file_name == 'false' ? 'selected="selected"' : '';?> value="false">不处理</option>
                      <option <?php echo $obs_update_file_name == 'md5' ? 'selected="selected"' : '';?> value="md5">MD5</option>
                      <option <?php echo $obs_update_file_name == 'time' ? 'selected="selected"' : '';?> value="time">时间戳+随机数</option>
                    </select>
                  </td>
                </tr>
                <tr>
                    <th>
                        <legend>本地文件夹</legend>
                    </th>
                    <td>
                        <input type="text" name="upload_path" value="<?php echo $upload_path; ?>" size="50" placeholder="请输入上传文件夹"/>
                        <p>附件在服务器上的存储位置，例如： <code>wp-content/uploads</code> （注意不要以“/”开头和结尾），根目录请输入<code>.</code>。</p>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend>URL前缀</legend>
                    </th>
                    <td>
                        <input type="text" name="upload_url_path" value="<?php echo $upload_url_path; ?>" size="50" placeholder="请输入URL前缀"/>

                        <p><b>注意：</b></p>

                        <p>1）URL前缀的格式为 <code><?php echo $protocol;?>{obs域名}/{本地文件夹}</code> ，“本地文件夹”务必与上面保持一致（结尾无 <code>/</code> ），或者“本地文件夹”为 <code>.</code> 时 <code><?php echo $protocol;?>{obs域名}</code> 。</p>

                        <p>2）OBS中的存放路径（即“文件夹”）与上述 <code>本地文件夹</code> 中定义的路径是相同的（出于方便切换考虑）。</p>

                        <p>3）如果需要使用 <code>用户域名</code> ，直接将 <code>{obs域名}</code> 替换为 <code>用户域名</code> 即可。</p>
                    </td>
                </tr>
                <tr>
                    <th><legend>保存/更新选项</legend></th>
                    <td><input type="submit" name="submit" class="button button-primary" value="保存更改"/></td>
                </tr>
            </table>
            <input type="hidden" name="type" value="obs_set">
        </form>
        <form name="form2" method="post" action="<?php echo wp_nonce_url('./options-general.php?page=' . OBS_BASEFOLDER . '/huaweicloud-obs-wordpress.php'); ?>">
            <table class="form-table">
                <tr>
                    <th>
                        <legend>同步历史附件</legend>
                    </th>
                    <input type="hidden" name="type" value="huaweicloud_obs_all">
                    <td>
                        <input type="submit" name="submit" class="button button-secondary" value="开始同步"/>
                        <p><b>注意：如果是首次同步，执行时间将会十分十分长（根据你的历史附件数量），有可能会因执行时间过长，页面显示超时或者报错。<br> 所以，建议那些几千上万附件的大神们，考虑官方的 <a target="_blank" rel="nofollow" href="https://support.huaweicloud.com/utiltg-obs/obs_11_0001.html">同步工具</a></b></p>
                    </td>
                </tr>
            </table>
        </form>
        <hr>
        <form name="form3" method="post" action="<?php echo wp_nonce_url('./options-general.php?page=' . OBS_BASEFOLDER . '/huaweicloud-obs-wordpress.php'); ?>">
            <table class="form-table">
                <tr>
                    <th>
                        <legend>数据库原链接替换</legend>
                    </th>
                    <td>
                        <input type="text" name="old_url" size="50" placeholder="请输入要替换的旧域名"/>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend></legend>
                    </th>
                    <td>
                        <input type="text" name="new_url" size="50" placeholder="请输入要替换的新域名"/>
                    </td>
                </tr>
                <tr>
                    <th>
                        <legend></legend>
                    </th>
                    <input type="hidden" name="type" value="huaweicloud_obs_replace">
                    <td>
                        <input type="submit" name="submit"  class="button button-secondary" value="开始替换"/>
                        <p><b>注意：如果是首次替换，请注意备份！此功能会替换文章以及设置的特色图片（题图）等使用的资源链接</b></p>
                    </td>
                </tr>
            </table>
        </form>
    </div>
<?php
}
?>
