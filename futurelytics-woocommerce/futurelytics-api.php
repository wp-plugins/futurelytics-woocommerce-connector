<?php

/**
 * Check that php file is not accessed directly
 */
defined( 'ABSPATH' ) or die( 'No script kiddies please!' ); 

include_once 'functions.php';

class FuturelyticsApi {
    
    var $functions;
    
    public function __construct(){
        $this->functions = new FuturelyticsFunctions();
    }
    
    public function verify_shop_id($shopid) {
        if ($this->functions->get_shop_id(true) == $shopid) {
            return true;
        }
        return false;
    }
    
    private function get_product_categories($product){
        $categories = [];
        
        $product_cats = wp_get_post_terms( $product->id, 'product_cat' );

        if ( $product_cats && ! is_wp_error ( $product_cats ) ){

            foreach ( $product_cats as $category) {
                $categories[] = $category->name;
            }
        }
        
        if ( count($categories) == 0 ) {
            $categories[] = "Unknown";
        }
        
        return $categories;
    }
    
    private function get_order_items($order) {
        $items = $order->get_items();
        $products = [];
        
        foreach ($items as $item) {
            $line_item = [];
            
            $line_item["product_id"] = $item["product_id"];
            $line_item["product_id"] = $item["product_id"];
            $line_item["qty"] = $item["qty"];
            $line_item["line_total"] = $item["line_total"];
            $line_item["line_tax"] = $item["line_tax"];
            
            $products[] =(object)$line_item;
        }
        
        return $products;
    }
    
    public function getOrdersCount($modified_from, $modified_to) {
        $args = array(
            'posts_per_page'   => 1,         
            'post_type'        => 'shop_order',
            'post_status'      => array( 'wc-completed' ),
            'suppress_filters' => true ,
            'date_query'       => array(
		array(
			'after'     => $modified_from,
			'before'    => $modified_to,
			'inclusive' => true,
		),
            )
        );
        
        
        // The Query
        $the_query = new WP_Query( $args );
        
        echo json_encode((object)['orders_count' => $the_query->found_posts]);
    }
    
    /**
     * Method to return orders
     * 
     * @param number $limit - number of products per page
     * @param number $paged - page number
     * @param Date string - $modified_from
     * @param Date string - $modified_to
     */
    public function get_orders($limit, $paged, $modified_from, $modified_to) {
        $orders = [];

        $args = array(
            'posts_per_page'   => $limit,
            'orderby'          => 'post_date',
            'order'            => 'DESC',          
            'post_type'        => 'shop_order',
            'post_status'      => array( 'wc-completed' ),
            'paged'            => $paged,
            'suppress_filters' => true ,
            'date_query'       => array(
		array(
			'after'     => $modified_from,
			'before'    => $modified_to,
			'inclusive' => true,
		),
            )
        );
        
        
        // The Query
        $the_query = new WP_Query( $args );

        // The Loop
        if ( $the_query->have_posts() ) {
            while ( $the_query->have_posts() ) {
                $the_query->the_post();
                
                $order = new WC_Order( $the_query->post->ID );
                $user = $order->get_user();
                
                $orders[] = array(
                    'Id'                => $order->id,
                    'TransactionDate'   => (new DateTime($order->order_date))->getTimestamp(),
                    'Amount'            => $order->get_total(),
                    'Currency'          => get_woocommerce_currency(),
                    'OrderItems'        => $this->get_order_items($order),
                    'CustomerAccountId' => $order->get_user_id(),
                    'Email'             => $order->billing_email,
                    'FirstName'         => $order->billing_first_name,
                    'LastName'          => $order->billing_last_name,
                    'Country'           => $order->billing_country,
                    'City'              => $order->billing_city,
                    'Phone'             => $order->billing_phone,
                    'RegistrationDate'  => (new DateTime($user->user_registered))->getTimestamp(),
                    'LastLoggedDate'    => null,
                    'Age'               => null,
                    'Sex'               => null,
                    'AcceptsMarketing'  => null,
                    'StoreName'         => null
                );
            }
        } else {
            // no posts found
        }
        
        $output = (object)[];
        
        $output->data = $orders;
        
        echo json_encode($output);
    }
    
