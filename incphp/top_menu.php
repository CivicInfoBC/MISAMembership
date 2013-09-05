<?php


	def('TOP_MENU_URL','http://test.misa.bc.ca/users/?action=get_top_menu');


	function GetTopMenu () {
	
		if (($curl=curl_init())===false) throw new Exception('Could not create cURL handle');
		
		$curl_opts=array(
			CURLOPT_URL => TOP_MENU_URL,
			CURLOPT_HEADER => false,
			CURLOPT_RETURNTRANSFER => true
		);
		
		if (
			defined('TOP_MENU_USERNAME') &&
			defined('TOP_MENU_PASSWORD')
		) $curl_opts[CURLOPT_USERPWD]=TOP_MENU_USERNAME.':'.TOP_MENU_PASSWORD;
		
		if (
			!curl_setopt_array(
				$curl,
				$curl_opts
			) ||
			(($result=curl_exec($curl))===false)
		) throw new Exception(curl_error($curl));
		
		if (is_null($json=json_decode($result))) throw new Exception('Could not decode JSON: '.$result);
		
		if (!(
			isset($json->links) &&
			is_array($json->links)
		)) goto invalid;
		
		$retr=array();
		
		foreach ($json->links as $x) {
		
			if (!(
				is_object($x) &&
				isset($x->label) &&
				is_string($x->label) &&
				isset($x->url) &&
				is_string($x->url)
			)) goto invalid;
			
			$retr[$x->label]=$x->url;
			
		}
		
		return $retr;
		
		invalid:throw new Exception('JSON structure invalid: '.$result);
	
	}


?>