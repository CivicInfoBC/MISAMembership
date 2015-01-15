<?php

	$title='Login';

	//	Render login page
	
	AddStylesheet('login.css');
	
	$template=new Template(WHERE_TEMPLATES);
	
	if (isset($login_message)) $template->message=$login_message;
	
	Render($template,'login.phtml');


?>