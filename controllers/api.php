<?php


	//	Response type is always JSON
	header('Content-Type:application/json');


	//	Special error handling function
	//	for API functions that doesn't have
	//	the GUI that goes along with the
	//	regular error function
	function api_error ($errno, $msg=null) {
	
		if (is_integer($errno)) {
		
			global $http_error_code_map;
			
			$http_header='HTTP/1.1 '.$errno;
			
			if (
				isset($http_error_code_map[$errno]) &&
				isset($http_error_code_map[$errno]['title'])
			) $http_header.=' '.$http_error_code_map[$errno]['title'];
		
			header($http_header);
		
		}
		
		if (isset($msg)) {
		
			echo(
				json_encode(
					array(
						'error' => $msg
					)
				)
			);
		
		}
		
		//	Die
		exit();
	
	}
	
	
	try {


		//	All API requests must be POST
		//	and must send JSON
		if (!(
			($_SERVER['REQUEST_METHOD']==='POST') &&
			($_SERVER['CONTENT_TYPE']==='application/json')
		)) api_error(HTTP_BAD_REQUEST);
		
		//	Get JSON request
		$api_request=json_decode(
			file_get_contents(
				'php://input'
			)
		);
		
		//	JSON request is, at bare minimum:
		//
		//	{
		//		"action":	<Desired API action>
		//	}
		//
		//	So we verify this structure.
		if (
			//	JSON could not be parsed at
			//	all
			is_null($api_request) ||
			!(
				is_object($api_request) &&
				isset($api_request->action)
			)
		) api_error(HTTP_BAD_REQUEST);
		
		//	The only request that we may route
		//	to at this point is the "check"
		//	action, which simply checks to see
		//	if a user with a given username or
		//	e-mail exists
		//
		//	All other actions require an API
		//	key.
		if ($api_request->action==='check') {
		
			require(WHERE_CONTROLLERS.'api_check.php');
		
		} else {
		
			//	JSON request with API key is, at bare
			//	minimum:
			//
			//	{
			//		"action":	<Desired API action>,
			//		"api_key":	<API key for API consumer>
			//	}
			//
			//	So we check to ensure proper structure.
			//
			//	We also setup routes which map an action
			//	to a controller which shall handle it.
			
			$api_routes=array(
				'login' => 'api_login.php',
				'logout' => 'api_login.php',
				'query' => 'api_query.php'
			);
			
			//	Verify recognized request
			if (!in_array(
				$api_request->action,
				array_keys($api_routes),
				true
			)) api_error(HTTP_BAD_REQUEST);
			
			//	Check to ensure this API key is
			//	authorized
			$conn=$dependencies['MISADBConn'];
			
			$query=$conn->query(
				sprintf(
					'SELECT
						`organizations`.*
					FROM
						`api_keys`
						LEFT OUTER JOIN `organizations`
						ON `organizations`.`id`=`api_keys`.`org_id`',
					$conn->real_escape_string($api_request->api_key)
				)
			);
			
			//	Throw on error
			if ($query===false) throw new Exception($conn->error);
			
			//	If there are no matching rows,
			//	the API key doesn't exist and therefore
			//	the consumer is not authorized
			if ($query->num_rows===0) api_error(HTTP_FORBIDDEN);
			
			//	Consumer is authorized, load
			//	and store data
			$api_consumer=new MySQLRow($query);
			
			//	Route
			require(WHERE_CONTROLLERS.$api_routes[$api_request->action]);
			
		}
		
		//	If the controller specified a
		//	result, serialize it to JSON,
		//	otherwise do nothing
		if (isset($api_result)) echo(json_encode($api_result));
		
		
	} catch (Exception $e) {
	
		if (DEBUG) api_error(HTTP_INTERNAL_SERVER_ERROR,$e->getMessage());
		else api_error(HTTP_INTERNAL_SERVER_ERROR);
	
	}
	

?>