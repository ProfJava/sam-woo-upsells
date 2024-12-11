<?php
/**
 * Plugin Name: WooCommerce Checkout Customizer
 * Description: Customize WooCommerce checkout page based on user behavior and product types
 * Version: 1.0.0
 * Author: Prof
 */

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class WooCommerce_Checkout_Customizer {
    /**
     * Constructor to initialize plugin hooks
     */
    public function __construct() {

        add_action( 'wp_enqueue_scripts' , [ $this , 'enqueue_styles' ] );

        // Recommendations hooks
        add_action( 'woocommerce_checkout_before_customer_details' , [ $this , 'display_product_recommendations' ] );

        // Dynamic field customization hooks
        add_filter( 'woocommerce_checkout_fields' , [ $this , 'customize_checkout_fields'] );

        // Save custom fields
        add_action( 'woocommerce_checkout_update_order_meta' , [ $this , 'save_custom_checkout_fields'] );

    }

    /**
     * Display product recommendations based on user's purchase history
     */
    public function display_product_recommendations() {

        // Check if user is logged in
        if (!is_user_logged_in()) {
            return;
        }

        $current_user = wp_get_current_user();
        $recommended_products = $this->get_recommended_products($current_user->ID);

        if (empty($recommended_products)) {
            return;
        }

        echo '<div class="checkout-recommendations">';
        echo '<h3>You Might Also Like</h3>';
        echo '<div class="recommended-products">';

        foreach ($recommended_products as $product_id) {
            $product = wc_get_product($product_id);
            if ($product) {
                echo '<div class="recommended-product">';
                echo '<a href="' . esc_url($product->get_permalink()) . '">';
                echo $product->get_image('thumbnail');
                echo '<p>' . esc_html($product->get_name()) . '</p>';
                echo '<span class="price">' . $product->get_price_html() . '</span>';
                echo '</a>';
                echo '</div>';
            }
        }

        echo '</div>';
        echo '</div>';
    }

    /**
     * Get recommended products based on user's purchase history
     *
     * @param int $user_id User ID
     * @return array Recommended product IDs
     */
    private function get_recommended_products($user_id) {
        // Get user's previous orders
        $customer_orders = wc_get_orders([
            'customer_id' => $user_id,
            'status' => ['completed', 'processing']
        ]);

        $purchased_product_ids = [];
        $category_counts = [];

        // Analyze previous purchases
        foreach ($customer_orders as $order) {
            foreach ($order->get_items() as $item) {
                $product_id = $item->get_product_id();
                $purchased_product_ids[] = $product_id;

                // Count product categories
                $categories = get_the_terms($product_id, 'product_cat');
                if ($categories) {
                    foreach ($categories as $category) {
                        $category_counts[$category->term_id] =
                            isset($category_counts[$category->term_id])
                                ? $category_counts[$category->term_id] + 1
                                : 1;
                    }
                }
            }
        }

        // Sort categories by occurrence
        arsort($category_counts);
        $top_categories = array_slice(array_keys($category_counts), 0, 3);

        // Get recommended products from top categories, excluding previously purchased
        $recommended_products = get_posts([
            'post_type' => 'product',
            'posts_per_page' => 4,
            'tax_query' => [
                [
                    'taxonomy' => 'product_cat',
                    'field' => 'id',
                    'terms' => $top_categories,
                ]
            ],
            'post__not_in' => $purchased_product_ids
        ]);

        return wp_list_pluck($recommended_products, 'ID');
    }

    /**
     * Customize checkout fields based on cart contents
     *
     * @param array $fields Checkout fields
     * @return array Modified checkout fields
     */
    public function customize_checkout_fields($fields) {
        // Check cart contents for product type customization
        $cart = WC()->cart->get_cart();

        foreach ($cart as $cart_item_key => $cart_item) {
            $product = $cart_item['data'];

            // Example: Custom field for electronic products
            if ($this->is_electronic_product($product)) {
                $fields['order']['operating_system'] = [
                    'type' => 'select',
                    'label' => __('Operating System', 'woocommerce'),
                    'required' => true,
                    'options' => [
                        '' => __('Select Operating System', 'woocommerce'),
                        'windows' => __('Windows', 'woocommerce'),
                        'macos' => __('macOS', 'woocommerce'),
                        'linux' => __('Linux', 'woocommerce'),
                        'other' => __('Other', 'woocommerce')
                    ]
                ];
            }

            // Add more product-type specific customizations here
        }

        return $fields;
    }

    /**
     * Check if a product is electronic
     *
     * @param WC_Product $product Product object
     * @return bool
     */
    private function is_electronic_product( $product ) {
        // You can customize this logic based on your product categories or attributes
        $electronic_categories = ['electronics', 'computers', 'laptops'];

        $product_categories = get_the_terms($product->get_id(), 'product_cat');

        if ($product_categories) {
            foreach ($product_categories as $category) {
                if (in_array(strtolower($category->slug), $electronic_categories)) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Save custom checkout fields to order meta
     *
     * @param int $order_id Order ID
     */
    public function save_custom_checkout_fields($order_id) {
        if (isset($_POST['operating_system'])) {
            update_post_meta($order_id, '_operating_system', sanitize_text_field($_POST['operating_system']));
        }
    }

    /**
     * Enqueue styles for recommendations
     */
    public function enqueue_styles() {
        wp_enqueue_style( 'checkout-customizer' , plugin_dir_url(__FILE__) . 'assets/css/checkout-customizer.css');
    }
}

// Initialize the plugin
function initialize_woocommerce_checkout_customizer() {
    new WooCommerce_Checkout_Customizer();
}
add_action('plugins_loaded', 'initialize_woocommerce_checkout_customizer');