<?php

/**
 * Check that php file is not accessed directly
 */
defined( 'ABSPATH' ) or die( 'No script kiddies please!' ); 


class FuturelyticsFunctions {
    /**
     * Create shop autentitication file with generated shop ID
     */
    function get_shop_id($key = false){

        // generate shop id if not already generated
        $shop_id_meta = get_option('futurelytics-shop-ID');
        if(empty($shop_id_meta)){
            $shopId = $this->generate_shop_id();
            add_option('futurelytics-shop-ID', $shopId );
        }

        //return shop id
        if($key){
            return get_option('futurelytics-shop-ID');
        }
    }
    
    /**
    * Generate random unique shop ID
    *
    * @return string - generated shop ID
    */
    function generate_shop_id(){

        $shop_id = '';
        $date = new DateTime();

        $str = get_site_url().'/'.$date->getTimestamp();
        $shop_id = base64_encode($str);

        return $shop_id;
    }
}