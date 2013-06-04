<?php


	//	Load configuration
	require_once('./config.php');
	
	
	//	PHP includes support
	//	if not provided by
	//	config.php
	if (!defined('WHERE_PHP_INCLUDES')) define('WHERE_PHP_INCLUDES','./incphp/');
	
	
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
	def('DEFAULT_CONTROLLER','login.php');
	def('WHERE_LOCAL_PHP_INCLUDES',WHERE_PHP_INCLUDES);
	def('DEBUG',false);
	
	
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
	
	


?>