<?php 
    /**
     * Caching API intrigation
     *
     * @since  1.0.0
     * @package UnitekSyncData
     */

    if ( ! defined( 'ABSPATH' ) ) {
        exit; // Exit if accessed directly.
    }

    class Und_Cache_Api{

        public function usd_wp_rest_clear_cache( $division = null ) {

            $url = site_url('/wp-json/unitek_divisions-core/v1/purge-rest-cache');
        
            $curl = curl_init();
        
            curl_setopt_array($curl, array(
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'GET',
            ));
        
            $response = curl_exec($curl);
        
            if ($response === false) {
                do_action( 'unitek_log', 'Something wrong ' . $division . ' wp rest api cache is not cleared', 'alert');
            } else {
                do_action( 'unitek_log', 'The ' . $division . ' wp rest api cache has been cleared', 'alert');
            }
        
            curl_close($curl);
        }


        public function usd_nitropack_clear_cache( $division = null ) {

            if( ! $division ){
                return false;
            }

            $divisionValues = [
                'unitek-college' => [
                    'siteID' => 'NPcSlZqrCJzJITEGsuXNhCkoWmIrfjFP',
                    'siteSecret' => '6cfSI6P517wMCZfoIB52bXCsCKWHfIBrnkAR03GR0ul0OJwHXUawAuSStRInPRSc',
                    'urlToPurge' => 'https://www.unitekcollege.edu/',
                ],
                'brookline' => [
                    'siteID' => 'MDbIaQnHgvYrWdlkNlAApgMXaoLyMZQw',
                    'siteSecret' => '3xp3CmDUtGM2htQQ5KAT0A6WU3MzbuqibPdsVYSWmfGPF10T1K00hh7JoAQGUNmV',
                    'urlToPurge' => 'https://www.brooklinecollege.edu/',
                ],
                'eaglegate' => [
                    'siteID' => 'NjZJIONKWXmglJtuZWYfLRXrdMKSdTsmW',
                    'siteSecret' => '5xbgJ3vpGhTLgwWyyatGBZzCX3quJMZlL6kI9LwBwoaGiZmpS26frE7NhUx6Rjpt',
                    'urlToPurge' => 'https://www.eaglegatecollege.edu/',
                ],
                'provo-college' => [
                    'siteID' => 'hNJckCstFVQjUckKaelcVKoVJrzdTVkW',
                    'siteSecret' => 'XAVCZXP6QwuqqOZbePUcloJvJBv9uWWbbqC2OhEj0Wzd7WkMBGxYAh4jtKNF2ZCV',
                    'urlToPurge' => 'https://www.provocollege.edu/',
                ]
            ];

            if ( isset($divisionValues[$division]) ) {
                $siteID = $divisionValues[$division]['siteID'];
                $siteSecret = $divisionValues[$division]['siteSecret'];
                $urlToPurge = $divisionValues[$division]['urlToPurge'];
            }

            $apiEndpoint = "https://api.getnitropack.com/cache/purge/$siteID";
            
            $signature = hash_hmac('sha512', "/cache/purge/$siteID||url:$urlToPurge", $siteSecret);
            
            $headers = [
                "X-Nitro-Signature: $signature",
                "Content-Type: application/x-www-form-urlencoded"
            ];
            
            $postData = http_build_query(['url' => $urlToPurge]);
            
            $ch = curl_init();
            
            curl_setopt($ch, CURLOPT_URL, $apiEndpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            
            curl_close($ch);
            
            if ($httpCode == 200) {
                do_action( 'unitek_log', 'The ' . $division . ' nitropack cache has been cleared', 'alert');
            } else {
                do_action( 'unitek_log', 'Something wrong ' . $division . ' nitropack cache is not cleared', 'alert');
            }
        }


        public function usd_cloudflare_clear_cache( $zones = null, $division = null ){

            if( is_null( $zones ) ){
                return false;
            }
        
            $curl = curl_init();
        
            curl_setopt_array($curl, array(
                CURLOPT_URL => 'https://api.cloudflare.com/client/v4/zones/'.$zones.'/purge_cache',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => '',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_TIMEOUT => 0,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                CURLOPT_CUSTOMREQUEST => 'POST',
                CURLOPT_POSTFIELDS =>'{"purge_everything": true}',
                CURLOPT_HTTPHEADER => array(
                    'Content-Type: application/json',
                    'Authorization: Bearer zbgV-PQUSy_RfQRLHistuaVJa0Y6KEjx8XyVgdkY'
                ),
            ));
        
            $response = curl_exec($curl);
            curl_close($curl);
            
            $output = json_decode($response, true );
        
            if( false !== $output['success'] ){
                do_action( 'unitek_log', 'The ' . $division . ' cloudflare cache has been cleared', 'alert');
            }else{
                do_action( 'unitek_log', 'Something wrong ' . $division . ' cache is not cleared', 'alert');
            }
        }
        
        
         

    }