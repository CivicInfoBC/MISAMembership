<?php


	if (!defined('WHERE_PHP_INCLUDES')) define('WHERE_PHP_INCLUDES','../phpCode/');
	if (!defined('WHERE_TEMPLATES')) define('WHERE_TEMPLATES','./');


	require_once(WHERE_PHP_INCLUDES.'http_error_codes.php');
	require_once(WHERE_PHP_INCLUDES.'template.php');
	
	
	//	Constants for various HTTP status codes
	define('HTTP_BAD_REQUEST',400);
	define('HTTP_INTERNAL_SERVER_ERROR',500);
	define('HTTP_FILE_NOT_FOUND',404);
	define('HTTP_FORBIDDEN',403);


	function error ($param=null, $desc=null) {
	
		//	Headers that shall be added to
		//	outgoing e-mails
		$headers=array(
			'From' => 'error@civicinfo.bc.ca',
			'Content-Type' => 'text/html;charset=utf-8'
		);
		//	E-mail addresses of people who
		//	will receive error e-mails
		$recipients=array(
			'rleahy@rleahy.ca',
			'kvalley@civicinfo.bc.ca'
		);
		//	Values of param for which e-mails
		//	shall never be sent
		$filter=array(
			404
		);
		//	IP addresses for which e-mails shall
		//	not be sent if they are the client
		$filter_ips=array(
			'184.71.24.198'
		);
	
		$template=new Template(WHERE_TEMPLATES);
		
		//	Populate template with data
		
		if (!is_null($param)) {
		
			$template->error=$param;
		
			//	Determine if we were given an
			//	HTTP status code
			if (is_int($param)) {
			
				global $http_error_code_map;
				
				if (isset($http_error_code_map[$param])) {
				
					if (isset($http_error_code_map[$param]['title'])) {
					
						$template->error.=' '.$http_error_code_map[$param]['title'];
					
					}
					
					if (isset($http_error_code_map[$param]['desc'])) {
					
						$template->error_desc=$http_error_code_map[$param]['desc'];
					
					}
				
				}
			
			}
			
		}
		
		if (!is_null($desc)) $template->desc=$desc;
		
		//	Render page
		$template->Render('error.phtml');
		
		//	Filter -- see if we should
		//	send an e-mail
		if (
		
			//	Filter based on IP -- if the client
			//	has been specified in $filter_ips,
			//	do not send e-mail
			in_array(
				$_SERVER['REMOTE_ADDR'],
				$filter_ips,
				true
			) ||
			//	Filter based on error type -- if the
			//	error has been ignored, do not send
			//	e-mail
			in_array(
				$param,
				$filter,
				true
			)
		) exit();
		
		//	Send email
		
		//	Generate recipient list
		$to='';
		foreach ($recipients as $x) {
		
			if ($to!=='') $to.=',';
			
			$to.=$x;
		
		}
		
		//	Generate header list
		$headers_str='';
		foreach ($headers as $x=>$y) {
		
			if ($headers_str!=='') $headers_str.="\r\n";
			
			$headers_str.=$x.': '.$y;
		
		}
		
		//	Generate e-mail contents
		ob_start();
		$template->Render('error_email.phtml');
		$contents=ob_get_contents();
		ob_end_clean();
		
		//	Send email
		mb_send_mail(
			$to,
			$_SERVER['COMPUTERNAME'].': Error in script '.$_SERVER['SCRIPT_NAME'],
			$contents,
			$headers_str
		);
		
		//	END
		exit();
	
	}


?>