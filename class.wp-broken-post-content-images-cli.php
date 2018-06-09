<?php

WP_CLI::add_command( 'bpci', 'BPCI_CLI' );

class BPCI_CLI extends WP_CLI_Command {
    /**
     * Checks one or more comments against the Akismet API.
     *
     * ## OPTIONS
     * <post_id>...
     * : The ID(s) of the post(s) to check.
     *
     * [--skip]
     * : Skip n posts before checking.
     *
     * [--limit]
     * : Check a limited number of posts.
     *
     * ## EXAMPLES
     *
     *     wp bpci check all --skip 10 --limit 100
     *     wp bpci check 12345
     *
     * @alias comment-check
     */
    public function check( $args, $assoc_args ) {
        global $wpdb;
        $site_url = get_option('siteurl');
        $counter = 0;
        $total = 0;
        $sql = "SELECT id, post_content FROM $wpdb->posts WHERE post_type='post' AND post_status='publish'";

        foreach ( $args as $post_id ) {
            if ($post_id === 'all') {

                $sql .= " ORDER BY id ASC";

                if ( isset( $assoc_args['limit'] ) ) {
                    $limit = $assoc_args['limit'];
                    $sql .= " LIMIT $limit";
                }

                if ( isset( $assoc_args['skip'] ) ) {
                    $skip = $assoc_args['skip'];
                    $sql .= " OFFSET $skip";
                }

            }
            else {
                $sql .= " AND ID = '$post_id'";
            }

            $results = $wpdb->get_results($sql);

            if ($results) {

                $total = count($results);

                foreach ($results as $obj) {

                    $post_id = $obj->id;
                    $post_content = $obj->post_content;

                    preg_match_all(BPCI_IMG_SRC_REGEXP, $post_content, $matches);

                    foreach ($matches[1] as $src) {

                        $b = parse_url($src);
                        $image_url = ($b['scheme'] == 'http' || $b['scheme'] == 'https') ? $src : trim($site_url, '/') . '/' . trim($src, '/');

                        $is_broken = true;

                        $curl = curl_init($image_url);
                        curl_setopt($curl, CURLOPT_NOBODY, true);
                        $result = curl_exec($curl);
                        if ($result !== false) {
                            $status_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
                            if ($status_code == 200) {
                                $is_broken = false;
                            }
                        }
                        curl_close($curl);

                        if ($is_broken) {
                            $wpdb->query("UPDATE $wpdb->posts SET post_content = REPLACE(post_content, '$src', '/wp-content/plugins/wp-broken-post-content-images/wp-broken-post-content-images-transaprent-1x1.gif') WHERE ID = '$post_id';");
                            flush();
                            WP_CLI::line(sprintf(__("Image %s in Post #%d was broken and now it is fixed.", 'bpci'), $src, $post_id));
                        }

                    }

                    ++$counter;
                    WP_CLI::line(sprintf(__( "Post #%d was checked [%d/%d].", 'bpci' ), $post_id, $counter, $total ));
                }

            }
            else {
                WP_CLI::line( __( "Nothing to do.", 'bpci' ) );
            }

            if ($post_id === 'all') {
                break;
            }
        }

        WP_CLI::line( __( "Done!", 'bpci' ) );
    }

}