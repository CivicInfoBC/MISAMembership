<?php


	$email_to=array(
		'rleahy@rleahy.ca', 'kvalley@civicinfo.bc.ca'
	);
	$email_subject='ERROR ON PAGE '.$_SERVER['PHP_SELF'];
	$email_from='error@civicinfo.bc.ca';
	$email_filter=array();
	$email_ip_bypass=array(
		'184.71.24.198'
	);


	//	Constants for various HTTP status codes
	define('HTTP_BAD_REQUEST',400);
	define('HTTP_INTERNAL_SERVER_ERROR',500);
	define('HTTP_FILE_NOT_FOUND',404);


	function error ($param=null, $desc=null) {
	
		function html_newline_convert ($html) {
		
			return sprintf(
				'<p>%s</p>',
				preg_replace(
					'/\\r/',
					'',
					preg_replace(
						'/\\n/',
						'</p><p>',
						$html
					)
				)
			);
		
		}
	
		$http_error_code=is_int($param);
		
		if ($http_error_code) {
		
			global $WherePHPIncludes;
		
			require_once(
				(
					!isset($WherePHPIncludes)
						?	'./'
						:	$WherePHPIncludes
				).
				'http_error_codes.php'
			);
		
		}
		
		$http_or_https=htmlspecialchars((
			!isset($_SERVER['HTTPS']) ||
			($_SERVER['HTTPS']=='on')
		) ? 'https' : 'http');
	
?><!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">

<html xmlns="http://www.w3.org/1999/xhtml">

	<head>
	
		<meta http-equiv="X-UA-Compatible" content="IE=9" />
		
		<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	
		<title>CivicInfo BC - Error</title>
		
		<link rel="stylesheet" type="text/css" href="<?php	echo($http_or_https);	?>://www.civicinfo.bc.ca/style/reset.css" />
		<link rel="stylesheet" type="text/css" href="<?php	echo($http_or_https);	?>://www.civicinfo.bc.ca/style/error.css" />
	
	</head>
	
	<body>
	
		<img src="<?php	echo($http_or_https);	?>://www.civicinfo.bc.ca/images/civicinfo300w_transparent2.png" />
		
		<div class="informational"><p>An error was encountered while processing your request!</p><p>See below for details:</p></div>
		
		<?php
		
			//	If there's nothing coherent to output given the parameters the function was called with
			if (
				!isset($param) ||
				(trim($param)=='') ||
				(
					$http_error_code &&
					!isset($http_error_code_map[$param])
				)
			):
			
		?>
		
		<div class="title">ERROR</div>
		
		<?php
		
			else:
				
				if ($http_error_code):
		
		?>
		
		<div class="title"><?php	echo(htmlspecialchars($param));	?> <?php	echo(htmlspecialchars($http_error_code_map[$param]['title']));	?></div>
		
		<?php
		
					if (isset($http_error_code_map[$param]['desc'])):
					
		?>
		
		<div><?php	echo(html_newline_convert(htmlspecialchars($http_error_code_map[$param]['desc'])));	?></div>
		
		<?php
		
					endif;
					
				else:
		
		?>
		
		<div class="title"><?php	echo(htmlspecialchars($param));	?></div>
		
		<?php
		
				endif;
				
			endif;
			
			if (($desc!=null) && (trim($desc)!='')):
			
		?>
		
		<div class="informational">The following information about your error was supplied:</div>
		
		<div><?php	echo(html_newline_convert(htmlspecialchars($desc)));	?></div>
		
		<?php	endif;	?>
	
	</body>

</html><?php

		//	Prepare e-mail
		
		global $email_filter;
		foreach ($email_filter as $x) {
		
			if ($param==$x) exit();
		
		}
		
		global $email_ip_bypass;
		foreach ($email_ip_bypass as $x) {
		
			if ($_SERVER['REMOTE_ADDR']===$x) exit();
		
		}
		
		ob_start();
		
		if (!is_null($param)):
		
?>The following status was specified:

<?php

		echo($param);

		if (
			$http_error_code &&
			isset($http_error_code_map[$param]) &&
			is_array($http_error_code_map[$param]) &&
			isset($http_error_code_map[$param]['desc']) &&
			isset($http_error_code_map[$param]['title'])
		):
			
?> - <?php	echo($http_error_code_map[$param]['title']);	?> - <?php	echo($http_error_code_map[$param]['desc']);

		endif;
		
?>


<?php
		
		endif;

		if (!is_null($desc)):
		
?>The following description was given:

<?php	echo($desc);	?>


<?php

		endif;
		
		foreach (array('$_GET'=>$_GET,'$_POST'=>$_POST,'$_COOKIE'=>$_COOKIE,'$_SERVER'=>$_SERVER) as $x=>$y):
		
			echo($x.'=');
			var_dump($y);
			
?>


<?php
		
		endforeach;
		
		global $email_to;
		global $email_subject;
		global $email_from;
		
		$email_to_string='';
		$first=true;
		foreach ($email_to as $x) {
		
			if ($first) $first=false;
			else $email_to_string.=',';
			
			$email_to_string.=$x;
		
		}
		
		//	Send message
		mb_send_mail(
			$email_to_string,
			$email_subject,
			ob_get_contents(),
			'From: '.$email_from."\r\n".'Reply-To: '.$email_from."\r\n".'X-Mailer: PHP/'.phpversion()
		);
		
		ob_end_clean();

		//	ABORT
		exit();
	
	}
	
?>