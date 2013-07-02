<?php



	function Render ($inner_template, $file) {
	
		global $request;
		
		$template=new Template(WHERE_TEMPLATES);
		
		$template->template=$inner_template;
		$template->file=$file;
		
		$template->Render('main.phtml');
	
	}
	
	
	function AddStylesheet ($stylesheet, $media=null) {
	
		global $header;
		global $request;
		
		$attributes=array(
			'rel' => 'stylesheet',
			'type' => 'text/css'
		);
		
		$link=array(WHERE_STYLESHEETS);
		
		if (is_array($stylesheet)) array_merge($link,$stylesheet);
		else $link[]=$stylesheet;
		
		$attributes['href']=$request->MakeFileLink($link);
		
		if (!is_null($media)) $attributes['media']=$media;
		
		$header[]=new HTMLElement(
			'link',
			$attributes
		);
	
	}
	
	
	function AddJavaScript ($script) {
	
		global $header;
		global $request;
		
		$link=array(WHERE_JAVASCRIPT);
		
		if (is_array($script)) array_merge($link,$script);
		else $link[]=$script;
		
		$element=new HTMLElement(
			'script',
			array(
				'type' => 'text/javascript',
				'src' => $request->MakeFileLink($link)
			)
		);
		$element->ExplicitClose=true;
		
		$header[]=$element;
	
	}


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
	def('LOGIN_CONTROLLER','login.php');	//	Users who aren't logged in can only login
	def('DEFAULT_CONTROLLER','');	//	TDB
	def('API_CONTROLLER','api.php');	//	Controller for API functions
	def('API_ARG','api');	//	Handled during pre-routing so API calls don't get GUI login functionality
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
	require_once(WHERE_LOCAL_PHP_INCLUDES.'request.php');			//	Request/sit abstraction/encapsulation
	require_once(SITE_ROOT.'dependencies.php');						//	Definition of all dependencies
	require_once(WHERE_PHP_INCLUDES.'utils.php');					//	Misc utilities
	require_once(WHERE_PHP_INCLUDES.'html_element.php');			//	HTML element
	require_once(WHERE_LOCAL_PHP_INCLUDES.'user.php');				//	User/login abstraction/encapsulation
	
	
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
		
		//	First we check to see if the pre-rewritten
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
		
		//	Pre-route -- detect API requests
		if (MBString::Compare(
			$request->GetController(),
			API_ARG
		)) {
		
			//	Hand off control to API controller
			require(WHERE_CONTROLLERS.API_CONTROLLER);
		
		//	Continue as normal
		} else {
		
			//	We must check the user's logged in
			//	status before deciding how to
			//	proceed
			
			//	User is logging in
			if (
				($request->GetQueryString(LOGIN_KEY)===TRUE_STRING) &&
				($_SERVER['REQUEST_METHOD']==='POST') &&
				isset($_POST) &&
				isset($_POST[PASSWORD_KEY]) &&
				isset($_POST[USERNAME_KEY])
			) {
			
				$user=User::Login(
					$_POST[USERNAME_KEY],
					$_POST[PASSWORD_KEY]
				);
				
				if ($user->code===0) {
				
					//	Login success
					$user=$user->user;
				
				} else {
				
					//	Login failure
					$login_message=$user->reason;
					
					$user=null;
				
				}
			
			//	User is logging out
			} else if ($request->GetQueryString(LOGOUT_KEY)===TRUE_STRING) {
			
				$user=null;
				
				User::Logout();
			
			//	Attempt to regenerate a session
			} else {
			
				$user=User::Resume();
				
				if ($user->code===0) {
				
					//	Login success
					$user=$user->user;
				
				} else {
				
					//	Login failure
					//
					//	Session-based logins fail
					//	silently to avoid poor
					//	user experience
					$user=null;
				
				}
			
			}
			
			//	Force login if user is not
			//	logged in
			if (is_null($user)) {
			
				require(WHERE_CONTROLLERS.LOGIN_CONTROLLER);
			
			//	Otherwise we may proceed with routing
			} else {
			
				if (
					is_null($request->GetController()) ||
					!isset($routes[$request->GetController()])
				) {
				
					$request->NoController();
					
					require(WHERE_CONTROLLERS.$fallback_route);
				
				} else {
				
					require(WHERE_CONTROLLERS.$routes[$request->GetController()]);
				
				}
			
			}
		
		}
		
		
	} catch (Exception $e) {
	
		if (DEBUG) error(HTTP_INTERNAL_SERVER_ERROR,$e->getMessage());
		else error(HTTP_INTERNAL_SERVER_ERROR);
	
	}


?>