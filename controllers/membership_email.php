<?php


	//	User must be administrator
	if ($user->type!=='admin') error(HTTP_FORBIDDEN);
	
	
	require_once(WHERE_LOCAL_PHP_INCLUDES.'settings.php');
	
	
	$title='Membership Application Notifications';
	
	
	//	Was this a POST request?
	if (is_post()) {
	
		//	A post request will set administrations
		//	to receive e-mails as follews:
		//
		//	$_POST=array(1 => TRUE_STRING)
		//
		//	So scan
		
		$recipients=array();
		foreach ($_POST as $key=>$value) if (
			is_integer($key) &&
			($value===TRUE_STRING) &&
			!is_null($recipient=User::GetByID($key))
		) $recipients[]=$recipient->email;
		
		//	Update the database
		SetSetting(
			'membership_application_email',
			$recipients
		);
	
	}
	
	
	$template=new Template(WHERE_TEMPLATES);
	
	
	//	Get a list of sitewide admins
	$template->admins=User::GetPage(
		null,
		null,
		'`last_name` ASC,`first_name` ASC',
		User::GetTypeQuery('admin')
	);
	
	
	//	Get a list of admins currently set
	//	to receive notification e-mails
	$template->recipients=GetSetting('membership_application_email');
	
	
	Render($template,'membership_email.phtml');


?>