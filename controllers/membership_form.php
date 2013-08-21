<?php


	require_once(WHERE_LOCAL_PHP_INCLUDES.'settings.php');


	//	Prepare a template
	$template=new Template(WHERE_TEMPLATES);
	
	
	$title='Membership Application Form';
	
	
	//	Check to see if this is allowed
	if (!IsSettingTrue(GetSetting('membership_application_open'))) {
	
		Render($template,'form_closed.phtml');
	
		exit();
	
	}


	require_once(WHERE_PHP_INCLUDES.'form.php');
	
	
	$mt=new Template(WHERE_TEMPLATES);
	
	
	//	Users will be able to select one
	//	of the membership types, so
	//	fetch, sort, and arrange them
	//	into a convenient collection
	$types=Organization::GetTypes();
	$mt->types=array();
	
	foreach ($types as $type) if ($type->show) $mt->types[]=$type;
	
	usort(
		$mt->types,
		function ($first, $second) {
		
			if ($first->order<$second->order) return -1;
			if ($first->order>$second->order) return 1;
		
			return Collator::Create(Collator::DEFAULT_VALUE)->compare(
				$first->name,
				$second->name
			);
		
		}
	);
	
	
	//	Prepare the form
	$elements=array(
		new HeadingElement(
			'Membership Type',
			2
		),
		new CustomFormElement(
			'membership_type_id',
			null,
			function ($e) use ($mt) {	return $mt->Get('membership_type_radio.phtml');	},
			function ($e) use ($mt) {	return $mt->Get('membership_type_radio_validate.js');	},
			function ($e) use ($mt) {
			
				if (!(
					is_numeric($e->value) &&
					(($id=intval($e->value))==floatval($e->value))
				)) return false;
			
				foreach ($mt->types as $type) if ($id===$type->id) return true;
				
				return false;
			
			}
		),
		new HeadingElement(
			'Organization Information',
			2
		),
		new TextFormElement(
			'name',
			'Name',
			'^.+$'	//	Non-optional
		),
		new TextFormElement(
			'address1',
			'Address',
			'^.+$',	//	Non-optional
			null,
			'text',
			'wide'
		),
		new TextFormElement(
			'address2',
			'Address (Continued)',
			'',	//	Optional
			null,
			'text',
			'wide'
		),
		new TextFormElement(
			'city',
			'City',
			'^.+$'	//	Non-optional
		),
		new ProvinceFormElement(
			'territorial_unit',
			'Province/State & Country',
			'^.+$'	//	Non-optional
		),
		new TextFormElement(
			'postal_code',
			'Postal/Zip Code',
			'^\\s*[A-Za-z]\\d[A-Za-z](?:\\s|\\-)*\\d[A-Za-z]\\d|\\d{5}\\s*$'
		),
		new TextFormElement(
			'phone',
			'Phone',
			'^[\\d\\-\\s\\(\\)\\+]+$'	//	Non-optional
		),
		new TextFormElement(
			'fax',
			'Fax',
			'^[\\d\\-\\s\\(\\)\\+]*$'
		),
		new HeadingElement(
			'Primary Contact Information',
			2
		),
		new TextFormElement(
			'contact_first_name',
			'First Name',
			'^.+$'	//	Non-optional
		),
		new TextFormElement(
			'contact_last_name',
			'Last Name',
			'^.+$'	//	Non-optional
		),
		new TextFormElement(
			'contact_title',
			'Primary Contact Title',
			''	//	Optional
		),
		new ChangePasswordFormElement(
			'contact_password',
			'Password',
			false
		),
		new TextFormElement(
			'contact_email',
			'E-Mail',
			'^[^@]+@[^@]+\\.[^@]+$'	//	E-mails can't be encapsulated by regexes, but here's some basic requirements
		),
		new TextFormElement(
			'contact_phone',
			'Phone',
			'^[\\d\\-\\s\\(\\)\\+]+$'	//	Non-optional
		),
		new TextFormElement(
			'contact_fax',
			'Fax',
			'^[\\d\\-\\s\\(\\)\\+]*$'	//	Optional but with character restrictions
		),
		new HeadingElement(
			'Secondary Contact Information (Optional)',
			2
		),
		new TextFormElement(
			'secondary_contact_first_name',
			'First Name',
			''	//	Optional
		),
		new TextFormElement(
			'secondary_contact_last_name',
			'Last Name',
			''	//	Optional
		),
		new TextFormElement(
			'secondary_contact_title',
			'Title',
			''	//	Optional
		),
		new ChangePasswordFormElement(
			'secondary_contact_password',
			'Password'
		),
		new TextFormElement(
			'secondary_contact_email',
			'E-Mail',
			'^(?:[^@]+@[^@]+\\.[^@]+)?$'	//	E-mails can't be encapsulated by regexes, but here's some basic requirements
		),
		new TextFormElement(
			'secondary_contact_phone',
			'Phone',
			'^[\\d\\-\\s\\(\\)\\+]*$'	//	Non-optional
		),
		new TextFormElement(
			'secondary_contact_fax',
			'Fax',
			'^[\\d\\-\\s\\(\\)\\+]*$'	//	Optional but with character restrictions
		),
		new SubmitFormElement(
			'Submit'
		)
	);
	$form=new Form('','POST',$elements);
	
	
	//	Was this a POST request?
	if (is_post()) {
	
		//	Populate
		$form->Populate();
		
		//	Verify
		if (!$form->Verify()) error(HTTP_BAD_REQUEST);
		
		//	Get form values
		$arr=$form->GetValues();
		
		//	Do a quick scan to enforce null
		foreach ($arr as &$value) {
		
			if (is_string($value)) {
			
				$value=MBString::Trim($value);
				
				if ($value==='') $value=null;
			
			}
		
		}
		
		//	For secondary contact, if name,
		//	e-mail, or password has not been
		//	set, just axe the whole thing
		if (
			is_null($arr['secondary_contact_first_name']) ||
			is_null($arr['secondary_contact_last_name']) ||
			is_null($arr['secondary_contact_password']) ||
			is_null($arr['secondary_contact_email'])
		) {
		
			unset($arr['secondary_contact_first_name']);
			unset($arr['secondary_contact_last_name']);
			unset($arr['secondary_contact_title']);
			unset($arr['secondary_contact_password']);
			unset($arr['secondary_contact_email']);
			unset($arr['secondary_contact_phone']);
			unset($arr['secondary_contact_fax']);
		
		}
		
		//	Prepare the organization for creation
		$org=$arr;
		unset($org['contact_password']);
		unset($org['secondary_contact_password']);
		$org['contact_name']=$org['contact_first_name'].' '.$org['contact_last_name'];
		unset($org['contact_first_name']);
		unset($org['contact_last_name']);
		if (isset($org['secondary_contact_first_name'])) {
		
			$org['secondary_contact_name']=$org['secondary_contact_first_name'].' '.$org['secondary_contact_last_name'];
			unset($org['secondary_contact_first_name']);
			unset($org['secondary_contact_last_name']);
		
		}
		$terr_unit=ProvinceFormElement::Split($org['territorial_unit']);
		$org['territorial_unit']=$terr_unit->territorial_unit;
		$org['country']=$terr_unit->country;
		
		//	Create organization
		$org=new Organization($org);
		
		//	Create a user for the primary contact
		$primary=array();
		foreach ($arr as $key=>$value) {
		
			if (preg_match('/^contact_/u',$key)!==0) {
			
				$primary[
					preg_replace(
						'/^contact_/u',
						'',
						$key
					)
				]=$value;
				
			}
			
		}
		
		$primary['password']=User::PasswordHash($primary['password']);
		//	E-Mail addresses are always lowercase
		$primary['email']=MBString::ToLower($primary['email']);
		
		$primary=new User($primary);
		
		//	Create a user for the secondary contact
		//	if applicable
		unset($secondary);
		if (isset($arr['secondary_contact_first_name'])) {
		
			$secondary=array();
			foreach ($arr as $key=>$value) {
			
				if (preg_match('/^secondary_/u',$key)!==0) {
				
					$secondary[
						preg_replace(
							'/^secondary_contact_/u',
							'',
							$key
						)
					]=$value;
				
				}
			
			}
			
			$secondary['password']=User::PasswordHash($secondary['password']);
			//	E-Mail addresses are always lowercase
			$secondary['email']=MBString::ToLower($secondary['email']);
			
			$secondary=new User($secondary);
		
		}
		
		$template->messages=array();
		
		//	Check to make sure that e-mail addresses
		//	are not the same
		if (
			isset($secondary) &&
			MBString::Compare(
				$primary->email,
				$secondary->email
			)
		) $template->messages[]='Primary and Secondary Contact E-Mails must not be identical';
		
		//	Lock table so that users with
		//	the same e-mail cannot be
		//	simultaneously inserted
		$conn=$dependencies['MISADBConn'];
		if ($conn->query(
			'LOCK TABLES `users` WRITE, `organizations` WRITE'
		)===false) throw new Exception($conn->error);
		
		try {
		
			//	Check for e-mail collisions
			
			$check_email=function ($email) use ($conn) {
				
				if (($query=$conn->query(
					sprintf(
						'SELECT
							COUNT(*)
						FROM
							`users`
						WHERE
							`email`=\'%s\'',
						$conn->real_escape_string($email)
					)
				))===false) throw new Exception($conn->error);
				
				$row=new MySQLRow($query);
				
				return $row[0]->GetValue()===0;
			
			};
			
			if (!$check_email($primary->email)) $template->messages[]='Primary Contact E-Mail is already taken';
			if (
				isset($secondary) &&
				!$check_email($secondary->email)
			) $template->messages[]='Secondary Contact E-Mail is already taken';
			
			//	INSERT if no errors
			if (count($template->messages)===0) {
			
				$org->id=$org->Save();
				
				$primary->org_id=$org->id;
				$primary->type='superuser';
				$primary->Save();
				
				if (isset($secondary)) {
				
					$secondary->org_id=$org->id;
					$secondary->type='superuser';
					$secondary->Save();
				
				}
			
			}
			
		} catch (Exception $e) {
		
			$conn->query('UNLOCK TABLES');
			
			throw $e;
		
		}
		
		//	Release database lock
		if ($conn->query('UNLOCK TABLES')===false) throw new Exception($conn->error);
		
		//	If no errors, display complete
		//	template, otherwise re-render the
		//	form
		if (count($template->messages)===0) {
		
			require_once(WHERE_PHP_INCLUDES.'mail.php');
			require_once(WHERE_LOCAL_PHP_INCLUDES.'settings.php');
		
			//	Send email to administrators
			//	nominated to receive notifications
			//	of new applications
			$email=new EMail();
			$email->to=GetSetting('membership_application_email');
			$email->is_html=true;
			$email->subject='New Membership Application';
			$email->from=GetSettingValue(GetSetting('mail_from'));
			$email_template=new Template(WHERE_TEMPLATES);
			$org->membership_type=Organization::GetType($org->membership_type_id);
			$email_template->organization=$org;
			$email_template->primary=$primary;
			if (isset($secondary)) $email_template->secondary=$secondary;
			$email->Send(
				$email_template,
				array(
					'email.phtml',
					'email_membership_application.phtml'
				)
			);
		
			Render($template,'membership_complete.phtml');
			
		} else {
		
			goto display_form;
			
		}
	
	} else {
	
		display_form:
	
		$template->form=$form;
		$template->intro=array(
			'Thank-you for applying for MISA BC membership!',
			'Please fill out and submit the form below.  '.
			'Once our membership administrator has confirmed your application details, '.
			'you will receive information on how to pay your first year\'s membership dues.',
			'We look forward to having you join our community!'
		);
		Render($template,'form.phtml');
		
	}


?>