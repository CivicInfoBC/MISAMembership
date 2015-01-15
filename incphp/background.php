<?php


	def('BACKGROUND_URL','https://www.misa.bc.ca/members/?action=get_header_image');
	
	
	function GetBackgroundURL () {
	
		if (($curl=curl_init())===false) throw new Exception('Could not create cURL handle');
		
		$curl_opts=array(
			CURLOPT_URL => BACKGROUND_URL,
			CURLOPT_HEADER => false,
			CURLOPT_RETURNTRANSFER => true/*,
			CURLOPT_SSL_VERIFYPEER => false*/
		);
		
		if (
			defined('BACKGROUND_USERNAME') &&
			defined('BACKGROUND_PASSWORD')
		) $curl_opts[CURLOPT_USERPWD]=BACKGROUND_USERNAME.':'.BACKGROUND_PASSWORD;
		
		if (
			!curl_setopt_array(
				$curl,
				$curl_opts
			) ||
			(($result=curl_exec($curl))===false)
		) throw new Exception(curl_error($curl));
		
		return $result;
	
	}


?>