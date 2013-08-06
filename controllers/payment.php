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
	
		$amount=$user->organization->GetPrice();
	
		//	Insert row into the database
		$conn=$dependencies['MISADBConn'];
		if (
			($conn->query('LOCK TABLES `payment` WRITE')===false) ||
			(($query=$conn->query(
				sprintf(
					'SELECT * FROM `payment` WHERE `org_id`=\'%s\' AND `membership_year_id`=\'%s\'',
					$conn->real_escape_string($user->organization->id),
					$conn->real_escape_string($template->year->id)
				)
			))===false)
		) throw new Exception($conn->error);
		
		//	Insert a row if none exists
		if ($query->num_rows===0) {
		
			if ($conn->query(
				sprintf(
					'INSERT INTO `payment` (
						`org_id`,
						`membership_type_id`,
						`membership_year_id`,
						`type`,
						`created`,
						`subtotal`,
						`tax`,
						`total`
					) VALUES (
						\'%s\',
						\'%s\',
						\'%s\',
						\'%s\',
						NOW(),
						\'%s\',
						\'%s\',
						\'%s\'
					)',
					$conn->real_escape_string($user->organization->id),
					$conn->real_escape_string($user->organization->membership_type_id),
					$conn->real_escape_string($template->year->id),
					$conn->real_escape_string('membership renewal'),
					$conn->real_escape_string(0.00),
					$conn->real_escape_string(0.00),
					$conn->real_escape_string($amount)
				)
			)===false) throw new Exception($conn->error);
		
		//	Otherwise make sure the created time
		//	and amounts are acceptable
		} else {
		
			$row=new MySQLRow($query);
			
			if ($conn->query(
				sprintf(
					'UPDATE
						`payment`
					SET
						`created`=NOW(),
						`subtotal`=\'%s\',
						`tax`=\'%s\',
						`total`=\'%s\'
					WHERE
						`id`=\'%s\'',
					$conn->real_escape_string(0.00),
					$conn->real_escape_string(0.00),
					$conn->real_escape_string($amount),
					$conn->real_escape_string($row['id'])
				)
			)===false) throw new Exception($conn->error);
		
		}
		
		//	Unlock
		if ($conn->query('UNLOCK TABLES')===false) throw new Exception($conn->error);
	
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
		
		if (
			($user->type==='admin') &&
			($request->GetQueryString('test')===TRUE_STRING)
		) $template->payment->is_test=true;
		
		Render($template,'payment.phtml');
	
	} else {
	
		Render($template,'payment_year_select.phtml');
	
	}


?>