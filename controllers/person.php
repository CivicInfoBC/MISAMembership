<?php


	require_once(WHERE_PHP_INCLUDES.'form.php');
	
	
	//	Select the user to modify

	//	If there's no argument, it's
	//	the logged in user
	if (is_null($request->GetArg(0))) {
	
		$curr_user=$user;
	
	//	Make sure the user
	//	ID supplied is
	//	numeric
	} else if (
		is_numeric($request->GetArg(0)) &&
		(($user_id=intval($request->GetArg(0)))==floatval($request->GetArg(0)))
	) {
	
		//	Try and get the user from
		//	the database
		$curr_user=User::GetByID($user_id);
		
		//	If the user doesn't exist,
		//	that's a bad request
		if (is_null($curr_user)) error(HTTP_BAD_REQUEST);
	
	//	If the user ID isn't
	//	numeric that's a bad
	//	request
	} else {
	
		error(HTTP_BAD_REQUEST);
	
	}
	

	//	Determine what kind of access
	//	to this user the logged in
	//	user is allowed to have
	
	//	Everyone can modify themselves
	if ($user->id===$curr_user->id) {
	
		$read_only=false;
	
	//	If the user is a regular
	//	user, they're only allowed
	//	to modify themselves
	} else if (
		($user->type==='user') ||
		//	Treat users with an invalid
		//	type as if they were users
		!in_array(
			$user->type,
			array(
				'user',
				'superuser',
				'admin'
			),
			true
		)
	) {
	
		$read_only=true;
	
	//	Anyone with a higher access than
	//	a regular user can modify people
	//	in their organization
	} else if (
		isset($user->org) &&
		isset($curr_user->org) &&
		($user->org->id===$curr_user->org->id)
	) {
	
		$read_only=false;
	
	//	This leaves superusers, who can't
	//	modify outside their organization,
	//	and administrators, who can modify
	//	anything
	} else {
	
		$read_only=$user->type==='superuser';
	
	}
	
	
	//	If we're just displaying the user
	//	we have all the information we need
	//	so dispatch to a template
	if ($read_only) {
	
		$template=new Template(WHERE_TEMPLATES);
		
		$template->user=$curr_user;
		
		Render($template,'display_user.phtml');
	
	} else {
	
		//	Prepare the form
		$elements=array(
			new TextElement(
				'User ID',
				$curr_user->id
			),
			new TextFormElement(
				'first_name',
				'First Name',
				'^\\w+$',	//	Non-optional, one word
				$curr_user->first_name
			),
			new TextFormElement(
				'last_name',
				'Last Name',
				'^\\w+$',	//	Non-optional, one word
				$curr_user->last_name
			),
			new TextFormElement(
				'title',
				'Title',
				'',	//	Optional
				$curr_user->title
			),
			new TextFormElement(
				'email',
				'E-Mail',
				'^[^@]+@[^@]+\\.[^@]+$',	//	E-mails can't be encapsulated by regexes, but here's some basic requirements
				$curr_user->email
			),
			new TextElement(
				'Username',
				$curr_user->username
			),
			new ChangePasswordFormElement(
				'password',
				'New Password'
			),
			new TextFormElement(
				'address',
				'Address',
				'^.+$',	//	Non-optional
				$curr_user->address
			),
			new TextFormElement(
				'address2',
				'Address (Continued)',
				'',	//	Optional
				$curr_user->address2
			),
			new TextFormElement(
				'city',
				'City',
				'^.+$',	//	Non-optional
				$curr_user->city
			),
			new ProvinceFormElement(
				'territorial_unit',
				'Province/State & Country',
				'^.+$',	//	Non-optional
				$curr_user->territorial_unit
			),
			new TextFormElement(
				'phone',
				'Phone',
				'^[\\d\\-\\w]+$',	//	Non-optional
				$curr_user->phone
			),
			new TextFormElement(
				'fax',
				'Fax',
				'^[\\d\\-\\w]*$',	//	Optional but with character restrictions
				$curr_user->fax
			),
			new SubmitFormElement(
				'Submit'
			)
		);
		$form=new Form('','POST',$elements);
		
		//	Did we POST?
		if (
			($_SERVER['REQUEST_METHOD']==='POST') &&
			($_SERVER['CONTENT_TYPE']==='application/x-www-form-urlencoded')
		) {
		
			//	Populate
			$form->Populate();
			
			//	Verify
			//
			//	We don't handle this gracefully.
			//
			//	There's front-end validation for
			//	a reason.
			if (!$form->Verify()) error(HTTP_BAD_REQUEST);
			
			//	Get the values from the form
			$arr=$form->GetValues();
			
			//	Set the ID
			$arr['id']=$curr_user->id;
			
			//	Do a quick scan to enforce
			//	null
			foreach ($arr as &$value) {
			
				$value=MBString::Trim($value);
				
				if ($value==='') $value=null;
			
			}
			
			//	Process password change
			if (
				isset($arr['password']) &&
				($arr['password']!=='')
			) {
			
				//	Replace the plaintext password
				//	with a secure hash thereof
				$arr['password']=User::PasswordHash($arr['password']);
			
			//	No password change requested,
			//	ignore
			} else {
			
				unset($arr['password']);
			
			}
			
			//	Process the Country/Province/State
			//	drop-down's values
			
			$terr_unit=$arr['territorial_unit'];
			unset($arr['territorial_unit']);
			
			//	Is it a drop-down option?
			if (in_array(
				$terr_unit,
				array_keys(ProvinceFormElement::$options),
				true
			)) {
			
				$terr_unit=ProvinceFormElement::$options[$terr_unit];
				
				//	Check for the hyphen
				if (preg_match(
					'/(.+) \\- (.+)/u',
					$terr_unit,
					$matches
				)===0) {
				
					//	It's just a country
					$arr['country']=$terr_unit;
					$arr['territorial_unit']=null;
				
				} else {
				
					$arr['country']=$matches[1];
					$arr['territorial_unit']=$matches[2];
				
				}
			
			} else {
			
				//	Just assume it's a country
				$arr['country']=$terr_unit;
				$arr['territorial_unit']=null;
			
			}
			
			//	Save the information to the
			//	database
			$temp=new User($arr);
			$temp->Save();
		
		}
		
		
		$template=new Template(WHERE_TEMPLATES);
		$template->form=$form;
		
		
		Render($template,'person.phtml');
		
	}


?>