<?php


	//	Only administrators can use
	//	this controller
	if ($user->type!=='admin') error(HTTP_FORBIDDEN);
	
	
	$conn=$dependencies['MISADBConn'];
	
	
	//	Try and get the latest
	//	membership year from the
	//	database
	if (($query=$conn->query(
		'SELECT * FROM `membership_years` ORDER BY `end` DESC LIMIT 1'
	))===false) throw new Exception($conn->error);
	
	$create_year=(
		($query->num_rows===0)
			//	If there are no membership
			//	years in the database, default
			//	to creating a membership year
			//	for the present year
			?	intval(date('Y'))
			:	intval(MySQLRow::FetchObject($query)->end->format('Y'))+1
	);
	
	
	if (is_post()) {
	
		//	A POST back means that the
		//	user wishes to create this
		//	year
		if ($conn->query(
			sprintf(
				'INSERT INTO `membership_years` (
					`name`,
					`start`,
					`end`
				) VALUES (
					\'%1$s\',
					\'%1$s-01-01 00:00:00\',
					\'%1$s-12-31 23:59:59\'
				)',
				$conn->real_escape_string($create_year)
			)
		)===false) throw new Exception($conn->error);
		
		//	Now they'll be creating the next
		//	year...
		++$create_year;
		
	}
	
	
	$title='Add Membership Year';
	
	$template=new Template(WHERE_TEMPLATES);
	$template->create_year=$create_year;
	
	Render($template,'membership_year.phtml');
	

?>