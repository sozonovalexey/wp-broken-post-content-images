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

    if ($debug) {
        echo get_option('siteurl');
    }

    ?>
    <div class="wrap">
        <h2>Broken Post Content Images</h2>
        <?php
        if ( !isset($_POST['step']) ) {
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

            foreach ($postid_list as $v) {
                $postid = $v->ID;
                $post = $wpdb->get_results("SELECT post_content FROM $wpdb->posts WHERE ID = '$postid'");
                $post_content = $post[0]->post_content;

                preg_match_all(BPCI_IMG_SRC_REGEXP, $post_content, $matches);

                foreach ($matches[1] as $url) {
//                    if ($debug == 1) {
//                        echo "url=$url<br>";
//                    }

                    $b = parse_url($url);
                    $image_url = ($b['scheme'] == 'http' || $b['scheme'] == 'https') ? $url : trim($site_url, '/') . '/' . trim($url, '/');
                    $img_processed[$url] = bpci_check_img($image_url);

                    if ($debug == 1) {
                        echo $image_url . '<br>';
                    }

                }
            }

//            if ($debug == 1) {
//                var_dump($img_processed);
//            }
            ?>
            <?php
            if (is_null($img_processed)) {
                die('Nothing to do.');
            } else {
                ?>
                <p><strong>Results:</strong></p>
                <form action="" method="post">
                    <ul>
                        <?php
                        foreach ($img_processed as $img => $status) {
                            ?>
                            <li style="color: <?php if ($status) { ?>green<?php } else { ?>red<?php } ?>;">
                                <?php echo $img; ?> ... <?php if ($status) { ?>OK!<?php } else { ?>Broken!<?php } ?>
                            </li>
                            <?php
                        }
                        ?>
                    </ul>
                    <p class="submit">
                        <input name="postid" type="hidden" id="postid" value="<?php echo $postidnum; ?>" />
                        <input name="step" type="hidden" id="step" value="3" />
                        <input type="submit" name="Submit" value="Replace broken images with a 1x1 transparent gif &raquo;" />
                    </p>
                </form>
                <?php
            }
        }
        ?>

        <?php
        if ('3' == $_POST['step']) {

            $postidnum = trim($_POST['postid']);

            if ($debug == 1) {
                echo $postidnum . " is the current post ID<br />";
            }

            if (strtoupper($postidnum) == 'ALL') {
                $postid_list = $wpdb->get_results("SELECT DISTINCT ID FROM $wpdb->posts WHERE post_content LIKE ('%<img%')");
            } else {
                $postid_list = $wpdb->get_results("SELECT DISTINCT ID FROM $wpdb->posts WHERE ID = '$postidnum'");
            }

            foreach ($postid_list as $v) {
                $post_id = $v->ID;
                bpci_replace_images($post_id);
            }

            echo '<h3>Done!</h3>';
        }
        ?>
    </div>
    <?php
}

function bpci_check_img($url) {
    return false === file_get_contents($url) ? false : true;
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

add_action('admin_menu', 'bpci_mm_ci_add_pages');

?>