    /**
     * Method to return products
     * 
     * @param type $limit - number of products per page
     * @param type $paged - page number
     * @param Date string - $modified_from
     * @param Date string - $modified_to 
     */
    public function getProducts($limit, $paged, $modified_from, $modified_to) {
        
        $products = [];

        $args = array(
            'posts_per_page'   => $limit,
            'orderby'          => 'post_date',
            'order'            => 'DESC',          
            'post_type'        => 'product',
            'paged'            => $paged,
            'suppress_filters' => true,
            'date_query'       => array(
		array(
			'after'     => $modified_from,
			'before'    => $modified_to,
			'inclusive' => true,
		),
            )
        );
        
        
        // The Query
        $the_query = new WP_Query( $args );

        // The Loop
        if ( $the_query->have_posts() ) {
            while ( $the_query->have_posts() ) {
                $the_query->the_post();
                
                $product = new WC_Product( $the_query->post->ID );
                
                $products[] = array(
                    'Id'            => $product->id,
                    'Title'         => $product->get_title(),
                    'Description'   => apply_filters( 'woocommerce_short_description', $the_query->post->post_excerpt ),
                    'Price'         => $product->get_price(),
                    'ProductUrl'    => $product->get_permalink(),
                    'ImageUrl'      => preg_match('/src=["\']([^"\']+)["\']/', $product->get_image(), $matches, PREG_OFFSET_CAPTURE) ? $matches[1][0] : "",
                    'Categories'    => $this->get_product_categories($product),
                    'CrossSellIds'  => $product->get_cross_sells(),
                    'UpSellIds'     => $product->get_upsells(),
                    'Sku'           => $product->get_sku(),
                    'Ean'           => null,
                    'StockQuantity' => $product->get_stock_quantity()
                );
                
            }
        } else {
            // no posts found
        }
        
        $output = (object)[];
        
        $output->data = $products;
        
        echo json_encode($output);
    }
    
    /**
     * Method to return products count
     * 
     * @param Date string - $modified_from
     * @param Date string - $modified_to 
     */
    public function getProductsCount($modified_from, $modified_to) {

        $args = array(         
            'post_type'        => 'product',
            'suppress_filters' => true,
            'date_query'       => array(
		array(
			'after'     => $modified_from,
			'before'    => $modified_to,
			'inclusive' => true,
		),
            )
        );
        
        
        // The Query
        $the_query = new WP_Query( $args );

        echo json_encode((object)['products_count' => $the_query->found_posts]);
    }
}

global $wp;

$api = new FuturelyticsApi();

$resource = $wp->query_vars['fl_api_resource'];
if (isset($wp->query_vars['limit'])) {
    $limit = $wp->query_vars['limit'];
}
if (isset($wp->query_vars['paged'])) {
    $paged = $wp->query_vars['paged'];
}
if (isset($wp->query_vars['modified_from'])) {
    $modified_from = $wp->query_vars['modified_from'];
}
if (isset($wp->query_vars['modified_to'])) {
    $modified_to = $wp->query_vars['modified_to'];
}
if (!isset($wp->query_vars['shopid'])) {
    status_header(403); 
    echo 'Missing shopid parameter';
    die();
} else {
    $shopid = $wp->query_vars['shopid'];
    if (!$api->verify_shop_id($shopid)) {
        status_header(403);       
        die();
    }
}

if (!isset($limit) || !is_numeric($limit)) {
    $limit = 200;
}

if (!isset($paged) || !is_numeric($paged)) {
    $paged = 1;
}

if (!isset($modified_from)) {
    $modified_from = "1970-01-01 00:00";
}

if (!isset($modified_to)) {
    $modified_to = date("Y-m-d H:i", time());
}

//Set content type header
header("Content-type: application/json");

switch ($resource) {
    case 'orders':
        $api->get_orders($limit, $paged, $modified_from, $modified_to);

        break;
    
    case 'orders_count':
        $api->getOrdersCount($modified_from, $modified_to);
        
        break;

    case 'products':
        $api->getProducts($limit, $paged, $modified_from, $modified_to);
        
        break;
    
    case 'products_count':
        $api->getProductsCount($modified_from, $modified_to);
        
        break;
    
    case 'verify':
        status_header(200);       
        die();
    
    default:
        break;
}

