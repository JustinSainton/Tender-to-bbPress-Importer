<?php

/* Uses WordPress-specific HTTP APIs to access the Tender API */
class WP_Tender_API {

	public static __construct() {

	}

	public static function http_request_headers( $request, $url ) {
		
		if ( false === strpos( $url, self::get_tender_base() ) ) {
			return $request;
		}

		if ( ! is_array( $request['headers'] ) ) {
			$request['headers'] = array();
		}

		$request['headers']['X-Tender-Auth'] = self::get_api_token();
		$request['headers']['Accept']        = 'application/vnd.tender-v1+json';

		return $request;
	}

	public static function get_api_token() {

		/* Likely the easiest way for folks to go, just add define( 'TENDER_API', 'enter API key here' ); to wp-config.php */
		if ( defined( 'TENDER_API_TOKEN' ) ) {
			$token = TENDER_API_TOKEN;
		} else {
			/* We could add a UI for this at some point...but I really hate the Settings API. */
			$token = ''; 
		}

		return apply_filters( 'wp_tender_api_token', $token );
	}

	public static function get_tender_base() {

		/* Likely the easiest way for folks to go, just add define( 'TENDER_API_BASE', 'https://api.tenderapp.com/okaythemes/' ); to wp-config.php */
		if ( defined( 'TENDER_API_BASE' ) ) {
			$token = TENDER_API_BASE;
		} else {
			/* We could add a UI for this at some point...but I really hate the Settings API. */
			$url = ''; 
		}
		
		return apply_filters( 'wp_tender_api_base', $url );
	}

	private static function _request( $base = '/' ) {

	}
}