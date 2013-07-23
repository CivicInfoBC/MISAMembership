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
	
	
	//	Users will be able to select one
	//	of the membership types, so
	//	fetch, sort, and arrange them
	//	into a convenient collection
	$types=Organization::GetTypes();
	
	usort(
		$types,
		function ($first, $second) {
		
			return Collator::Create(Collator::DEFAULT_VALUE)->compare(
				$first->name,
				$second->name
			);
		
		}
	);
	
	$mt=array();
	
	foreach ($types as $x) {
	
		$mt[$x->id]=$x->name.': $'.sprintf('%.2f',$x->price);
	
	}
	
	
	//	Prepare the form
	$elements=array(
		new TextFormElement(
			'name',
			'Organization Name',
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
		new RadioButtonFormElement(
			'membership_type_id',
			$mt,
			null,
			'Membership Type'
		),
		new TextFormElement(
			'contact_first_name',
			'Primary Contact First Name',
			'^.+$'	//	Non-optional
		),
		new TextFormElement(
			'contact_last_name',
			'Primary Contact Last Name',
			'^.+$'	//	Non-optional
		),
		new TextFormElement(
			'contact_title',
			'Primary Contact Title',
			''	//	Optional
		),
		new TextFormElement(
			'contact_username',
			'Primary Contact Username',
			'^.+$'	//	Non-optional
		),
		new ChangePasswordFormElement(
			'contact_password',
			'Primary Contact Password',
			false
		),
		new TextFormElement(
			'contact_email',
			'Primary Contact E-Mail',
			'^[^@]+@[^@]+\\.[^@]+$'	//	E-mails can't be encapsulated by regexes, but here's some basic requirements
		),
		new TextFormElement(
			'contact_phone',
			'Primary Contact Phone',
			'^[\\d\\-\\s\\(\\)\\+]+$'	//	Non-optional
		),
		new TextFormElement(
			'contact_fax',
			'Primary Contact Fax',
			'^[\\d\\-\\s\\(\\)\\+]*$'	//	Optional but with character restrictions
		),
		new TextFormElement(
			'secondary_contact_first_name',
			'Secondary Contact First Name',
			''	//	Optional
		),
		new TextFormElement(
			'secondary_contact_last_name',
			'Secondary Contact Last Name',
			''	//	Optional
		),
		new TextFormElement(
			'secondary_contact_title',
			'Secondary Contact Title',
			''	//	Optional
		),
		new TextFormElement(
			'secondary_contact_username',
			'Secondary Contact Username',
			''	//	Optional
		),
		new ChangePasswordFormElement(
			'secondary_contact_password',
			'Secondary Contact Password'
		),
		new TextFormElement(
			'secondary_contact_email',
			'Secondary Contact E-Mail',
			'^(?:[^@]+@[^@]+\\.[^@]+)?$'	//	E-mails can't be encapsulated by regexes, but here's some basic requirements
		),
		new TextFormElement(
			'secondary_contact_phone',
			'Secondary Contact Phone',
			'^[\\d\\-\\s\\(\\)\\+]*$'	//	Non-optional
		),
		new TextFormElement(
			'secondary_contact_fax',
			'Secondary Contact Fax',
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
		//	username, e-mail, or password
		//	has not been set, just axe the
		//	whole thing
		if (
			is_null($arr['secondary_contact_first_name']) ||
			is_null($arr['secondary_contact_last_name']) ||
			is_null($arr['secondary_contact_username']) ||
			is_null($arr['secondary_contact_password']) ||
			is_null($arr['secondary_contact_email'])
		) {
		
			unset($arr['secondary_contact_first_name']);
			unset($arr['secondary_contact_last_name']);
			unset($arr['secondary_contact_title']);
			unset($arr['secondary_contact_password']);
			unset($arr['secondary_contact_username']);
			unset($arr['secondary_contact_email']);
			unset($arr['secondary_contact_phone']);
			unset($arr['secondary_contact_fax']);
		
		}
		
		//	Prepare the organization for creation
		$org=$arr;
		unset($org['contact_username']);
		unset($org['contact_password']);
		unset($org['secondary_contact_username']);
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
		
		$primary=new User($primary);
		
		//	Create a user for the secondary contact
		//	if applicable
		unset($secondary);
		if (isset($arr['secondary_contact_name'])) {
		
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
			
			$secondary=new User($secondary);
		
		}
		
		$template->messages=array();
		
		//	Check to make sure that usernames and
		//	e-mail addresses are unique
		if (
			isset($secondary) &&
			(
				MBString::Compare(
					$primary->username,
					$secondary->username
				) ||
				MBString::Compare(
					$primary->username,
					$secondary->email
				) ||
				MBString::Compare(
					$primary->email,
					$secondary->username
				) ||
				MBString::Compare(
					$primary->email,
					$secondary->email
				)
			)
		) $template->messages[]='Primary and Secondary Contact Usernames and/or E-Mails must not be identical';
		
		//	Lock table so that users with
		//	the same username/e-mail cannot
		//	be simultaneously inserted
		$conn=$dependencies['MISADBConn'];
		if ($conn->query(
			'LOCK TABLES
				`users` WRITE,
				`organizations` WRITE,
				`payment` READ,
				`membership_years` READ,
				`membership_types` READ'
		)===false) throw new Exception($conn->error);
		
		if (!(
			is_null(User::GetByUsername($primary->username)) &&
			is_null(User::GetByUsername($primary->email))
		)) {
		
			$template->messages[]='Primary Contact Username is already taken';
			$template->messages[]='Primary Contact E-Mail is already taken';
		
		}
		
		if (
			isset($secondary) &&
			!(
				is_null(User::GetByUsername($secondary->username)) &&
				is_null(User::GetByUsername($secondary->email))
			)
		) {
			
			$template->messages[]='Secondary Contact Username is already taken';
			$template->messages[]='Secondary Contact E-Mail is already taken';
		
		}
		
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
		
		//	Release database lock
		if ($conn->query('UNLOCK TABLES')===false) throw new Exception($conn->error);
		
		//	If no errors, display complete
		//	template, otherwise re-render the
		//	form
		if (count($template->messages)===0) Render($template,'membership_complete.phtml');
		else goto display_form;
	
	} else {
	
		display_form:
	
		$template->form=$form;
		Render($template,'form.phtml');
		
	}


?>