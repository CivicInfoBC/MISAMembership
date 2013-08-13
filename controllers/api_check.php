<?php


	//	A "check" request shall be as follows:
	//
	//	{
	//		"action":	"check",
	//		"email":	<E-Mail to check>
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


	//	Verify needed argument is present
	if (!isset($api_request->email)) api_error(HTTP_BAD_REQUEST);
	
	
	//	Determine whether specified user
	//	exists or not
	$exists=!is_null(
		$user=User::GetByUsername($api_request->email)
	);
	
	$api_result=array('exists' => $exists);
	
	if ($exists) {
	
		//	Determine whether specified user is
		//	in good standing
		$good_standing=$user->Check($code);
		
		$api_result['good_standing']=$good_standing;
		
		if (!$good_standing) {
		
			$api_result['code']=$code;
			$api_result['reason']=LoginAttempt::GetReason($code);
			$api_result['bypass']=$user->type==='admin';
		
		}
	
	}


?>