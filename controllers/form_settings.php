<?php


	//	User must be administrator
	if ($user->type!=='admin') error(HTTP_FORBIDDEN);
	
	
	require_once(WHERE_LOCAL_PHP_INCLUDES.'settings.php');
	require_once(WHERE_PHP_INCLUDES.'form.php');
	
	
	$title='Form Settings';
	
	
	//	Get the current values from
	//	the database
	$payment=IsSettingTrue(GetSetting('dues_payment_permitted'));
	$membership=IsSettingTrue(GetSetting('membership_application_open'));
	
	
	//	Build the form
	$form=new Form(
		'',
		'POST',
		array(
			new CheckBoxFormElement(
				'payment',
				$payment,
				'Membership Dues Payment Enabled'
			),
			new CheckBoxFormElement(
				'membership',
				$membership,
				'Membership Applications Enabled'
			),
			new SubmitFormElement(
				'Submit'
			)
		)
	);
	
	
	//	Handle POST requests
	if (is_post()) {
	
		$form->Populate();
		
		if (!$form->Verify()) error(HTTP_BAD_REQUEST);
		
		$arr=$form->GetValues();
		
		SetSetting(
			'dues_payment_permitted',
			(isset($arr['payment']) && $arr['payment']) ? TRUE_STRING : null
		);
		
		SetSetting(
			'membership_application_open',
			(isset($arr['membership']) && $arr['membership']) ? TRUE_STRING : null
		);
	
	}
	
	
	$template=new Template(WHERE_TEMPLATES);
	$template->form=$form;
	Render($template,'form.phtml');


?>