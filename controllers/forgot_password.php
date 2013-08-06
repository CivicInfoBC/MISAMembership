<?php


	$template=new Template(WHERE_TEMPLATES);
	
	
	$title='Reset Password';
	
	
	require_once(WHERE_PHP_INCLUDES.'form.php');
	require_once(WHERE_PHP_INCLUDES.'mail.php');
	require_once(WHERE_LOCAL_PHP_INCLUDES.'settings.php');
	
	
	//	There are four paths through
	//	this controller:
	//
	//	1.	User clicks on the link on the login
	//		page, indicating that they've forgotten
	//		their password.
	//	2.	User types in their username/e-mail
	//		and submits to generate a password reset
	//		e-mail.
	//	3.	User clicks on the link in the password
	//		reset e-mail.
	//	4.	User chooses a new password.
	
	
	//	There are two separate forms involved:
	//
	//	One for entering a username/e-mail.
	//
	//	One for entering a new password.
	
	
	if (
		is_post() ||
		!(
			is_null($request->GetQueryString('username')) ||
			is_null($request->GetQueryString('key'))
		)
	) {
	
		//	This is a request for the password
		//	form
		
		$elements=array(
			new ChangePasswordFormElement(
				'password',
				'New Password'
			),
			new SubmitFormElement(
				'Submit'
			),
			new HiddenFormElement(
				'username',
				$request->GetQueryString('username')
			),
			new HiddenFormElement(
				'key',
				$request->GetQueryString('key')
			)
		);
		$form=new Form('','POST',$elements);
		
		//	Handle post back
		if (is_post()) {
		
			$form->Populate();
			
			if (!$form->Verify()) error(HTTP_BAD_REQUEST);
			
			$arr=$form->GetValues();
			
			if (
				is_null($reset_user=User::GetByUsername($arr['username'])) ||
				is_null($arr['key']) ||
				($arr['key']!==$reset_user->activation_key)
			) error(HTTP_BAD_REQUEST);
			
			$reset_user->SetPassword($arr['password']);
			$reset_user->ClearActivationKey();
			
			Render($template,'password_reset.phtml');
		
		} else {
		
			$template->form=$form;
		
			Render($template,'form.phtml');
		
		}
	
	} else {
	
		//	This is a request for the username
		//	form which will generate the e-mail
		
		$elements=array(
			new TextFormElement(
				'username',
				'Username/E-Mail',
				'^.+$'	//	Non-optional
			),
			new SubmitFormElement(
				'Submit'
			)
		);
		$form=new Form('','GET',$elements);
		
		//	This form is submitted using GET
		//	so detect the form's key
		if (is_null($request->GetQueryString('username'))) {
		
			//	Regular request, render form
			
			$template->form=$form;
			
			Render($template,'form.phtml');
		
		} else {
		
			//	Attempt to process
			
			$form->Populate();
			
			if (!$form->Verify()) error(HTTP_BAD_REQUEST);
			
			$arr=$form->GetValues();
			
			if (is_null($reset_user=User::GetByUsername($arr['username']))) {
			
				//	User entered a username which does
				//	not exist, notify them and stop
				//	processing
			
				$template->messages=array();
				$template->messages[]='User does not exist';
				
				$template->form=$form;
				
				Render($template,'form.phtml');
				
			} else {
			
				//	Proceed to generate the activation
				//	key and send the e-mail
			
				$reset_user->GenerateActivationKey();
				
				//	Send e-mail
				$template->user=$reset_user;
				$mail=new EMail();
				$mail->to=$reset_user->email;
				$mail->from=GetSettingValue(GetSetting('mail_from'));
				$mail->subject='Password Reset';
				$mail->is_html=true;
				$mail->Send(
					$template,
					array(
						'email.phtml',
						'password_reset_email.phtml'
					)
				);
				
				Render($template,'password_reset_sent.phtml');
				
			}
		
		}
	
	}


?>