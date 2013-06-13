<?php


	//	An API login request shall
	//	be as follows:
	//
	//
	//	To log a user in from username
	//	and password:
	//
	//	{
	//		"action":		"login",
	//		"api_key":		<API key of API consumer>,
	//		"username":		<Username user supplied>,
	//		"password":		<Plaintext password user supplied>,
	//		"real_ip":		<IP request is being made from>
	//	}
	//
	//	To verify that a session key is
	//	valid, has not expired, and that
	//	the associated user is still
	//	enabled et cetera:
	//
	//	{
	//		"action":		"login",
	//		"api_key":		<API key of API consumer>,
	//		"session_key":	<Session key to verify>,
	//		"real_ip":		<IP request is being made from>
	//	}
	//
	//	To destroy a user's session:
	//
	//	{
	//		"action":		"logout",
	//		"api_key":		<API key of API consumer>,
	//		"session_key":	<Session key of session to destroy>
	//	}
	//
	//	The response to "login" requests shall be as
	//	follows:
	//
	//	{
	//		"code":			<0=Success,1-6=Failure>
	//		"reason":		<A string describing the reason for failure>
	//		"user":			{
	//							"user":			{
	//												//	Contains a collection of properties
	//												//	which share the name of their
	//												//	corresponding database fields
	//											}
	//							"organization":	{
	//												//	Contains a collection of properties
	//												//	which share the name of their
	//												//	corresponding database fields and...
	//												"has_paid":	<0 if organization has not paid for
	//															 this calendar year, 1 otherwise>
	//											}
	//							"session_key":		<Only set when creating a new session,
	//											 	 contains the key that should be sent
	//											 	 as a cookie to the client.>
	//							"session_expiry":	<Contains the date the session will
	//												 expire formatted as "Y,m,d,H,i,s",
	//												 only sent when creating a new session.>
	//						}
	//	}
	//
	//	The "logout" request shall send no reply.
	
	//	Branch depending on requested
	//	action:
	if ($api_request->action==='login') {
	
		//	Login
		
		//	Check shared field
		if (!isset($api_request->real_ip)) error(HTTP_BAD_REQUEST);
		
		//	Check individual fields
		//	and decide how to handle
		
		//	Username/password login
		if (
			isset($api_request->username) &&
			isset($api_request->password)
		) {
		
			//	Perform login
			$api_result=User::Login(
				$api_request->username,
				$api_request->password,
				null,
				$api_request->real_ip
			);
		
		//	Session regeneration
		} else if (isset($api_request->session_key)) {
		
			//	Perform session regeneration
			$api_result=User::Resume(
				$api_request->session_key
			);
		
		//	ERROR
		} else {
		
			error(HTTP_BAD_REQUEST);
		
		}
		
		//	Serialize
		$api_result=$api_result->ToArray();
	
	//	Logout
	//
	//	Verify fields
	} else if (isset($api_request->session_key)) {
	
		User::Logout($api_request->session_key);
	
	//	ERROR
	} else {
	
		error(HTTP_BAD_REQUEST);
	
	}


?>