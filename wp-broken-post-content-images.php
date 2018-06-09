<?php
/*
Plugin Name: Broken Post Content Images
Plugin URI: https://github.com/sozonovalexey/wp-broken-post-content-images/
Description: Goes through your posts and handles the broken images that might appear on the posts on your blog.
Version: 0.1
Author: Sozonov Alexey
Author URI: https://sozonov-alexey.ru/
WordPress Version Required: 1.5
*/

register_activation_hook( __FILE__, 'bpci_plugin_activate' );

function bpci_plugin_activate(){
    if (!is_plugin_active('batch_operations/batch.php') and current_user_can('activate_plugins')) {
        // Stop activation redirect and show error
        wp_die('Sorry, but this plugin requires the <a href="https://github.com/IgorVBelousov/batch_operations" target="_blank">Batch operations plugin</a> to be installed and active. <br><a href="' . admin_url( 'plugins.php' ) . '">&laquo; Return to Plugins</a>');
    }
}

#
#  This regexp pattern is used to find the
#  <img src="xxxx"> links in posts.
#
define('BPCI_IMG_SRC_REGEXP', '|<img.*?src=[\'"](.*?)[\'"].*?>|i');

function bpci_mm_ci_add_pages() {
    add_management_page('Broken Post Content Images Plugin', 'Broken Post Content Images', 8, __FILE__,
      'bpci_mm_ci_manage_page');
}

function bpci_mm_ci_manage_page() {
    global $wpdb;
    $debug = 0;

    $img_processed = [];
    $site_url = get_option('siteurl');

    $page_url = '/wp-admin/tools.php?page=wp-broken-post-content-images%2Fwp-broken-post-content-images.php';

    if ($debug) {
        echo get_option('siteurl');
    }

    ?>
    <div class="wrap">

        <?php
        if (isset($_GET['step']) && '4' == $_GET['step']) {
            echo '<h3>Done!</h3>';
            echo '<a href="' . $page_url .'">Back</a>';
            exit;
        }
        ?>

        <h2>Broken Post Content Images</h2>
        <?php
        if ( !isset($_POST['step']) && !isset($_GET['step'])) {
            ?>
            <p>Here's how this plugin works:</p>
            <ol>
                <li>To check the images for an existing post, enter the <b>post id</b> in the field below.</li>
                <li>If you want to check all posts, then enter <b>ALL</b> in the post id field.</li>
                <li>Then you'll be presented with a list of checked images.</li>
            </ol>
            <form action="" method="post">
                <div class="submit">
                    Post ID: <input name="postid" type="text" id="postid" value="enter a post id here">
                    <input name="step" type="hidden" id="step" value="2">
                    <input type="submit" name="Submit" value="Get Started &raquo;" />
                </div>
            </form>
            <?php
        }
        ?>

        <?php
        $postidnum = trim($_POST['postid']);

        if ('2' == $_POST['step']) {
            if (strtoupper($postidnum) == 'ALL' || $postidnum == 'enter a post id here') {
                $postid_list = $wpdb->get_results("SELECT DISTINCT ID FROM $wpdb->posts WHERE post_content LIKE ('%<img%')");
                if (!$postid_list) {
                    die('No posts with images were found.');
                }
            } else {
                $postid_list = $wpdb->get_results("SELECT DISTINCT ID FROM $wpdb->posts WHERE ID = '$postidnum'");
                if (!$postid_list) {
                    die('No posts with this Post ID were found.');
                }
            }

            if ($debug == 1) {
                echo $postidnum . " was the post ID chosen<br />";
            }

            $temp = [];
            $operations = [];
            foreach ($postid_list as $v) {
                $post_id = $v->ID;
                $temp[$post_id] = ['bpci_batch_operation', [$post_id, 'check']];
            }

            foreach ($temp as $operation) {
                $operations[] = $operation;
            }

            $batch['title'] = 'Checking images';
            $batch['operations'] = $operations;
            $redirect = '/wp-admin/tools.php?page=wp-broken-post-content-images%2Fwp-broken-post-content-images.php&step=3';

            bpci_clear_log_file();

            batch_operations_start($batch, $redirect);

            ?>
            <?php
        }
        ?>

        <?php
        if (isset($_GET['step']) && '3' == $_GET['step']) {
            ?>
            <p><strong>Broken images:</strong></p>

            <?php
            $img_processed = bpci_get_broken_images();
            ?>

            <form action="" method="post">
                <?php if (count($img_processed) > 0) { ?>
                    <ul>
                        <?php
                        foreach ($img_processed as $post_id => $images) {
                            foreach ($images as $image) {
                                ?>
                                <li style="color: red">
                                    Post ID: <?php echo $post_id; ?>; <?php echo $image; ?>
                                </li>
                                <?php
                            }
                        }
                        ?>
                    </ul>
                <?php } else { echo '<span style="color:green;">Empty!</span>'; } ?>
                <?php if (count($img_processed) > 0) { ?>
                    <p class="submit">
                        <input name="step" type="hidden" id="step" value="3" />
                        <input type="submit" name="Submit" value="Replace broken images with a 1x1 transparent gif &raquo;" />
                    </p>
                <?php } else { ?>
                    <p class="submit"><a href="<?php echo $page_url; ?>">Back</a></p>
                <?php } ?>
            </form>
            <?php
        }
        ?>

        <?php
        if ('3' == $_POST['step']) {
            $operations = [];
            $img_processed = bpci_get_broken_images();
            foreach ($img_processed as $post_id => $images) {
                $operations[] = ['bpci_batch_operation', [$post_id, 'replace']];
            }

            bpci_clear_log_file();

            $batch['title'] = 'Replacing';
            $batch['operations'] = $operations;
            $redirect = '/wp-admin/tools.php?page=wp-broken-post-content-images%2Fwp-broken-post-content-images.php&step=4';

            batch_operations_start($batch, $redirect);
        }
        ?>
    </div>
    <?php
}

