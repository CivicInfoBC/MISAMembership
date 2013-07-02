<?php

	
	require_once(WHERE_PHP_INCLUDES.'form.php');
	
	
	//	Select the organization
	
	unset($org);
	
	//	If there's no argument, it's
	//	the logged it user's organization
	if (is_null($request->GetArg(0))) {
	
		$org=$user->organization;
	
	//	Make sure the organization ID
	//	supplied is numeric
	} else if (
		is_numeric($request->GetArg(0)) &&
		(($org_id=intval($request->GetArg(0)))==floatval($request->GetArg(0)))
	) {
	
		$org=Organization::GetByID($org_id);
	
	}
	
	//	If there's no organization
	//	specified, die
	if (!isset($org)) error(HTTP_BAD_REQUEST);
	
	
	//	Determine what kind of access to
	//	this organization this user has
	$read_only=!(
		//	If this user is a site admin, they
		//	can modify every organization
		($user->type==='admin') ||
		//	If this user is an organization admin
		//	they can modify the organization only
		//	if it's their organization
		(
			($user->type==='superuser') &&
			(!is_null($user->organization)) &&
			($user->organization->id===$org->id)
		)
	);
	
	
	//	IF we're just displaying the organizational
	//	information we have all the information we
	//	need to dispatch to a template
	if ($read_only) {
	
		$template=new Template(WHERE_TEMPLATES);
		
		$template->org=$org;
		$template->type=Organization::GetType($org->membership_type_id);
		
		Render($template,'display_organization.phtml');
	
	} else {
	
		//	Prepare the form
		$elements=array(
			new TextElement(
				'Organization ID',
				$org->id
			),
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
				$org->address1
			),
			new TextFormElement(
				'address2',
				'Address (Continued)',
				'',	//	Optional
				$org->address2
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
			,
			new TextFormElement(
				'contact_name',
				'Primary Contact Name',
				'^.+$',	//	Non-optional
				$org->contact_name
			),
			new TextFormElement(
				'contact_title',
				'Primary Contact Title',
				'',	//	Optional
				$org->contact_title
			),
			new TextFormElement(
				'contact_email',
				'Primary Contact E-Mail',
				'^[^@]+@[^@]+\\.[^@]+$',	//	E-mails can't be encapsulated by regexes, but here's some basic requirements
				$org->contact_email
			),
			new TextFormElement(
				'contact_phone',
				'Primary Contact Phone',
				'^[\\d\\-\\s\\(\\)\\+]+$',	//	Non-optional
				$org->contact_phone
			),
			new TextFormElement(
				'contact_fax',
				'Primary Contact Fax',
				'^[\\d\\-\\s\\(\\)\\+]*$',	//	Optional but with character restrictions
				$org->contact_fax
			),
			new TextFormElement(
				'secondary_contact_name',
				'Secondary Contact Name',
				'^.+$',	//	Non-optional
				$org->contact_name
			),
			new TextFormElement(
				'secondary_contact_title',
				'Secondary Contact Title',
				'',	//	Optional
				$org->contact_title
			),
			new TextFormElement(
				'secondary_contact_email',
				'Secondary Contact E-Mail',
				'^[^@]+@[^@]+\\.[^@]+$',	//	E-mails can't be encapsulated by regexes, but here's some basic requirements
				$org->contact_email
			),
			new TextFormElement(
				'secondary_contact_phone',
				'Secondary Contact Phone',
				'^[\\d\\-\\s\\(\\)\\+]+$',	//	Non-optional
				$org->contact_phone
			),
			new TextFormElement(
				'secondary_contact_fax',
				'Secondary Contact Fax',
				'^[\\d\\-\\s\\(\\)\\+]*$',	//	Optional but with character restrictions
				$org->contact_fax
			),
			//	If the user is a sitewide
			//	administrator they can enable
			//	and disable organizations
			($user->type==='admin')
				?	new CheckBoxFormElement(
						'enabled',
						$org->enabled,
						'Enabled'
					)
				:	new TextElement(
						'Enabled',
						$org->enabled ? 'Yes' : 'No'
					)
			,
			//	If the user is a sitewide
			//	administrator they can make
			//	organizations "perpetual"
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
			,
			new SubmitFormElement(
				'Submit'
			)
		);
		$form=new Form('','POST',$elements);
		
		
		//	Was this request a POST?
		if (
			($_SERVER['REQUEST_METHOD']==='POST') &&
			($_SERVER['CONTENT_TYPE']==='application/x-www-form-urlencoded') &&
			($request->GetQueryString(LOGIN_KEY)!==TRUE_STRING)
		) {
		
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
			
			//	Set the ID
			$arr['id']=$org->id;
			
			//	Do a quick scan to enforce null
			//	vs. empty string
			foreach ($arr as &$value) {
			
				if (is_string($value)) {
				
					$value=MBString::Trim($value);
					
					if ($value==='') $value=null;
				
				}
			
			}
			
			//	Process Country/Province/State
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
			$temp=new Organization($arr);
			$temp->Save();
		
		}
		
		
		$template=new Template(WHERE_TEMPLATES);
		$template->form=$form;
		
		
		Render($template,'user_organization_form.phtml');
	
	}
	

?>