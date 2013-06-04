<?php


	require_once(WHERE_PHP_INCLUDES.'mb.php');


	/**
	 *	Represents a request to the site by
	 *	encapsulating/parsing the arguments
	 *	to this request.
	 */
	class Request {
	
	
		private $args;
		private $controller;
		private $root;
		private $url;
		
		
		private static function match ($target, $subject) {
		
			return mb_eregi('^'.MBString::RegexEscape($target).'$',$subject);
		
		}
	
	
		/**
		 *	Creates a new request object.
		 *
		 *	\param [in] $url
		 *		The URL used to perform this
		 *		request.
		 *	\param [in] $root
		 *		A string specifying the URL to
		 *		the root of this site.
		 */
		public function __construct ($url, $root) {
		
			//	Init
			$this->controller=null;
			$this->args=array();
			$this->root=$root;
			$this->url=$url;
			
			//	Determine the number of leading
			//	groups to skip in the URL
			if (!mb_ereg_search_init($root)) throw new Exception('Regex engine error');
			
			$leading_groups=0;
			while (mb_ereg_search_regs('(?:(?<=\\/)([^\\/\\?]+)(?=\\/|$|\\?))+')) ++$leading_groups;
			
			//	Parse out URL
			if (!mb_ereg_search_init($url)) throw new Exception('Regex engine error');
			
			$args=array();
			$group_count=0;
			while ($match=mb_ereg_search_regs('(?:(?<=\\/)([^\\/\\?]+)(?=\\/|$|\\?))+')) {
			
				//	Skip leading groups
				if ($group_count<$leading_groups) {
				
					++$group_count;
					continue;
				
				}
				
				//	Skip all zero-length
				if (MBString::Length($match[1])===0) continue;
				
				//	Add argument
				$args[]=$match[1];
			
			}
			
			//	Is there a controller?
			if (count($args)>=1) $this->controller=$args[0];
			
			//	Copy args that aren't the controller
			for ($i=1;$i<count($args);++$i) $this->args[]=$args[$i];
		
		}
		
		
		/**
		 *	Returns the name of the controller
		 *	to which this request ought to be
		 *	routed, or \em null if the request
		 *	did not specify a controller.
		 */
		public function GetController () {
		
			return $this->controller;
		
		}
		
		
		/**
		 *	Determines whether or not this request
		 *	is for the home page.
		 *
		 *	Requests without a specified controller
		 *	are assumed to be requests for the home
		 *	page.
		 *
		 *	Requests for a page under the root directory
		 *	which begin with "index." or "default." are
		 *	assumed to be requests for the home page.
		 *
		 *	\return
		 *		\em true if this is a request for
		 *		the home page, \em false otherwise.
		 */
		public function IsHome () {
		
			return is_null($this->controller);
		
		}
		
		
		/**
		 *	Determines whether or not an argument
		 *	was specified.
		 *
		 *	\param [in] $offset
		 *		The zero-relative index of
		 *		the argument to retrieve.
		 *
		 *	\return
		 *		\em true if the specified
		 *		argument exists, \em false
		 *		otherwise.
		 */
		public function ArgExists ($offset) {
		
			return isset($this->args[$offset]);
		
		}
		
		
		/**
		 *	Retrieves an argument.
		 *
		 *	\param [in] $offset
		 *		The zero-relative index
		 *		of the argument to retrieve.
		 *
		 *	\return
		 *		The argument specified by
		 *		\em offset, or \em null if
		 *		the argument was not specified.
		 */
		public function GetArg ($offset) {
		
			if ($this->ArgExists($offset)) return $this->args[$offset];
			
			return null;
		
		}
		
		
		/**
		 *	Determines whether or not a given query
		 *	string key has an associated value.
		 *
		 *	\param [in] $offset
		 *		The key to query.
		 *
		 *	\return
		 *		\em true if the specified key has
		 *		a value, \em false otherwise.
		 */
		public function QueryStringExists ($offset) {
		
			return isset($_GET[$offset]) && (MBString::Trim($_GET[$offset])!=='');
		
		}
		
		
		/**
		 *	Retrieves an element from the query
		 *	string.
		 *
		 *	\param [in] $offset
		 *		The key of the element to retrieve.
		 *
		 *	\return
		 *		The argument specified by \em offset,
		 *		or \em null if the argument was not
		 *		specified.
		 */
		public function GetQueryString ($offset) {
		
			if ($this->QueryStringExists($offset)) return $_GET[$offset];
			
			return null;
		
		}
		
		
		private function get_root () {
		
			$returnthis=mb_eregi('^https',$this->url) ? mb_ereg_replace(
				'^http(?!s)',
				'https',
				$this->root,
				'msri'
			) : $this->root;
			
			if (!mb_eregi('\/$',$returnthis)) $returnthis.='/';
			
			return $returnthis;
		
		}
		
		
		private static function flatten_query_string ($query_string) {
		
			if (is_null($query_string) || !is_array($query_string)) return '';
			
			$returnthis='';
			
			$first=true;
			foreach ($query_string as $key=>$value) {
			
				if ($first) {
				
					$first=false;
					
					$returnthis.='?';
				
				} else {
				
					$returnthis.='&';
				
				}
				
				$returnthis.=rawurlencode($key).'='.rawurlencode($value);
			
			}
			
			return $returnthis;
		
		}
		
		
		/**
		 *	Creates a link to a page.
		 *
		 *	Combines the root URL with the
		 *	leading argument given to this
		 *	page, as well as a controller name,
		 *	and arguments, te create a URL
		 *	that links to a given page.
		 *
		 *	\param [in] $controller
		 *		Name of the controller to
		 *		link to.  If not specified
		 *		the home page is assumed.
		 *	\param [in] $args
		 *		An item or array of arguments
		 *		to pass to \em controller
		 *		when and if the link is
		 *		followed.
		 *	\param [in] $query_string
		 *		An array whose keys and
		 *		values shall become the keys
		 *		and values of the query string
		 *		for the resultant URL.
		 *
		 *	\return
		 *		A string specifying a link
		 *		to the given controller with
		 *		the given arguments.
		 */
		public function MakeLink ($controller=null, $args=null, $query_string=null) {
		
			$link=$this->get_root();
			
			if (!is_null($this->leading_arg)) $link.=rawurlencode($this->leading_arg).'/';
			
			if (!is_null($controller)) {
			
				$link.=rawurlencode($controller).'/';
				
				if (!is_null($args)) {
				
					if (!is_array($args)) $args=array($args);
					
					foreach ($args as $x) $link.=rawurlencode(mb_ereg_replace('\.{0,2}/','',$x)).'/';
				
				}
				
			}
			
			$link.=self::flatten_query_string($query_string);
			
			return $link;
		
		}
		
		
		/**
		 *	Creates a link to a file.
		 *
		 *	\param [in] $path
		 *		An array of path elements
		 *		ending with the file
		 *		name itself.  If set to
		 *		\em null makes a link
		 *		to the home page.
		 *	\param [in] $query_string
		 *		An array whose keys and
		 *		values shall become the
		 *		keys and values of the
		 *		query string for the
		 *		resultant URL.
		 *
		 *	\return
		 *		A string specifying a
		 *		link to the given file.
		 */
		public function MakeFileLink ($path=null, $query_string=null) {
		
			$link=$this->get_root();
			
			if (!is_null($path)) {
			
				if (is_array($path)) {
				
					$first=true;
					foreach ($path as $x) {
					
						if ($first) {
						
							$first=false;
						
						} else {
						
							$link.='/';
						
						}
						
						$link.=rawurlencode(
							mb_ereg_replace(
								'\.{0,2}/',
								'',
								$x
							)
						);
					
					}
				
				} else {
				
					$link.=rawurlencode($path);
				
				}
			
			}
			
			$link.=self::flatten_query_string($query_string);
			
			return $link;
		
		}
		
		
		/**
		 *	Determines whether the connection is secure
		 *	sockets layer or not.
		 *
		 *	\return
		 *		\em true if this connection is secured
		 *		with secure sockets layer, \em false
		 *		otherwise.
		 */
		public function IsSSL () {
		
			return !(
				empty($_SERVER['HTTPS']) ||
				($_SERVER['HTTPS']==='off')
			);
		
		}
	
	
	}


?>