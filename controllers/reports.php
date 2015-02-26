<?php


	//	User must be a sitewide admin to view
	//	reports
	if ($user->type!=='admin') error(HTTP_FORBIDDEN);
	
	
	//	Reports
	$reports=array(
		'people' => (object)array(
			'columns' => array(
				'first_name' => 'First Name',
				'last_name' => 'Last Name',
				'email' => 'E-Mail',
				'title' => 'Title',
				'org_name' => 'Organization',
				'membership_type_name' => 'Membership Type'
			),
			'query' =>
				'SELECT
					`users`.*,
					`organizations`.`name` AS `org_name`,
					`membership_types`.`name` AS `membership_type_name`
				FROM
					`users` LEFT OUTER JOIN
					`organizations` ON `organizations`.`id`=`users`.`org_id` LEFT OUTER JOIN
					`membership_types` ON `organizations`.`membership_type_id`=`membership_types`.`id`'
		)
	);
	
	
	//	Retrieve requested report
	$name=$request->GetArg(0);
	if (is_null($name) || !isset($reports[$name])) error(HTTP_NOT_FOUND);
	$report=$reports[$name];
	
	
	$conn=$dependencies['MISADBConn'];
	if (($query=$conn->query($report->query))===false) throw new \Exception($conn->error);
	
	
	$template=new \Template(WHERE_TEMPLATES);
	$template->report=$report;
	$template->query=$query;
	
	
	$template->Render('csv.phtml');


?>