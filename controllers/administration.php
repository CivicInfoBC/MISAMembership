<?php


	//	Make sure user is authorized to
	//	access/use administration
	//	tools
	if ($user->type!=='admin') error(HTTP_FORBIDDEN);
	
	
	$title='Administration';
	
	
	$template=new Template(WHERE_TEMPLATES);
	Render(
		$template,
		'administration.phtml'
	);


?>