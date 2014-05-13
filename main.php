<?php


	//	Shim to stop errors
	if ($_SERVER['HTTP_X_ORIGINAL_URL']==='/favicon.ico') exit();


	function Render ($inner_template, $file) {
	
		global $request;
		global $top_menu;
		global $background_url;
		
		//	Get top menu via API
		$top_menu=GetTopMenu();
		//	Get background via API
		$background_url=GetBackgroundURL();
		
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
	
	
	function is_post () {
	
		global $request;
	
		return (
			($_SERVER['REQUEST_METHOD']==='POST') &&
			($_SERVER['CONTENT_TYPE']==='application/x-www-form-urlencoded') &&
			isset($_POST)
		);
	
	}
	
	
	function fetch_post ($key) {
	
		if (!isset($_POST[$key])) return null;
		
		$temp=MBString::Trim($_POST[$key]);
		
		if ($temp==='') return null;
		
		return $temp;
	
	}
	
	
	function fetch_post_int ($key) {
	
		$temp=fetch_post($key);
		
		if (!(
			is_numeric($temp) &&
			(($temp_int=intval($temp))==floatval($temp))
		)) return null;
		
		return $temp_int;
	
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
	def('PAYMENT_CONTROLLER','payment.php');	//	Controller for paying membership dues
	def('API_ARG','api');	//	Handled during pre-routing so API calls don't get GUI login functionality
	def('WHERE_LOCAL_PHP_INCLUDES',WHERE_PHP_INCLUDES);
	def('DEBUG',false);
	def('TRUE_STRING','true');
	def('FALSE_STRING','false');
	def('ALL_STRING','all');
	def('LOGIN_KEY','login');
	def('LOGOUT_KEY','logout');
	def('PASSWORD_KEY','password');
	def('USERNAME_KEY','username');
	def('REMEMBER_ME_KEY','remember_me');
	def('SESSION_KEY_KEY','session_key');	//	Key in the query string for cross-site login
	
	
	//	Debugging settings
	if (DEBUG) {
	
		ini_set('display_errors','1');
		error_reporting(E_ALL|E_STRICT);
	
	}
	
	
	//	Set cache settings
	header('Cache-Control: private,must-revalidate,max-age=0');
	
	
	//	Include all required libraries
	require_once(WHERE_PHP_INCLUDES.'error.php');					//	Error reporting
	require_once(WHERE_PHP_INCLUDES.'mb.php');						//	Multi-byte string handling
	require_once(WHERE_PHP_INCLUDES.'template.php');				//	Templating engine
	require_once(WHERE_PHP_INCLUDES.'dependency_container.php');	//	Dependency handling/resolution
	require_once(WHERE_LOCAL_PHP_INCLUDES.'request.php');			//	Request abstraction/encapsulation
	require_once(SITE_ROOT.'dependencies.php');						//	Definition of all dependencies
	require_once(WHERE_PHP_INCLUDES.'utils.php');					//	Misc utilities
	require_once(WHERE_PHP_INCLUDES.'html_element.php');			//	HTML element
	require_once(WHERE_LOCAL_PHP_INCLUDES.'user.php');				//	User/login abstraction/encapsulation
	require_once(WHERE_LOCAL_PHP_INCLUDES.'top_menu.php');			//	Top menu JSON consumer
	require_once(WHERE_LOCAL_PHP_INCLUDES.'background.php');		//	Random backgroundp API wrapper
	
	
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
			
			//	Initialize variables
			$user=null;
			$verbose=false;
			
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
					$_POST[PASSWORD_KEY],
					isset($_POST[REMEMBER_ME_KEY]) && ($_POST[REMEMBER_ME_KEY]===TRUE_STRING)
				);
				
				$verbose=true;
				
			//	User is logging out
			} else if ($request->GetQueryString(LOGOUT_KEY)===TRUE_STRING) {
			
				User::Logout();
			
			//	Attempt to regenerate a session from the query
			//	string
			} else if (!is_null($request->GetQueryString(SESSION_KEY_KEY))) {
			
				$user=User::Resume($request->GetQueryString(SESSION_KEY_KEY));
				
				if (!is_null($user->user)) User::SetCookie($request->GetQueryString(SESSION_KEY_KEY));
				
				unset($_GET[SESSION_KEY_KEY]);
				
				header(
					'Location: '.$request->MakeLink(
						$request->GetController(),
						$request->GetArgs(),
						$_GET
					)
				);
				
				exit();
			
			//	Attempt to regenerate a session from cookies
			} else {
			
				$user=User::Resume();
			
			}
			
			//	Handle login results
			if (isset($user)) {
			
				//	Login successful
				if ($user->code===0) {
				
					$user=$user->user;
				
				//	Login unsuccessful or conditionally
				//	successful
				
				//	Login didn't succeed because
				//	username, password, or session
				//	key was wrong
				} else if (
					($user->code===1) ||
					($user->code===5) ||
					($user->code===6)
				) {
				
					goto login_failure;
					
				//	Login didn't succeed because user
				//	is disabled et cetera
				
				//	Admins can login anyway
				} else if ($user->user->type==='admin') {
				
					$user=$user->user;
				
				//	BUSINESS LOGIC:
				//
				//	Users/organizations/etc. get a one year
				//	"grace period".  I.e. in the calendar year
				//	after their last payment they can login
				//	whether their organization has paid for that
				//	year or not.
				} else if ($user->code===4) {
				
					//	Get amount of time since last payment
					$last_payment=$user->user->organization->UnpaidDuration();
					
					//	If organization has never paid, disallow login,
					//	otherwise only disallow login if it's been over
					//	a year since the last paid year ended
					if (
						is_null($last_payment) ||
						($last_payment>(60*60*24*365))
					) {
					
						//	If the user is a superuser, they may
						//	login, but are "jailed" to the payment
						//	page
						if ($user->user->type==='superuser') {
						
							$user=$user->user;
							
							require(WHERE_CONTROLLERS.PAYMENT_CONTROLLER);
						
							exit();
						
						//	Otherwise they cannot login
						} else {
						
							goto login_failure;
						
						}
					
					} else {
					
						$user=$user->user;
					
					}
				
				} else {
				
					login_failure:
				
					if ($verbose) $login_message=$user->reason;
					
					$user=null;
				
				}
			
			}
			
			//	Redirect logins to get rid of
			//	spurious query string entry
			if (!(is_null($user) || is_null($request->GetQueryString(LOGIN_KEY)))) {
			
				unset($_GET[LOGIN_KEY]);
				
				header(
					'Location: '.$request->MakeLink(
						$request->GetController(),
						$request->GetArgs(),
						$_GET
					)
				);
			
				exit();
			
			}
			
			//	Should a prompt for the user to pay be
			//	displayed?
			$display_payment_prompt=!(
				is_null($user) ||
				!(
					($user->type==='admin') ||
					($user->type==='superuser')
				) ||
				is_null($user->organization) ||
				$user->organization->perpetual ||
				(count($user->organization->UnpaidYears())===0) ||
				($request->GetController()==='payment')
			);
			
			//	Route
			if (
				is_null($request->GetController()) ||
				!isset($routes[$request->GetController()])
			) {
			
				//	Default route
				
				$request->NoController();
				
				if ($fallback_route_auth && is_null($user)) {
				
					//	Force login
					require(WHERE_CONTROLLERS.LOGIN_CONTROLLER);
				
				} else {
				
					require(WHERE_CONTROLLERS.$fallback_route);
				
				}
			
			} else {
			
				$route=$routes[$request->GetController()];
				$auth_required=true;
				
				if (is_array($route)) {
				
					if (isset($route['auth'])) $auth_required=$route['auth'];
					
					$route=$route['route'];
				
				}
				
				if ($auth_required && is_null($user)) {
				
					//	Force login
					require(WHERE_CONTROLLERS.LOGIN_CONTROLLER);
				
				} else {
				
					require(WHERE_CONTROLLERS.$route);
				
				}
			
			}
		
		}
		
		
	} catch (Exception $e) {
	
		error(
			HTTP_INTERNAL_SERVER_ERROR,
			$e->getMessage()
		);
	
	}


?>