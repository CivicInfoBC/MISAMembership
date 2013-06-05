<?php


	//	Load configuration
	require_once('./config.php');
	
	
	//	PHP includes support
	//	if not provided by
	//	config.php
	if (!defined('WHERE_PHP_INCLUDES')) define('WHERE_PHP_INCLUDES','./incphp/');
	//	This variable is used by
	//	some older includes
	$WherePHPIncludes=WHERE_PHP_INCLUDES;
	
	
	//	Define support
	//
	//	Allows configuration options to
	//	be set without causing errors
	//	or overriding settings set in
	//	config.php
	require_once(WHERE_PHP_INCLUDES.'define.php');
	
	
	//	Set defaults (doesn't overrite
	//	configuration settings defined
	//	in config.php
	def('SITE_ROOT','./');
	def('WHERE_CONTROLLERS','./controllers/');
	def('WHERE_TEMPLATES','./templates/');
	def('WHERE_JAVASCRIPT','./javascript/');
	def('WHERE_STYLESHEETS','./styles/');
	def('WHERE_IMAGES','./images/');
	def('NO_LOGIN_CONTROLLER','login.php');	//	Users who aren't logged in can only login
	def('DEFAULT_CONTROLLER','');	//	TDB
	def('WHERE_LOCAL_PHP_INCLUDES',WHERE_PHP_INCLUDES);
	def('DEBUG',false);
	def('TRUE_STRING','true');
	def('LOGIN_KEY','login');
	def('LOGOUT_KEY','logout');
	def('PASSWORD_KEY','password');
	def('USERNAME_KEY','username');
	
	
	//	Debugging settings
	if (DEBUG) {
	
		ini_set('display_errors','1');
		error_reporting(E_ALL|E_STRICT);
	
	}
	
	
	//	Include all required libraries
	require_once(WHERE_PHP_INCLUDES.'error.php');					//	Error reporting
	require_once(WHERE_PHP_INCLUDES.'mb.php');						//	Multi-byte string handling
	require_once(WHERE_PHP_INCLUDES.'template.php');				//	Templating engine
	require_once(WHERE_PHP_INCLUDES.'dependency_container.php');	//	Dependency handling/resolution
	require_once(WHERE_PHP_INCLUDES.'request.php');					//	Request/sit abstraction/encapsulation
	require_once(SITE_ROOT.'dependencies.php');						//	Definition of all dependencies
	require_once(WHERE_PHP_INCLUDES.'utils.php');					//	Misc utilities
	require_once(WHERE_PHP_INCLUDES.'html_element.php');			//	HTML element
	require_once(WHERE_PHP_INCLUDES.'user.php');					//	User/login abstraction/encapsulation
	
	
	//	Error handling
	try {
	
	
		//	Initialize dependency container
		//	which allows dependencies to be
		//	resolved and retrieved without
		//	the overhead of creating extraneous
		//	handles
		$dependencies=new DependencyContainer($dependency_map);
		
		//	Prepare template/rendering arguments
		
		//	An array of HTMLElement objects which
		//	shall be rendered in the header
		$header=array();
		
		//	A string representing the title
		//	of this page
		$title=null;
		
		//	Setup request object
		
		//	First we check to see if the pre-rewriting
		//	URL is available
		//
		//	If it's not, we error out
		if (!(isset($_SERVER[ORIGINAL_URL_SERVER]) || isset($GET[ORIGINAL_URL_GET]))) error(HTTP_BAD_REQUEST);
		
		//	Now that we're assured the original
		//	URL is available, we can create a request
		//	object based on that URL
		$request=new Request(
			(
				isset($_SERVER[ORIGINAL_URL_SERVER])
					?	'http'.(
							!(empty($_SERVER['HTTPS']) || ($_SERVER['HTTPS']==='off'))
								?	's'
								:	''
						).'://'.$_SERVER['HTTP_HOST'].$_SERVER[ORIGINAL_URL_SERVER]
					:	$_GET[ORIGINAL_URL_GET]
			),
			SITE_ROOT_URL
		);
		
		//	Handle user login/session/logout
		
		//	Allows login/logout events
		//	to be conveniently detected
		//	and presented by later code
		$login=false;
		$logout=false;
		
		//	Login?
		if (
			//	Check for the GET key that indicates
			//	a login should take place
			MBString::Compare(
				$request->GetQueryString(LOGIN_KEY),
				TRUE_STRING
			) &&
			//	Check to make sure POST variable
			//	exist (i.e. this is a POST request)
			isset($_POST) &&
			//	Check for username POST variable
			isset($_POST[USERNAME_KEY]) &&
			//	Check for password POST variable
			isset($_POST[PASSWORD_KEY])
		) {
		
			$login=true;
		
			//	Execute login
			$user=User::Login(
				$_POST[USERNAME_KEY],
				$_POST[PASSWORD_KEY]
			);
		
		//	Logout?
		} else if (MBString::Compare(
			$request->GetQueryString(LOGOUT_KEY),
			TRUE_STRING
		)) {
		
			$logout=true;
			
			User::Logout();
			
			$user=null;
		
		//	Attempt to resume session
		} else {
		
			$user=User::Resume();
		
		}
		
		
		var_dump($user);
		
		
	} catch (Exception $e) {
	
		//	Print actual error if debug is on
		if (DEBUG) error(
			HTTP_INTERNAL_SERVER_ERROR,
			$e->message
		);
		//	Otherwise just print a generic
		//	error message
		else error(HTTP_INTERNAL_SERVER_ERROR);
	
	}


?>