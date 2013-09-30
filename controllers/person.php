<?php


	require_once(WHERE_PHP_INCLUDES.'form.php');
	
	
	//	Select the user to modify and
	//	set the add flag if we're adding
	//	a new user
	if ($request->GetController()==='add') {
	
		//	ADDING
	
		//	Only organization admins and
		//	sitewide admins can add new
		//	users
		if (!(
			($user->type==='admin') ||
			(
				($user->type==='superuser') &&
				!is_null($user->org_id)
			)
		)) error(HTTP_FORBIDDEN);
	
		$add=true;
		
		//	Empty user with no properties
		$curr_user=new User(array());
		
		//	If an organization ID was passed
		//	in the query string, add it as
		//	a property so it's preselected
		if (
			!is_null($org_id=$request->GetQueryString('org_id')) &&
			is_numeric($org_id) &&
			(($org_id_int=intval($org_id))==floatval($org_id))
		) $curr_user->org_id=$org_id_int;
	
	} else {
	
		//	EDITING OR DELETING
		
		$add=false;
		
		$id=($request->GetArg(0)==='delete') ? $request->GetArg(1) : $request->GetArg(0);
		
		$curr_user=(
			is_numeric($id) &&
			(intval($id)==floatval($id))
		) ? User::GetByID(intval($id)) : $user;
		
		//	Make sure that user actually
		//	exists
		if (is_null($curr_user)) error(HTTP_BAD_REQUEST);
		
		//	Check to see if user can delete
		//	target user
		//
		//	Users may delete other users
		//	if:
		//
		//	1.	They themselves are not the
		//		target user
		//	AND EITHER
		//	2.	They are an administrator
		//	OR
		//	3.	They are a superuser of the
		//		same organization as the
		//		target user
		//	AND
		//	4.	The target user is not an
		//		administrator
		$can_delete=(
			($user->id!==$curr_user->id) &&
			(
				($user->type==='admin') ||
				(
					($curr_user->type!=='admin') &&
					($user->type==='superuser') &&
					($user->org_id===$curr_user->org_id)
				)
			)
		);
	
	}
	
	
	$template=new Template(WHERE_TEMPLATES);
	
	
	$conn=$dependencies['MISADBConn'];
	
	
	//	If deleting, perform permissions check,
	//	perform deletion (if appropriate), and
	//	end
	if ($request->GetArg(0)==='delete') {
	

		if (!$can_delete) error(HTTP_FORBIDDEN);
		
		//	Lock table
		if ($conn->query(
			'LOCK TABLES `users` WRITE, `sessions` WRITE, `organization_notes` WRITE'
		)===false) throw new Exception($conn->error);
		
		try {

			if (
				//	Delete all the user's sessions
				($conn->query(
					sprintf(
						'DELETE FROM `sessions` WHERE `user_id`=\'%s\'',
						$conn->real_escape_string($curr_user->id)
					)
				)===false) ||
				//	Delete user from all notes
				($conn->query(
					sprintf(
						'UPDATE `organization_notes` SET `modified_by`=NULL WHERE `modified_by`=\'%s\'',
						$conn->real_escape_string($curr_user->id)
					)
				)===false) ||
				($conn->query(
					sprintf(
						'UPDATE `organization_notes` SET `created_by`=NULL WHERE `created_by`=\'%s\'',
						$conn->real_escape_string($curr_user->id)
					)
				)===false) ||
				//	Delete user
				($conn->query(
					sprintf(
						'DELETE FROM `users` WHERE `id`=\'%s\'',
						$conn->real_escape_string($curr_user->id)
					)
				)===false)
			) throw new Exception($conn->error);
		
		} catch (Exception $e) {
		
			$conn->query('UNLOCK TABLES');
			
			throw $e;
		
		}
		
		if ($conn->query('UNLOCK TABLES')===false) throw new Exception($conn->error);
		
		$title='Delete User';
		
		$template->user=$curr_user;
		Render($template,'delete_user.phtml');
		
		exit();
	
	}
	
	
	//	If editing, determine the kind
	//	of access the user can have
	if (!$add) $read_only=!(
		//	Everyone can modify themselves
		($user->id===$curr_user->id) ||
		//	Sitewide admins can modify everyone
		($user->type==='admin') ||
		//	Organization admins can modify everyone
		//	in their organization, except sitewide
		//	admins
		(
			($user->type==='superuser') &&
			//	Make sure we don't let superusers
			//	who somehow are disassociated from
			//	an organization edit everyone
			!is_null($user->org_id) &&
			($user->org_id===$curr_user->org_id) &&
			($curr_user->type!=='admin')
		)
	);
	
	
	//	Set title
	$title=(
		$add
			?	'Add User'
			:	(
					($user->id===$curr_user->id)
						?	'My Profile'
						:	(
								$read_only
									?	'View User'
									:	'Edit User'
							)
				)
	);
	
	
	//	If we're just displaying the user
	//	we have all the information we need
	//	so dispatch to a template
	if (!$add && $read_only) {
		
		$template->user=$curr_user;
		
		Render($template,'display_user.phtml');
	
	} else {
	
		//	Add top link if we're editing and
		//	the current user can delete the
		//	target user
		if (!$add && $can_delete) $template->top_links=array(
			$request->MakeLink(
				null,
				array(
					'delete',
					$curr_user->id
				)
			) => 'Delete this User'
		);
	
		//	Users can always elevate to or
		//	create users at their level of
		//	privilege
		$elevate=array(
			'user' => 'User'
		);
		
		if (
			($user->type==='superuser') ||
			($user->type==='admin')
		) $elevate['superuser']='Organization Admin';
		
		if ($user->type==='admin') $elevate['admin']='Site Admin';
		
		//	Create an array to use to find
		//	the label for this user's type
		$user_types=array(
			'user' => 'User',
			'superuser' => 'Organization Admin',
			'admin' => 'Site Admin'
		);
	
		//	Prepare the form
		$elements=$add ? array() : array(
			new TextElement(
				'User ID',
				$curr_user->id
			)
		);
		
		$elements=array_merge(
			$elements,
			array(
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
				new ChangePasswordFormElement(
					'password',
					$add ? 'Password' : 'New Password',
					!$add
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
					'^.+$',	//	Non-optional
					$curr_user->phone
				),
				new TextFormElement(
					'fax',
					'Fax',
					'^[\\d\\-\\s\\(\\)\\+]*$',	//	Optional but with character restrictions
					$curr_user->fax
				),
				//	If the user is an administrator,
				//	they can change the organization
				//	that a user is a member of, otherwise
				//	they can only view that organization
				($user->type==='admin')
					?	new DropDownFormElement(
							'org_id',
							$curr_user->org_id,
							'Organization',
							$conn,
							true,
							'SELECT `id`,`name` FROM `organizations` ORDER BY `name`'
						)
					:	new TextElement(
							'Organization',
							//	If adding, we scrape the currently
							//	logged in user's organization
							is_null(
								$add
									?	$user->organization
									:	$curr_user->organization
							) ? '' : (
								$add ? $user->organization->name : $curr_user->organization->name
							)
						)
			)
		);
		
		//	If the user is not a regular user,
		//	they can enable/disable users,
		//	except themselves
		//
		//	If the user is adding a new user,
		//	they can unconditionally create that
		//	user disabled
		if (
			$add ||
			(
				(
					($user->type==='superuser') ||
					($user->type==='admin')
				) &&
				($curr_user->id!==$user->id)
			)
		) $elements[]=new CheckBoxFormElement(
			'enabled',
			$add || $curr_user->enabled,
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
		
		$elements[]=new CheckBoxFormElement(
			'opt_out',
			$curr_user->opt_out,
			'Hide contact details'
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
			
			//	Do a quick scan to enforce
			//	null
			foreach ($arr as &$value) {
			
				if (is_string($value)) {
			
					$value=MBString::Trim($value);
					
					if ($value==='') $value=null;
				
				}
			
			}
			
			//	E-Mail addresses are always lowercase
			$arr['email']=MBString::ToLower($arr['email']);
			
			//	Process the Country/Province/State
			//	drop-down's values
			$obj=ProvinceFormElement::Split($arr['territorial_unit']);
			$arr['territorial_unit']=$obj->territorial_unit;
			$arr['country']=$obj->country;
			
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
			
			//	We're going to need to check to
			//	make sure -- if an e-mail is being
			//	changed -- that the e-mail is unique.
			
			//	Lock the user table
			if ($conn->query('LOCK TABLES `users` WRITE')===false) throw new Exception($conn->error);
			
			try {
			
				//	Branch based on whether we're adding or not
				if ($add) {
				
					//	Organization admins can only create
					//	users in their own organization
					if ($user->type==='superuser') $arr['org_id']=$user->org_id;
					
					//	Check for duplicate e-mail
					if (($query=$conn->query(
						sprintf(
							'SELECT
								COUNT(*)
							FROM
								`users`
							WHERE
								`email`=\'%s\'',
							$conn->real_escape_string($arr['email'])
						)
					))===false) throw new Exception($conn->error);
					
					$row=new MySQLRow($query);
					
					if ($row[0]->GetValue()!==0) {
					
						$temp=null;
						$template->messages=array('E-Mail already taken');
					
					} else {
					
						$temp=new User($arr);
						$insert_id=$temp->Save();
					
					}
					
					//	Unlock tables
					if ($conn->query('UNLOCK TABLES')===false) throw new Exception($conn->error);
					
					if (!is_null($temp)) {
					
						header(
							'Location: '.$request->MakeLink(
								null,
								$insert_id
							)
						);
						
						exit();
					
					}
				
				} else {
				
					//	Set the ID
					$arr['id']=$curr_user->id;
					
					//	Check for duplicate e-mail
					if (($query=$conn->query(
						sprintf(
							'SELECT
								COUNT(*)
							FROM
								`users`
							WHERE
								`email`=\'%s\' AND
								`id`<>\'%s\'',
							$conn->real_escape_string($arr['email']),
							$conn->real_escape_string($arr['id'])
						)
					))===false) throw new Exception($conn->error);
					
					$row=new MySQLRow($query);
					
					if ($row[0]->GetValue()!==0) {
					
						$template->messages=array('E-mail already taken');
					
					} else {
					
						$temp=new User($arr);
						$temp->Save();
					
					}
					
					//	Unlock tables
					if ($conn->query('UNLOCK TABLES')===false) throw new Exception($conn->error);
				
				}
				
			} catch (Exception $e) {
			
				//	Attempt to unlock
				$conn->query('UNLOCK TABLES');
			
				throw $e;
			
			}
		
		}
		
		
		$template->form=$form;
		
		
		Render($template,'form.phtml');
		
	}


?>