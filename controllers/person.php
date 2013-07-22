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
	
		//	Unless the target user is a
		//	site admin and they aren't
		$read_only=!(($curr_user->type==='superuser') && ($curr_user->type!=='superuser'));
	
	//	This leaves superusers, who can't
	//	modify outside their organization,
	//	and administrators, who can modify
	//	anything
	} else {
	
		$read_only=$user->type==='superuser';
	
	}
	
	
	//	Title
	$title=(
		($user->id===$curr_user->id)
			?	'My Profile'
			:	(
					$read_only
						?	'View User'
						:	'Edit User'
				)
	);
	

	$template=new Template(WHERE_TEMPLATES);
	
	
	//	If we're just displaying the user
	//	we have all the information we need
	//	so dispatch to a template
	if ($read_only) {
		
		$template->user=$curr_user;
		
		Render($template,'display_user.phtml');
	
	} else {
	
		//	Users can always elevate to
		//	their own level of privilege
		$elevate=array(
			'user' => 'User'
		);
		
		if (
			($user->type==='superuser') ||
			($user->type==='admin')
		) {
		
			$elevate['superuser']='Organization Admin';
		
		}
		
		if ($user->type==='admin') {
		
			$elevate['admin']='Site Admin';
		
		}
		
		//	Create an array to use to find
		//	the label for this user's type
		$user_types=array(
			'user' => 'User',
			'superuser' => 'Organization Admin',
			'admin' => 'Site Admin'
		);
	
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
				$curr_user->address,
				'text',
				'wide'
			),
			new TextFormElement(
				'address2',
				'Address (Continued)',
				'',	//	Optional
				$curr_user->address2,
				'text',
				'wide'
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
				(is_null($curr_user->territorial_unit) || ($curr_user->territorial_unit===''))
					?	$curr_user->country
					:	$curr_user->country.' - '.$curr_user->territorial_unit
			),
			new TextFormElement(
				'postal_code',
				'Postal/Zip Code',
				'^\\s*[A-Za-z]\\d[A-Za-z](?:\\s|\\-)*\\d[A-Za-z]\\d|\\d{5}\\s*$',
				$curr_user->postal_code
			),
			new TextFormElement(
				'phone',
				'Phone',
				'^[\\d\\-\\s\\(\\)\\+]+$',	//	Non-optional
				$curr_user->phone
			),
			new TextFormElement(
				'fax',
				'Fax',
				'^[\\d\\-\\s\\(\\)\\+]*$',	//	Optional but with character restrictions
				$curr_user->fax
			),
			//	If the user as an administrator,
			//	they can change the organization
			//	that a user is a member of, otherwise
			//	they can only view that organization
			($user->type==='admin')
				?	new DropDownFormElement(
						'org_id',
						$curr_user->org_id,
						'Organization',
						$dependencies['MISADBConn'],
						true,
						'SELECT `id`,`name` FROM `organizations` ORDER BY `name`'
					)
				:	new TextElement(
						'Organization',
						is_null($curr_user->organization) ? '' : $curr_user->organization->name
					)
		);
		
		//	If the user is not a regular user,
		//	they can enable/disable users,
		//	except themselves
		if (
			(
				($user->type==='superuser') ||
				($user->type==='admin')
			) &&
			($curr_user->id!==$user->id)
		) $elements[]=new CheckBoxFormElement(
			'enabled',
			$curr_user->enabled,
			'Enabled'
		);
		
		//	Regular users can't change
		//	status, so they just see
		//	a TextElement, but higher
		//	levels of privilege see 
		//	a drop-down which allows them
		//	to make members up to their own
		//	level of privilege
		//
		//	Also don't let users change
		//	their own type, that's just asking
		//	for trouble...
		$elements[]=(
			((($user->type==='superuser') || ($user->type==='admin')) && ($curr_user->id!==$user->id))
				?	new DropDownFormElement(
						'type',
						is_null($curr_user->type) ? 'user' : $curr_user->type,
						'Type',
						$elevate
					)
				:	new TextElement(
						'Type',
						$user_types[is_null($curr_user->type) ? 'user' : $curr_user->type]
					)
		);
		
		$elements[]=new SubmitFormElement(
			'Submit'
		);
		
		$form=new Form('','POST',$elements);
		
		//	Did we POST?
		if (is_post()) {
		
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
			
				if (is_string($value)) {
			
					$value=MBString::Trim($value);
					
					if ($value==='') $value=null;
				
				}
			
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
			$obj=ProvinceFormElement::Split($arr['territorial_unit']);
			$arr['territorial_unit']=$obj->territorial_unit;
			$arr['country']=$obj->country;
			
			//	Save the information to the
			//	database
			$temp=new User($arr);
			$temp->Save();
		
		}
		
		
		$template->form=$form;
		
		
		Render($template,'form.phtml');
		
	}


?>