function bpci_get_broken_images() {
    $return = [];
    $handle = fopen(__DIR__ . '/wp-broken-post-content-images.log', 'r');
    if ($handle) {
        while (($line = fgets($handle)) !== false) {
            $temp_arr = explode(';', $line);
            if (is_array($temp_arr) && !empty($temp_arr) && isset($temp_arr[1]) && !empty($temp_arr[1])) {
                $return[$temp_arr[0]][] = $temp_arr[1];
            }
        }
        fclose($handle);
    }
    return $return;
}

function bpci_batch_operation($post_id, $operation, &$context) {
    switch ($operation) {
        case 'replace':
            bpci_replace_images($post_id);
            break;
        case 'check':
            bpci_check_images($post_id);
            //$context['message'] = 'Broken images:<br><iframe style="width: 100%;height: 300px;" src="/wp-content/plugins/wp-broken-post-content-images/wp-broken-post-content-images.log"></iframe>';
            break;
    }

}

function bpci_check_img($url) {
    //TODO
    //return false === file_get_contents($url) ? false : true;

    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_NOBODY, true);
    $result = curl_exec($curl);
    $ret = false;
    if ($result !== false) {
        $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($statusCode == 200) {
            $ret = true;
        }
    }
    curl_close($curl);
    return $ret;
}

function bpci_check_images($postid) {
    global $wpdb;
    $site_url = get_option('siteurl');
    //$output = 'Post ID: ' . $postid . "\n";
    $output = '';

    $post = $wpdb->get_results("SELECT post_content FROM $wpdb->posts WHERE ID = '$postid'");
    $post_content = $post[0]->post_content;

    preg_match_all(BPCI_IMG_SRC_REGEXP, $post_content, $matches);

    foreach ($matches[1] as $url) {
        $b = parse_url($url);
        $image_url = ($b['scheme'] == 'http' || $b['scheme'] == 'https') ? $url : trim($site_url, '/') . '/' . trim($url, '/');
        $checked = bpci_check_img($image_url);
        if (!$checked) {
            //$output = 'Post ID: ' . $postid . '; ' . $url . ' ... ' . ($checked ? 'OK!' : 'Broken!') . "\n";
            $output = $postid  . ';' . $url . "\n";
        }
        //$output .= '<span style="color:' . ($checked ? 'green' : 'red') . '">' . $url . ' ... ' . ($checked ? 'OK!' : 'Broken!') . '</span><br>';
        //$output .= $url . ' ... ' . ($checked ? 'OK!' : 'Broken!') . "\n";
    }

    file_put_contents(__DIR__ . '/wp-broken-post-content-images.log', $output, FILE_APPEND | LOCK_EX);
    return true;
}

function bpci_replace_images($postid) {
    global $wpdb;

    $site_url = get_option('siteurl');

    $post = $wpdb->get_results("SELECT post_content FROM $wpdb->posts WHERE ID = '$postid'");
    $post_content = $post[0]->post_content;

    preg_match_all(BPCI_IMG_SRC_REGEXP, $post_content, $matches);

    foreach ($matches[1] as $url) {
        $b = parse_url($url);
        $image_url = ($b['scheme'] == 'http' || $b['scheme'] == 'https') ? $url : trim($site_url, '/') . '/' . trim($url, '/');
        $isBroken = !bpci_check_img($image_url);
        if ($isBroken) {
            $wpdb->query("UPDATE $wpdb->posts SET post_content = REPLACE(post_content, '$url', '/wp-content/plugins/wp-broken-post-content-images/wp-broken-post-content-images-transaprent-1x1.gif') WHERE ID = '$postid';");
            flush();
        }
    }

    return true;
}

function bpci_clear_log_file() {
    file_put_contents(__DIR__ . '/wp-broken-post-content-images.log', '');
}

add_action('admin_menu', 'bpci_mm_ci_add_pages');

?>