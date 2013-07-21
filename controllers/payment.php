<?php


	//	User must be an organization admin or
	//	sitewide admin to proceed
	if (!(
		($user->type==='admin') ||
		($user->type==='superuser')
	)) error(HTTP_FORBIDDEN);
	
	
	//	Prepare a template
	$template=new Template(WHERE_TEMPLATES);
	
	
	//	If user's organization is perpetual,
	//	if they do not have an organization,
	//	or if there are no years for which
	//	they may pay, they do not have to pay
	if (
		is_null($user->organization) ||
		$user->organization->perpetual ||
		(count($template->years=$user->organization->UnpaidYears())===0)
	) error(HTTP_BAD_REQUEST);


	require_once(WHERE_LOCAL_PHP_INCLUDES.'settings.php');
	
	
	//	Check to see if this is allowed
	if (!IsSettingTrue(GetSetting('dues_payment_permitted'))) {
	
		Render($template,'form_closed.phtml');
		
		exit();
	
	}
	
	
	$title='Membership Dues';
	
	
	//	If there's just one available membership
	//	year, then we just show the user the
	//	form for that year.
	//
	//	Otherwise we show them a form to select
	//	the year, and then we show them the form
	//	for that specific year.
	if (count($template->years)===1) {
	
		$template->year=$template->years[0];
		
	} else if (is_post()) {
	
		if (!isset($_POST['year'])) error(HTTP_BAD_REQUEST);
		
		foreach ($template->years as $x) {
		
			if ($x->id===$_POST['year']) {
			
				$template->year=$x;
				
				break;
			
			}
		
		}
		
		if (!isset($template->year)) error(HTTP_BAD_REQUEST);
		
	}
	
	
	if (isset($template->year)) {
	
		require_once(WHERE_PHP_INCLUDES.'e-xact.php');
		
		$template->payment=new EXact();
		
		$template->payment->type='Membership Dues';
		$template->payment->description='MISA BC Membership '.$template->year->name;
		$template->payment->first_name=$user->Name('F');
		$template->payment->last_name=$user->Name('L');
		$template->payment->company=$user->organization->name;
		$template->payment->email=$user->Name('e');
		$template->payment->db_table='payment';
		$template->payment->amount=$user->organization->GetPrice();
		$template->payment->id=$user->organization->id.'|'.$template->year->id;
		$template->payment->code='MISA-DUES';
		
		Render($template,'payment.phtml');
	
	} else {
	
		Render($template,'payment_year_select.phtml');
	
	}


?>