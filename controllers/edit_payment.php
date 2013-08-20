<?php


	require_once(WHERE_PHP_INCLUDES.'key_value.php');

	
	//	Only admins can use this
	//	controller
	if ($user->type!=='admin') error(HTTP_FORBIDDEN);
	
	
	//	Get database access
	$conn=$dependencies['MISADBConn'];
	
	
	$template=new Template(WHERE_TEMPLATES);
	
	
	//	Determine whether we're adding or
	//	editing
	if ($request->GetController()==='edit_payment') {
	
		//	Editing
		$add=false;
		
		//	Verify that we have a numerical
		//	ID
		if (
			is_null($id=$request->GetArg(0)) ||
			!(
				is_numeric($id) &&
				($id=intval($id))==floatval($request->GetArg(0))
			)
		) error(HTTP_BAD_REQUEST);
	
	} else {
	
		//	Adding
		$add=true;
	
	}
	
	
	$template->types=array(
		'membership renewal' => 'Membership Renewal',
		'membership new' => 'New Membership',
		'sponsor' => 'Sponsor',
		'conference' => 'Conference',
		'other' => 'Other'
	);
	$template->messages=array();
	$template->paymethods=array(
		'VISA' => 'VISA',
		'MASTERCARD' => 'MasterCard',
		'AMERICAN EXPRESS' => 'American Express',
		'Cheque' => 'Cheque',
		'Other' => 'Other'
	);
	
	
	//	If we're editing, we need to get the
	//	row from the database so it can be
	//	edited
	unset($form);
	if (!$add) {
	
		//	Check the ID to make sure it's
		//	an integer et cetera
		if (
			is_null($id=$request->GetArg(0)) ||
			!(
				is_numeric($id) &&
				($id=intval($id))==floatval($request->GetArg(0))
			)
		) error(HTTP_BAD_REQUEST);
		
		//	Attempt to retrieve the payment and
		//	in so doing check to ensure it
		//	exists
		if (($query=$conn->query(
			sprintf(
				'SELECT * FROM `payment` WHERE `id`=\'%s\'',
				$conn->real_escape_string($id)
			)
		))===false) throw new Exception($conn->error);
		
		if ($query->num_rows===0) error(HTTP_BAD_REQUEST);
		
		$form=MySQLRow::FetchObject($query);
		
		//	Keep track of whether the payment was
		//	paid so we can update datepaid as
		//	appropriate
		$was_paid=$form->paid;
	
	}
	
	
	//	Handle POST backs
	if (is_post()) {
	
		if (!isset($form)) $form=new KeyValueStore();
		
		//	Perform shared verification
		$form->paid=fetch_post('paid')===TRUE_STRING;
		
		//	Validation/retrieval of certain
		//	elements is conditional upon paid
		//	being true
		if ($form->paid) {
		
			//	Get/validate the payment method,
			//	it will be used to decide how to
			//	validate going forward
			$form->paymethod=fetch_post('paymethod');
			
			if (!in_array(
				$form->paymethod,
				array_keys($template->paymethods),
				true
			)) error(HTTP_BAD_REQUEST);
			
			//	Validate the fields pertaining
			//	to the payment method-in-question
			if ($form->paymethod==='Cheque') {
			
				//	Paying by cheque
				
				$form->chequeissuedby=fetch_post('chequeissuedby');
				$form->chequenumber=fetch_post('chequenumber');
				if (
					is_null($form->chequeissuedby) ||
					is_null($form->chequenumber)
				) error(HTTP_BAD_REQUEST);
				
				$form->cardname=null;
				$form->response=null;
			
			} else if ($form->paymethod!=='Other') {
			
				//	Paying by credit card
				
				$form->cardname=fetch_post('cardname');
				$form->response=fetch_post('response');
				if (
					is_null($form->cardname) ||
					is_null($form->chequenumber)
				) error(HTTP_BAD_REQUEST);
				
				$form->chequeissuedby=null;
				$form->chequenumber=null;
			
			} else {
			
				$form->chequeissuedby=null;
				$form->chequenumber=null;
				$form->cardname=null;
				$form->response=null;
			
			}
			
			$form->amountpaid=fetch_post('amountpaid');
			if (!is_numeric($form->amountpaid)) error(HTTP_BAD_REQUEST);
			$form->amountpaid=floatval($form->amountpaid);
		
		} else {
		
			$form->amountpaid=null;
			$form->chequeissuedby=null;
			$form->chequenumber=null;
			$form->cardname=null;
			$form->response=null;
		
		}
		
		$form->notes=fetch_post('notes');
		$form->total=fetch_post('total');
		if (!is_numeric($form->total)) error(HTTP_BAD_REQUEST);
		$form->total=floatval($form->total);
		
		if ($add) {
		
			//	Process a POST back to add a new
			//	payment
		
			//	The payment type must be valid
			//	and there must be an integer
			//	organization ID
			$form->type=fetch_post('type');
			$form->org_id=fetch_post_int('org_id');
			if (
				!in_array(
					$form->type,
					array_keys($template->types),
					true
				) ||
				is_null($form->org_id)
			) error(HTTP_BAD_REQUEST);
			
			//	If the payment type is a membership
			//	renewal or a new membership then there
			//	must be an associated membership type
			//	and year
			$dues=($form->type==='membership new') || ($form->type==='membership renewal');
			$form->membership_type_id=fetch_post_int('membership_type_id');
			$form->membership_year_id=fetch_post_int('membership_year_id');
			if (
				$dues &&
				(
					is_null($form->membership_type_id) ||
					is_null($form->membership_year_id)
				)
			) error(HTTP_BAD_REQUEST);
			
			$sql_check=function ($sql) use ($conn) {
			
				if (($query=$conn->query($sql))===false) throw new Exception($conn->error);
				
				$row=$query->fetch_row();
				
				return intval($row[0])!==0;
			
			};
		
			//	Lock database tables
			if ($conn->query(
				'LOCK TABLES `organizations` READ, `payment` WRITE, `membership_types` READ, `membership_years` READ'
			)===false) throw new Exception($conn->error);
			
			//	Check to ensure organization,
			//	membership type (if applicable),
			//	and membership year (if applicable)
			//	are valid.
			if (!(
				$sql_check(
					sprintf(
						'SELECT
							COUNT(*)
						FROM
							`organizations`
						WHERE
							`id`=\'%s\'',
						$conn->real_escape_string($form->org_id)
					)
				) &&
				(
					!$dues ||
					(
						$sql_check(
							sprintf(
								'SELECT
									COUNT(*)
								FROM
									`membership_years`
								WHERE
									`id`=\'%s\'',
								$conn->real_escape_string($form->membership_year_id)
							)
						) &&
						$sql_check(
							sprintf(
								'SELECT
									COUNT(*)
								FROM
									`membership_types`
								WHERE
									`id`=\'%s\'',
								$conn->real_escape_string($form->membership_type_id)
							)
						)
					)
				)
			)) {
			
				if ($conn->query('UNLOCK TABLES')===false) throw new Exception($conn->error);
				
				error(HTTP_BAD_REQUEST);
			
			}
			
			//	If this is a dues payment the
			//	organization must not have a payment
			//	already for that membership year
			if (
				$dues &&
				!$sql_check(
					sprintf(
						'SELECT
							COUNT(*)
						FROM
							`payment`
						WHERE
							`org_id`=\'%s\' AND
							`membership_year_id`=\'%s\'',
						$conn->real_escape_string($form->org_id),
						$conn->real_escape_string($form->membership_year_Id)
					)
				)
			) $template->messages[]='That organization already has a payment for that membership year';
			
			//	INSERT into database
			if (count($template->messages)===0) {
			
				$insert_fields='`created`';
				$insert_values='NOW()';
				foreach ($form as $name=>$value) if (!is_null($value)) {
				
					$insert_fields.=',`'.preg_replace(
						'/`/u',
						'``',
						$name
					).'`';
					
					$insert_values.=',\''.$conn->real_escape_string(
						is_bool($value)
							?	($value ? 1 : 0)
							:	$value
					).'\'';
				
				}
				
				if ($form->paid) {
				
					$insert_fields.=',`datepaid`';
					$insert_values.=',NOW()';
				
				}
				
				if ($conn->query(
					sprintf(
						'INSERT INTO `payment` (%s) VALUES (%s)',
						$insert_fields,
						$insert_values
					)
				)===false) throw new Exception($conn->error);
				
				//	Redirect to edit newly-created
				//	payment
				header(
					'Location: '.$request->MakeLink(
						'edit_payment',
						$conn->insert_id
					)
				);
				
				exit();
			
			}
		
		} else {
		
			//	Save to database
			
			$update_text='';
			foreach ($form as $key=>$value) if (!(
				($key==='datepaid') ||
				($key==='created') ||
				($key==='id')
			)) {
			
				if ($update_text!=='') $update_text.=',';
			
				$update_text.='`'.preg_replace(
					'/`/u',
					'``',
					$key
				).'`='.(
					is_null($value)
						?	'NULL'
						:	'\''.$conn->real_escape_string(
								is_bool($value)
									?	($value ? 1 : 0)
									:	$value
							).'\''
				);
			
			}
			
			//	Update the date paid as appropriate
			if (($form->paid ? true : false)!==$was_paid) {
			
				if ($update_text!=='') $update_text.=',';
				
				$update_text.='`datepaid`='.($form->paid ? 'NOW()' : 'NULL');
			
			}
			
			if ($conn->query(
				sprintf(
					'UPDATE `payment` SET %s WHERE `id`=\'%s\'',
					$update_text,
					$conn->real_escape_string($form->id)
				)
			)===false) throw new Exception($conn->error);
		
		}
	
	}
	
	
	//	In order to generate/display the form
	//	we need to gather some information
	if ($add) {
	
		//	Adding
		
		if (isset($form)) $template->form=$form;
		else $template->form=new KeyValueStore();
	
		//	If we're adding we need to do the
		//	following:
		//
		//	1.	Retrieve a list of all organizations.
		//	2.	Retrieve a list of all membership years.
		//	3.	Retrieve a list of all membership types.
		
		
		$get_dropdown=function ($sql) use ($conn) {
		
			if (($query=$conn->query($sql))===false) throw new Exception($conn->error);
			
			if ($query->num_rows!==0) for (
				$row=new MySQLRow($query);
				!is_null($row);
				$row=$row->Next()
			) $retr[$row[0]->GetValue()]=$row[1]->GetValue();
			
			return $retr;
		
		};
		
		$template->organizations=$get_dropdown(
			'SELECT
				`id`,
				`name`
			FROM
				`organizations`
			ORDER BY
				`name`'
		);
		
		$template->membership_types=$get_dropdown(
			'SELECT
				`id`,
				`name`
			FROM
				`membership_types`
			ORDER BY
				`name`'
		);
		
		$template->membership_years=$get_dropdown(
			'SELECT
				`id`,
				`name`
			FROM
				`membership_years`
			ORDER BY
				`name`'
		);
	
	} else {
	
		//	Editing
		
		$template->form=$form;
		
		//	If we're editing we need to do
		//	the following:
		//
		//	1.	Retrieve information about the
		//		membership year and membership type
		//		if it's a membership dues payment.
		
		//	Get the organization that's paying
		//
		//	No need for null check, database has a
		//	foreign key relationship
		$template->organization=Organization::GetByID($template->form->org_id);
		
		//	Get membership type and
		//	membership year if applicable
		if (
			($template->form->type==='membership new') ||
			($template->form->type==='membership renewal')
		) {
		
			if (
				is_null($template->form->membership_type_id) ||
				is_null($template->form->membership_year_id)
			) error(HTTP_INTERNAL_SERVER_ERROR);
			
			$template->membership_type=Organization::GetType($template->form->membership_type_id);
			
			if (($query=$conn->query(
				sprintf(
					'SELECT * FROM `membership_years` WHERE `id`=\'%s\'',
					$conn->real_escape_string($template->form->membership_year_id)
				)
			))===false) throw new Exception($conn->error);
			
			//	No need to check for zero rows,
			//	guaranteed by a foreign key
			//	relationship
			$template->membership_year=MySQLRow::FetchObject($query);
		
		}
	
	}
	
	
	//	Set page title appropriately
	$title=$add ? 'Add Payment' : 'Edit Payment';
	
	
	$template->add=$add;
	
	
	//	Render template
	Render(
		$template,
		'edit_payment.phtml'
	);


?>