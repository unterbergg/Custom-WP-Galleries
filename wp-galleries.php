<?php
/*
Plugin Name: Custom WP Galleries
Description:
Version: 1.0.1
Author: Untebergg
License: GPLv2 or later
Text Domain: cwg
*/

if( !defined( 'CWG_VERSION' ) ) {
    define( 'CWG_VERSION', '1.0.1' );
}
if( !defined( 'CWG_PLUGIN_DIR' ) ) {
    define( 'CWG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}


class Custom_Wp_Galleries
{
    /**
     * Static property to hold our singleton instance
     *
     */
    static $instance = false;

    /**
     * @return void
     */
    private function __construct()
    {
        add_action('init', array($this, 'create_cpt'));
        add_action('plugins_loaded', array($this, 'image_sizes'), 0);
        add_action('admin_enqueue_scripts', array($this, 'admin_scripts'));
        add_action('add_meta_boxes', array($this, 'create_metaboxes'));
        add_action('save_post', array($this,'save_custom_meta'), 1);

        add_shortcode('custom-wp-gallery', array($this, 'cwg_shortcode_callback'));
        add_action('wp_enqueue_scripts', array($this, 'front_scripts'));
    }

    /**
     * If an instance exists, this returns it.  If not, it creates one and
     * retuns it.
     *
     * @return Custom_Wp_Galleries
     */
    public static function getInstance()
    {
        if (!self::$instance) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    /**
     * Admin styles and scripts
     *
     * @return void
     */
    public function admin_scripts()
    {
        wp_register_script( 'cwg-script', plugin_dir_url( __FILE__ ) . 'assets/js/admin-script.js', array('jquery'), CWG_VERSION, 'all');
        wp_enqueue_script( 'cwg-script' );
    }

    /**
     * Front styles and scripts
     *
     * @return void
     */
    public function front_scripts()
    {
        wp_enqueue_style('cwg_style', plugin_dir_url( __FILE__ ) . 'assets/css/style.css', array(), CWG_VERSION);
        wp_enqueue_style('cwg_lightgallery_style', plugin_dir_url( __FILE__ ) . 'assets/css/lightgallery.css', array(), CWG_VERSION);

        wp_enqueue_script('cwg_lightgallery_script', plugin_dir_url( __FILE__ ) . 'assets/js/lightgallery.min.js', array('jquery'), CWG_VERSION, 'all');
        wp_enqueue_script('cwg_script', plugin_dir_url( __FILE__ ) . 'assets/js/script.js', array('jquery', 'cwg_lightgallery_script'), CWG_VERSION, 'all');
    }

    /**
     * Custom image sizes
     *
     * @return void
     */
    public function image_sizes()
    {
        add_image_size( 'medium-gallery', 640, 480, true );
        add_image_size( 'small-gallery', 250, 250, true );
    }

    /**
     * Shortcode callback
     *
     * @return string
     */
    public function cwg_shortcode_callback($atts)
    {
        $images = [];

        if(!isset($atts['gallery-name']) || !isset($atts['gallery-size'])) {
            return;
        }

        $query = new WP_Query([
            "post_type" => 'wp_galleries',
            "name" => $atts['gallery-name']
        ]);

        if($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();

                $wpgallery_stored_meta = get_post_meta( get_the_ID() );

                foreach ($wpgallery_stored_meta as $key => $value) {
                    if (!str_contains($key, 'meta-image')) {
                        continue;
                    }

                    $attach_id = attachment_url_to_postid($value[0]);
                    $attach_size_thumb_url = wp_get_attachment_image_url($attach_id, $atts['gallery-size']);
                    $attach_size_image_url = wp_get_attachment_image_url($attach_id, 'full');
                    $images[] = [
                        'thumb' => $attach_size_thumb_url,
                        'origin' => $attach_size_image_url
                    ];
                }

            }
            wp_reset_postdata();
        }

        $align_items = $atts['gallery-size'] === 'medium-gallery' ? 'align-center' : '';
        ob_start();
        echo "<div id='lightgallery' class='cwg-gallery {$align_items}'>";
        foreach ($images as $image) {
            echo "    <a href='{$image['origin']}'>";
            echo "      <img src='{$image['thumb']}' alt=''>";
            echo "    </a>";
        }
        echo "</div>";
        $output = ob_get_contents();
        ob_end_clean();

        return $output;
    }

    /**
     * Create custom post type
     *
     * @return void
     */
    public function create_cpt()
    {
        register_post_type('wp_galleries',
            array(
                'labels'      => array(
                    'name'          => __('WP Galleries', 'wp-galleries'),
                    'singular_name' => __('WP Gallery', 'wp-galleries'),
                ),
                'public'      => true,
                'has_archive' => false,
            )
        );
    }


    /**
     * Call metabox
     *
     * @return void
     */
    public function create_metaboxes()
    {
        add_meta_box( 'gallery_gallery', __( 'Custom Gallery', 'cwg'), array($this, 'cwg_gallery_meta_box'), 'wp_galleries', 'normal', 'high' );
    }

    /**
     * Display meta fields for custom gallery meta
     *
     * @return void
     */
    public function cwg_gallery_meta_box($post)
    {
        wp_nonce_field( basename( __FILE__ ), 'cwg_nonce' );
        $cwg_stored_meta = get_post_meta( $post->ID );
        echo "<div class='wrap'>";
        echo "    <input type='button' id='add-input' value='Add Image'>";
        echo "    <div id='images-container'>";

        $counter = 1;
        foreach ($cwg_stored_meta as $key => $value) {
            if (!str_contains($key, 'meta-image')) { continue; }

        echo "            <p>";
        echo "                <label for='meta-image' class=''>Add Image</label>";
        echo "                <input type='text' name='meta-image-{$counter}' id='meta-image-{$counter}' value='{$value[0]}' />";
        echo "                <input type='button' class='meta-image-button button' value='Upload Image' />";
        echo "                <input type='button' class='meta-image-button button remove-button' value='Remove Image' />";
        echo "            </p>";

            $counter++;
        }

        echo "    </div>";
        echo "</div>";
    }

    /**
     * Save post metadata
     *
     * @return void
     */

    public function save_custom_meta( $post_id ) {

        $is_autosave = wp_is_post_autosave($post_id);
        $is_revision = wp_is_post_revision($post_id);
        $is_valid_nonce = (isset($_POST['cwg_nonce']) && wp_verify_nonce($_POST['cwg_nonce'], basename(__FILE__))) ? 'true' : 'false';

        if ($is_autosave || $is_revision || !$is_valid_nonce) {
            return;
        }

        foreach ($_POST as $key => $value) {
            update_post_meta($post_id, $key, $value);
        }
    }

}

$Custom_Wp_Galleries = Custom_Wp_Galleries::getInstance();