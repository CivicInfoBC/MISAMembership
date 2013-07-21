<?php


	//	User must be administrator
	if ($user->type!=='admin') error(HTTP_FORBIDDEN);
	
	
	$title='Pending Membership Applications';
	
	
	//	Handle approvals
	if (is_post()) {
	
		foreach ($_POST as $key=>$value) {
		
			if (is_integer($key)) {
			
				$org=Organization::GetByID($key);
				
				if (!is_null($org) && is_null($org->enabled)) {
				
					$org=new Organization(
						array(
							'id' => $org->id,
							'enabled' => true
						)
					);
					$org->Save();
				
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