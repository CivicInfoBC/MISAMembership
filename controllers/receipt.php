<?php


	//	Obtain input
	//
	//	I.e. payment ID
	$called=true;	//	Whether this controllor was invoked by another controller
	if (!isset($payment_id)) {
	
		$called=false;
	
		if (!(
			($user->type==='superuser') ||
			($user->type==='admin')
		)) error(HTTP_FORBIDDEN);
		
		if (!(
			!is_null($request->GetArg(0)) &&
			is_numeric($request->GetArg(0)) &&
			(($payment_id=intval($request->GetArg(0)))==floatval($request->GetArg(0)))
		)) error(HTTP_BAD_REQUEST);
	
	}
	
	
	//	Get database access
	$conn=$dependencies['MISADBConn'];
	
	
	//	Attempt to obtain that payment from
	//	the database
	if (($query=$conn->query(
		sprintf(
			'SELECT * FROM `payment` WHERE `id`=\'%s\'',
			$conn->real_escape_string($payment_id)
		)
	))===false) throw new Exception($conn->error);
	
	if ($query->num_rows===0) error(HTTP_BAD_REQUEST);
	
	$template=new Template(WHERE_TEMPLATES);
	
	$template->row=new MySQLRow($query);
	
	//	If the payment-in-question hasn't been
	//	paid, that's an error -- there's no receipt
	//	to generate because there's been no payment
	if (!$template->row['paid']->GetValue()) error(HTTP_BAD_REQUEST);
	
	//	Permissions check:
	//
	//	Only administrators or superusers of the
	//	organization-in-question can view receipts
	if (!(
		$called ||
		($user->type==='admin') ||
		(
			!is_null($user->organization) &&
			($user->organization->id===$template->row['org_id']->GetValue())
		)
	)) error(HTTP_FORBIDDEN);
	
	
	//	Get information about the organization
	$template->organization=Organization::GetByID($template->row['org_id']->GetValue());
	if (is_null($template->organization)) error(HTTP_BAD_REQUEST);
	
	
	//	Get information about the membership year
	//	(if there is one)
	if (!is_null($template->row['membership_year_id']->GetValue())) {
		
		if (($query=$conn->query(
			sprintf(
				'SELECT * FROM `membership_years` WHERE `id`=\'%s\'',
				$conn->real_escape_string($template->row['membership_year_id']->GetValue())
			)
		))===false) throw new Exception($conn->query);
		
		if ($query->num_rows===0) error(HTTP_BAD_REQUEST);
		
		$template->membership_year=new MySQLRow($query);
		
	}
	
	
	//	Get information about the membership type
	//	(if there is one)
	if (!is_null($template->row['membership_type_id']->GetValue())) {
	
		$template->membership_type=Organization::GetType(
			$template->row['membership_type_id']->GetValue()
		);
	
	}
	
	
	//	Break the total/subtotal/tax out (if necessary)
	$template->total=$template->row['total']->GetValue();
	$template->tax=$template->total-($template->total/(1+GST_RATE));
	$template->subtotal=$template->total-$template->tax;
	
	
	//	If it's a POST request, it's an e-xact
	//	postback, and we should send an e-mail
	if (is_post()) {
	
		//	Make sure the x_email field is set -- we'll need it
		//	to send the e-mail
		if (!isset($_POST['x_email']) || ($_POST['x_email']==='')) error(HTTP_BAD_REQUEST);
		
		require_once(WHERE_PHP_INCLUDES.'mail.php');
		require_once(WHERE_LOCAL_PHP_INCLUDES.'settings.php');
		
		$email=new EMail();
		$email->to=$_POST['x_email'];
		$email->is_html=true;
		$email->subject='Membership Dues Payment';
		$email->from=GetSettingValue(GetSetting('mail_from'));
		$template->email=true;
		$email->Send(
			$template,
			'receipt.phtml'
		);
	
	//	Otherwise we just render the receipt
	} else {
	
		$template->email=false;
		$template->Render('receipt.phtml');
	
	}


?>