<?php


	//	User must be administrator
	if ($user->type!=='admin') error(HTTP_FORBIDDEN);
	
	
	require_once(WHERE_PHP_INCLUDES.'mail.php');
	require_once(WHERE_LOCAL_PHP_INCLUDES.'settings.php');
	
	
	$title='Pending Membership Applications';
	
	
	//	Handle approvals
	if (is_post()) {
	
		foreach ($_POST as $key=>$value) {
		
			if (is_integer($key)) {
			
				$org=Organization::GetByID($key);
				
				if ($value==='approve') {
					
					echo('approved');
				
					if (!is_null($org) && is_null($org->enabled)) {
					
						$org->enabled=true;
						$org->Save();
						
						//	Send email to primary
						//	and secondary organization
						//	contacts
						$email=new EMail();
						$email->to=array($org->contact_email);
						if (!is_null($org->secondary_contact_email)) $email->to[]=$org->secondary_contact_email;
						$email->is_html=true;
						$email->subject='MISA Membership Approved';
						$email->from=GetSettingValue(GetSetting('mail_from'));
						$email_template=new Template(WHERE_TEMPLATES);
						$email_template->organization=$org;
						$email->Send(
							$email_template,
							array(
								'email.phtml',
								'email_membership_application_approved.phtml'
							)
						);
					
					}
					
				} else if ($value==='delete') {
					
					$org->Delete();
					
				}
			
			}
			
		}
	
	}
	
	
	$template=new Template(WHERE_TEMPLATES);
	$template->results=Organization::GetPage(
		null,
		null,
		'`name` ASC',
		Organization::GetPendingQuery()
	);
	
	
	Render($template,'pending.phtml');


?>