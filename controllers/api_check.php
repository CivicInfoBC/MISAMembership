<?php


	//	A "check" request shall be as follows:
	//
	//	{
	//		"action":	"check",
	//		"email":	<E-Mail to check>
	//	}
	//
	//	or
	//
	//	{
	//		"action":	"check"
	//		"domain":	<E-Mail or domain to check>
	//	}
	//
	//	And the response shall be:
	//
	//	{
	//		"exists":			true/false
	//		//	If "exists" is true:
	//		"good_standing":	true/false
	//		//	If "good_standing" is false:
	//		"code":
	//		"reason":
	//		"bypass":
	//	}
	
	
	function check ($user) {
	
		//	Determine whether specified user is
		//	in good standing
		$good_standing=$user->Check($code);
		
		$retr=array();
		
		$retr['good_standing']=$good_standing;
		
		if (!$good_standing) {
		
			$retr['code']=$code;
			$retr['reason']=LoginAttempt::GetReason($code);
			$retr['bypass']=$user->type==='admin';
		
		}
		
		return $retr;
	
	}
	
	
	if (isset($api_request->email)) {
	
		//	Check e-mail
	
		//	Determine whether specified user
		//	exists or not
		$exists=!is_null(
			$user=User::GetByUsername($api_request->email)
		);
		
		$api_result=array('exists' => $exists);
		
		if ($exists) $api_result=array_merge($api_result,check($user));
	
	} else if (isset($api_request->domain)) {
	
		//	Check domain
		
		//	The domain may just be an e-mail, so remove
		//	the username and @ symbol
		$domain=preg_replace('/^.*@/u','',$api_request->domain);
		
		$conn=$dependencies['MISADBConn'];
		$query=sprintf(
			'SELECT * FROM `users` WHERE `email` LIKE \'%%@%s\'',
			preg_replace(
				'(\\%|_)',
				'\\$1',
				$conn->real_escape_string($domain)
			)
		);
		
		if (($query=$conn->query($query))===false) throw new \Exception($conn->error);
		
		if ($query->num_rows===0) {
		
			$api_result=array('exists' => false);
		
		} else {
		
			//	TODO: Possibly loop
		
			$user=new User(new MySQLRow($query));
			
			$api_result=array_merge(array('exists' => true),check($user));
		
		}
	
	} else {
	
		//	Request is just bad
	
		api_error(HTTP_BAD_REQUEST);
	
	}


?>