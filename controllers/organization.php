<?php

	
	require_once(WHERE_PHP_INCLUDES.'form.php');
	
	
	//	Select the organization and set
	//	the add flag if we're adding a new
	//	organization
	if ($request->GetArg(0)==='add') {
	
		//	ADDING
		
		//	Only sitewide admins can
		//	add new organizations
		if ($user->type!=='admin') error(HTTP_FORBIDDEN);
		
		$add=true;
		
		//	Empty organization with no
		//	properties
		$org=new Organization(array());
	
	} else {
	
		//	EDITING
		
		$add=false;
		
		$id=$request->GetArg(0);
		
		$org=(
			is_numeric($id) &&
			(intval($id)==floatval($id))
		) ? Organization::GetByID(intval($id)) : $user->organization;
		
		//	Make sure that organization
		//	actually exists
		if (is_null($org)) error(HTTP_BAD_REQUEST);
	
	}
	
	
	//	If editing, determine the kind of
	//	access the user can have
	if (!$add) $read_only=!(
		//	Sitewide admins can edit
		//	everything
		($user->type==='admin') ||
		//	Organization admins can
		//	edit their own organization
		(
			($user->type==='superuser') &&
			($user->org_id===$org->id)
		)
	);
	
	
	//	Set title
	$title=(
		$add
			?	'Add Organization'
			:	(
					($user->org_id===$org->id)
						?	'My Organization'
						:	(
								$read_only
									?	'View Organization'
									:	'Edit Organization'
							)
				)
	);
	
	
	//	Template and shared info
	$template=new Template(WHERE_TEMPLATES);
	$template->org=$org;
	
	
	//	IF we're just displaying the organizational
	//	information we have all the information we
	//	need to dispatch to a template
	if (!$add && $read_only) {
		
		$template->org=$org;
		$template->type=Organization::GetType($org->membership_type_id);
		$template->users=$org->GetUsers();
		
		Render($template,'display_organization.phtml');
	
	} else {
	
		//	Prepare the form
		$elements=$add ? array() : array(
			new TextElement(
				'Organization ID',
				$org->id
			)
		);
		
		$elements=array_merge(
			$elements,
			array(
				new TextFormElement(
					'name',
					'Name',
					'^.+$',	//	Non-optional
					$org->name
				),
				new TextFormElement(
					'address1',
					'Address',
					'^.+$',	//	Non-optional
					$org->address1,
					'text',
					'wide'
				),
				new TextFormElement(
					'address2',
					'Address (Continued)',
					'',	//	Optional
					$org->address2,
					'text',
					'wide'
				),
				new TextFormElement(
					'city',
					'City',
					'^.+$',	//	Non-optional
					$org->city
				),
				new ProvinceFormElement(
					'territorial_unit',
					'Province/State & Country',
					'^.+$',	//	Non-optional
					(is_null($org->territorial_unit) || ($org->territorial_unit===''))
						?	$org->country
						:	$org->country.' - '.$org->territorial_unit
				),
				new TextFormElement(
					'postal_code',
					'Postal/Zip Code',
					'^\\s*[A-Za-z]\\d[A-Za-z](?:\\s|\\-)*\\d[A-Za-z]\\d|\\d{5}\\s*$',
					$org->postal_code
				),
				new TextFormElement(
					'url',
					'Web Address',
					'',	//	Optional
					$org->url
				),
				new TextFormElement(
					'phone',
					'Phone',
					'^[\\d\\-\\s\\(\\)\\+]+$',	//	Non-optional
					$org->phone
				),
				new TextFormElement(
					'fax',
					'Fax',
					'^[\\d\\-\\s\\(\\)\\+]*$',	//	Optional but with character restrictions
					$org->fax
				),
				//	If the user is a sitewide
				//	administrator they can change
				//	the type of the organization,
				//	otherwise they can just see
				//	it
				($user->type==='admin')
					?	new DropDownFormElement(
							'membership_type_id',
							$org->membership_type_id,
							'Type',
							$dependencies['MISADBConn'],
							false,
							'SELECT `id`,`name` FROM `membership_types` ORDER BY `name`'
						)
					:	new TextElement(
							'Type',
							Organization::GetType($org->membership_type_id)
						)
			)
		);
		
		//	If the user is a sitewide admin
		//	they can set an organization's
		//	enabled/disabled status, other
		//	types of users cannot see it.
		if ($user->type==='admin') {
		
			$elements[]=new CheckBoxFormElement(
				'enabled',
				$add || $org->enabled,
				'Enabled'
			);
		
		}
		
		
		//	If the user is a sitewide
		//	administrator they can make
		//	organizations "perpetual".
		//
		//	Organization admins can view
		//	whether or not their organization
		//	is "perpetual".
		$elements[]=(
			($user->type==='admin')
				?	new CheckBoxFormElement(
						'perpetual',
						$org->perpetual,
						'Perpetual'
					)
				:	new TextElement(
						'Perpetual',
						$org->perpetual ? 'Yes' : 'No'
					)
		);
		
		
		$elements=array_merge(
			$elements,
			array(
				new HeadingElement(
					'Primary Contact Information',
					2
				),
				new TextFormElement(
					'contact_name',
					'Name',
					'^.+$',	//	Non-optional
					$org->contact_name
				),
				new TextFormElement(
					'contact_title',
					'Title',
					'',	//	Optional
					$org->contact_title
				),
				new TextFormElement(
					'contact_email',
					'E-Mail',
					'^[^@]+@[^@]+\\.[^@]+$',	//	E-mails can't be encapsulated by regexes, but here's some basic requirements
					$org->contact_email
				),
				new TextFormElement(
					'contact_phone',
					'Phone',
					'^[\\d\\-\\s\\(\\)\\+]+$',	//	Non-optional
					$org->contact_phone
				),
				new TextFormElement(
					'contact_fax',
					'Fax',
					'^[\\d\\-\\s\\(\\)\\+]*$',	//	Optional but with character restrictions
					$org->contact_fax
				),
				new HeadingElement(
					'Secondary Contact Information (Optional)',
					2
				),
				new TextFormElement(
					'secondary_contact_name',
					'Name',
					'',	//	Optional
					$org->secondary_contact_name
				),
				new TextFormElement(
					'secondary_contact_title',
					'Title',
					'',	//	Optional
					$org->secondary_contact_title
				),
				new TextFormElement(
					'secondary_contact_email',
					'E-Mail',
					'^(?:[^@]+@[^@]+\\.[^@]+)?$',	//	E-mails can't be encapsulated by regexes, but here's some basic requirements
					$org->secondary_contact_email
				),
				new TextFormElement(
					'secondary_contact_phone',
					'Phone',
					'^[\\d\\-\\s\\(\\)\\+]*$',	//	Optional
					$org->secondary_contact_phone
				),
				new TextFormElement(
					'secondary_contact_fax',
					'Fax',
					'^[\\d\\-\\s\\(\\)\\+]*$',	//	Optional but with character restrictions
					$org->secondary_contact_fax
				)
			)
		);
		
		
		$elements[]=new SubmitFormElement(
			'Submit'
		);
		
		$form=new Form('','POST',$elements);
		
		
		//	Was this request a POST?
		if (is_post()) {
		
			//	Populate
			$form->Populate();
			
			//	Verify
			//
			//	We don't handle this gracefully.
			//
			//	There's front-end validation for
			//	a reason
			if (!$form->Verify()) error(HTTP_BAD_REQUEST);
			
			//	Get the values from the form
			$arr=$form->GetValues();
			
			//	Set the ID unless we're adding
			//	a new organization
			if (!$add) $arr['id']=$org->id;
			
			//	Do a quick scan to enforce null
			//	vs. empty string
			foreach ($arr as &$value) {
			
				if (is_string($value)) {
				
					$value=MBString::Trim($value);
					
					if ($value==='') $value=null;
				
				}
			
			}
			
			//	E-Mail addresses are always lowercase
			$arr['contact_email']=MBString::ToLower($arr['contact_email']);
			if (!is_null($arr['secondary_contact_email'])) $arr['secondary_contact_email']=MBString::ToLower($arr['secondary_contact_email']);
			
			//	If mandatory information about
			//	the secondary contact is missing,
			//	ignore it
			if (
				is_null($arr['secondary_contact_name']) ||
				is_null($arr['secondary_contact_email']) ||
				is_null($arr['secondary_contact_phone'])
			) {
			
				$arr['secondary_contact_name']=null;
				$arr['secondary_contact_email']=null;
				$arr['secondary_contact_phone']=null;
				$arr['secondary_contact_fax']=null;
				$arr['secondary_contact_title']=null;
			
			}
			
			//	Process Country/Province/State
			//	drop-down's values
			$obj=ProvinceFormElement::Split($arr['territorial_unit']);
			$arr['territorial_unit']=$obj->territorial_unit;
			$arr['country']=$obj->country;
			
			//	Save the information to the
			//	database
			$temp=new Organization($arr);
			$insert_id=$temp->Save();
			
			//	Redirect to the edit/view page for
			//	the new organization on add
			if ($add) {
			
				header(
					'Location: '.$request->MakeLink(
						'organization',
						$insert_id
					)
				);
				
				exit();
			
			}
		
		}
		
		
		$template->form=$form;
		
		$templates=array('form.phtml');
		if (!$add) {
		
			//	Get payment information
			$template->payment_info=$org->PaymentHistory();
			//	Get users attached to this
			//	organization
			$template->users=$org->GetUsers();
		
			$templates[]='org_users.phtml';
			$templates[]='payment_info.phtml';
		
		}
		
		Render($template,$templates);
	
	}
	

?>