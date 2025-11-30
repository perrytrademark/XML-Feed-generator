<?php
/*
Plugin Name: Press Start XML Feed Generator
Plugin URI: https://press-start.gr
Description: Generates a daily WooCommerce XML feed with product filtering options.
Version: 1.1
Author: Your Name
Author URI: https://press-start.gr
License: GPL2
*/

if (!defined('ABSPATH')) {
    exit; // Prevent direct access
}

// Plugin settings
function ps_xml_feed_register_settings() {
    add_option('ps_xml_feed_exclude_tags', '');
    register_setting('ps_xml_feed_options_group', 'ps_xml_feed_exclude_tags');
}
add_action('admin_init', 'ps_xml_feed_register_settings');

// Add admin menu item
function ps_xml_feed_admin_menu() {
    add_submenu_page(
        'woocommerce',
        'XML Feed Generator',
        'XML Feed Generator',
        'manage_options',
        'ps-xml-feed',
        'ps_xml_feed_settings_page'
    );
}
add_action('admin_menu', 'ps_xml_feed_admin_menu');

// Settings page
function ps_xml_feed_settings_page() {
    ?>
    <div class="wrap">
        <h1>Press Start XML Feed Generator</h1>
        <form method="post" action="options.php">
            <?php settings_fields('ps_xml_feed_options_group'); ?>
            <?php do_settings_sections('ps_xml_feed_options_group'); ?>

            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Exclude Products by Tags</th>
                    <td>
                        <input type="text" name="ps_xml_feed_exclude_tags" value="<?php echo esc_attr(get_option('ps_xml_feed_exclude_tags')); ?>" style="width: 300px;" />
                        <p class="description">Enter tag slugs separated by commas (e.g., "chondrikis, test").</p>
                    </td>
                </tr>
            </table>

            <?php submit_button(); ?>
        </form>

        <h2>Feed URL</h2>
        <p><strong>Current Feed:</strong> <a href="<?php echo content_url('/uploads/product-feed.xml'); ?>" target="_blank"><?php echo content_url('/uploads/product-feed.xml'); ?></a></p>
        
        <h2>Manually Regenerate Feed</h2>
        <p><a href="<?php echo admin_url('admin.php?page=ps-xml-feed&generate_feed=true'); ?>" class="button button-primary">Generate Feed Now</a></p>

    </div>
    <?php

    // Check for manual feed generation
    if (isset($_GET['generate_feed']) && $_GET['generate_feed'] === 'true') {
        ps_generate_product_feed();
        echo '<div class="updated"><p>Feed has been regenerated successfully!</p></div>';
    }
}

// Schedule cron job
function ps_schedule_product_feed_refresh() {
    if (!wp_next_scheduled('ps_refresh_product_feed')) {
        wp_schedule_event(time(), 'daily', 'ps_refresh_product_feed');
    }
}
register_activation_hook(__FILE__, 'ps_schedule_product_feed_refresh');

// Clear cron job on deactivation
function ps_unschedule_product_feed_refresh() {
    wp_clear_scheduled_hook('ps_refresh_product_feed');
}
register_deactivation_hook(__FILE__, 'ps_unschedule_product_feed_refresh');

// Function to generate the XML feed
function ps_generate_product_feed() {
    $feed_file = WP_CONTENT_DIR . '/uploads/product-feed.xml';
    ob_start();

    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo "<products>\n";

    // Get excluded tags from settings
    $exclude_tags = get_option('ps_xml_feed_exclude_tags', '');
    $exclude_tags_array = array_filter(array_map('trim', explode(',', $exclude_tags)));

    $tax_query = array();
    if (!empty($exclude_tags_array)) {
        $tax_query[] = array(
            'taxonomy' => 'product_tag',
            'field'    => 'slug',
            'terms'    => $exclude_tags_array,
            'operator' => 'NOT IN',
        );
    }

    // Query WooCommerce products
    $args = array(
        'post_type'      => 'product',
        'posts_per_page' => -1,
        'post_status'    => 'publish',
        'tax_query'      => $tax_query,
    );

    $products = get_posts($args);

    if (!$products) {
        echo "<!-- No products found -->\n";
    } else {
        foreach ($products as $post) {
            $product = wc_get_product($post->ID);
            if (!$product) continue;

            $price = $product->get_price();
            $link = get_permalink($post->ID);
            $image = wp_get_attachment_url($product->get_image_id());
            $stock_status = $product->is_in_stock() ? 'In Stock' : 'Out of Stock';

            // Get product categories
            $categories = wp_get_post_terms($post->ID, 'product_cat', array('fields' => 'names'));
            $category_list = !empty($categories) ? implode(', ', $categories) : 'Uncategorized';

            echo "  <product>\n";
            echo "    <id>" . esc_xml($post->ID) . "</id>\n";
            echo "    <title>" . esc_xml($post->post_title) . "</title>\n";
            echo "    <description>" . esc_xml($post->post_excerpt) . "</description>\n";
            echo "    <link>" . esc_xml($link) . "</link>\n";
            echo "    <image_link>" . esc_xml($image) . "</image_link>\n";
            echo "    <price>" . esc_xml($price) . "</price>\n";
            echo "    <stock_status>" . esc_xml($stock_status) . "</stock_status>\n";
            echo "    <category>" . esc_xml($category_list) . "</category>\n";
            echo "  </product>\n";
        }
    }

    echo "</products>\n";
    file_put_contents($feed_file, ob_get_clean());
}
add_action('ps_refresh_product_feed', 'ps_generate_product_feed');

// Function to escape XML
function esc_xml($string) {
    return htmlspecialchars(strip_tags(trim($string)), ENT_XML1, 'UTF-8');
}

// Add admin notice on activation
function ps_product_feed_admin_notice() {
    ?>
    <div class="notice notice-success is-dismissible">
        <p><strong>Press Start XML Feed Generator</strong> is active! Configure it under <a href="<?php echo admin_url('admin.php?page=ps-xml-feed'); ?>">WooCommerce → XML Feed Generator</a>.</p>
    </div>
    <?php
}
add_action('admin_notices', 'ps_product_feed_admin_notice');

