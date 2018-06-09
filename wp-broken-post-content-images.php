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

define( 'BPCI__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

#
#  This regexp pattern is used to find the
#  <img src="xxxx"> links in posts.
#
define('BPCI_IMG_SRC_REGEXP', '|<img.*?src=[\'"](.*?)[\'"].*?>|i');

if ( defined( 'WP_CLI' ) && WP_CLI ) {
    require_once( BPCI__PLUGIN_DIR . 'class.wp-broken-post-content-images-cli.php' );
}

?>