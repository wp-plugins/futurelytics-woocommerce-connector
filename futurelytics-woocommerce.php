<?php
/**
 * Plugin Name: Futurelytics WooCommerce plugin
 * Plugin URI: http://www.futurelytics.com
 * Description: Futurelytics is easy to use ecommerce retention automation platform.
 * Version: 1.0.0
 * Author: Futurelytics
 * Author URI: http://www.futurelytics.com
 * Developer: Petr Chudanic
 * Text Domain: futurelytics-woocommerce
 * Domain Path: /languages
 *
 * License: GNU General Public License v3.0
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 */

/**
 * Check that php file is not accessed directly
 */
defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

include_once 'functions.php';

/**
 * Check if WooCommerce is active
 **/
if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {
    
    class Futurelytics {
        
        var $plugin_path;
        var $functions;
        var $api_resource;
        
        /**
         * Futurelytics plugin main class constructor
         */
        public function __construct(){
            $this->functions = new FuturelyticsFunctions();
            $this->plugin_path = plugin_dir_path(__FILE__);
            register_activation_hook( __FILE__, array( $this, 'flush' ) );
            register_activation_hook( __FILE__, array( $this, 'activate_plugin' ) );
            add_action( 'init', array( $this, 'init') );
            add_action( 'init', array($this, 'app_output_buffer') );
            add_filter( 'query_vars', array( $this, 'query_vars') );
            add_action( 'parse_request', array( $this, 'parse_request') );
            add_action('admin_menu', array( $this, 'add_menu_item') );
        }

        /**
         * Flush rewrite rules
         */
        public function flush(){
            $permalink_structure = get_option('permalink_structure');
            
            if ( empty($permalink_structure) ) {
                echo "Futurelytics WooCommerce plugin requires permalinks to work. "
                . "\nYou can set them in administration."
                . "\nJust go to Settings -> Permalinks and select other then Default option based on your preference.";
                die();
            }
            
            $this->init();
            flush_rewrite_rules();
        }

        /**
         * Function hooked to plugin init
         */
        public function init(){
            
            add_rewrite_rule(
                'futurelytics-api\/([a-zA-z0-9_]+)\/?(?:[?]((?:[^=]+=[^&]+[&])*(?:[^=]+=[^&]+)))?$',
                'index.php?fl_api_resource=$matches[1]'
                    . '&$matches[2]',
                'top'
            );
        }

        /**
         * Function to hook to query_vars filter. 
         * @param array $query_vars
         * @return string
         */
        public function query_vars( $query_vars ){
            $query_vars[] = 'fl_api_resource';
            $query_vars[] = 'start_time';
            $query_vars[] = 'end_time';
            $query_vars[] = 'paged';
            $query_vars[] = 'limit';
            $query_vars[] = 'shopid';
            $query_vars[] = 'modified_from';
            $query_vars[] = 'modified_to';
            return $query_vars;
        }

        /**
         * Function to hook to parse_request action in order to redirect 
         * API requests to futurelytics-api.php
         * 
         * @param type $wp
         * @return type
         */
        public function parse_request( &$wp ){
            if ( array_key_exists( 'fl_api_resource', $wp->query_vars ) ){
                include $this->plugin_path . 'futurelytics-api.php';
                exit();
            }
        }
        
        /**
         * Function to be called upon plugin activation
         */
        public function activate_plugin() {
            if ( version_compare( phpversion(), FUTURELYTICS_MIN_PHP_VER, '<' ) ) {
                die( sprintf( "The minimum PHP version required for Futurelytics is %s", FUTURELYTICS_MIN_PHP_VER ) );
            }
            $this->functions->get_shop_id();
        }
        
        /**
         * Function to be called after the basic admin panel menu structure is in place
         */
        public function add_menu_item() {
            add_submenu_page('woocommerce', "Futurelytics", "Futurelytics", 'manage_woocommerce', 'futurelytics', array( $this, 'fl_redirect' ));
        }
        
        /**
         * Buffer output to allow for redirect before page is loaded
         */
        function app_output_buffer() {
            ob_start();
        } // soi_output_buffer
        
        /**
         * Function to be called upon selecting Futurelytics from WooCommerce admin menu
         */
        public function fl_redirect() {
            $user_from_email = get_user_by('email', get_option('admin_email'));
	    $details = get_userdata($user_from_email->ID);
            
            wp_redirect( 
                     'https://secure.futurelytics.com/client/?'
//                    'http://localhost:8888/client/?'
//                   'https://futurelytics-dev.appspot.com/client/?'
                    . 'shopname=' . urlencode(get_bloginfo())
                    . '&shopid=' . $this->functions->get_shop_id(true) 
                    . '&admin_email=' . urlencode(get_option('admin_email'))
                    . '&first_name=' . urlencode($details->user_firstname)
                    . '&last_name=' . urlencode($details->user_lastname)
                    . '&api_url=' .  urlencode(site_url() . "/futurelytics-api")
                    . "#apps/woocommerce"); 
            exit;
        }
    }
    
    define( "FUTURELYTICS_MIN_PHP_VER", '5.3.0' );

    $wc_fl_plg = new Futurelytics();

}