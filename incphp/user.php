<?php


	require_once(WHERE_PHP_INCLUDES.'mysqli_result.php');
	require_once(WHERE_PHP_INCLUDES.'bcrypt.php');
	require_once(WHERE_PHP_INCLUDES.'define.php');
	require_once(WHERE_PHP_INCLUDES.'mb.php');
	require_once(WHERE_PHP_INCLUDES.'crypto_random.php');
	require_once(WHERE_PHP_INCLUDES.'utils.php');
	require_once(WHERE_LOCAL_PHP_INCLUDES.'organization.php');
	
	
	//	Database dependency to use for logging
	//	users in et cetera
	def('USER_DB','MISADBConn');
	//	Number of rounds to use in bcrypt hashing
	//	of passwords
	def('BCRYPT_ROUNDS',12);
	//	The name of the cookie to use to store
	//	the login session secret
	def('LOGIN_COOKIE','misa_login_secret');
	//	When the login cookie will expire (unless
	//	$remember_me was set to false when calling
	//	the User::Login function, in which case it
	//	shall be 0, i.e. the session cookie shall
	//	be deleted when the user closes their
	//	browser
	def('LOGIN_COOKIE_EXPIRY',PHP_INT_MAX);
	//	The path within the domain that the cookie
	//	shall be available/sent to
	def('LOGIN_COOKIE_PATH','/');
	//	Whether the login cookie shall be limited
	//	to being sent over secure channels only.
	def('LOGIN_COOKIE_HTTPS',true);
	//	false if JavaScript etc. shall be allowed
	//	to access the cookie, true otherwise
	def('LOGIN_COOKIE_HTTP_ONLY',true);
	//	This must be set by user configuration,
	//	if it's not we cannot proceed
	if (!defined('LOGIN_COOKIE_DOMAIN')) error(HTTP_INTERNAL_SERVER_ERROR);
	
	
	/**
	 *	Communicates the status of a login
	 *	attempt.
	 */
	class LoginAttempt {
	
	
		/**
		 *	Maps reason codes to a human-readable
		 *	message.
		 */
		private static $codes=array(
			0 => 'Login successful',
			1 => 'Incorrect username or password',
			2 => 'Your account is disabled',
			3 => 'Your organization is disabled',
			4 => 'Your membership fees are unpaid',
			5 => 'No active session',
			6 => 'Invalid session'
		);
		/**
		 *	The User object which corresponds to this
		 *	login attempt, or \em null if there was
		 *	no matching User.
		 */
		public $user;
		/**
		 *	The code which represents the result of this
		 *	login attempt.
		 */
		public $code;
		/**
		 *	The reason string which corresponds to the
		 *	result of this login attempt.
		 */
		public $reason;
		
		
		/**
		 *	Creates a new LoginAttempt object.
		 *
		 *	\param [in] $code
		 *		The code which corresponds to the
		 *		result of the login attempt.
		 *	\param [in] $user
		 *		The user logged in (or not logged
		 *		in), if applicable.  If not applicable
		 *		set to \em null.  Defaults to \em null.
		 */
		public function __construct ($code, $user=null) {
		
			if (!in_array($code,array_keys(self::$codes),true)) throw new Exception('Unrecognized login code');
			
			$this->code=$code;
			$this->reason=self::$codes[$code];
			$this->user=$user;
		
		}
		
		
		/**
		 *	Converts this object to an array for
		 *	convenient JSON serialization.
		 *
		 *	\return
		 *		An associative array representing
		 *		this object.
		 */
		public function ToArray () {
		
			return array(
				'user' => is_null($this->user) ? null : $this->user->ToArray(),
				'code' => $this->code,
				'reason' => $this->reason
			);
		
		}
	
	
	}
	
	
	/**
	 *	Encapsulates a user, their corresponding organization
	 *	(if they have one), and defined functions for logging
	 *	users in and out.
	 */
	class User implements IteratorAggregate {
	
	
		private $user;
		private $org;
		private $session_key;
		private $session_expiry;
		
		
		/**
		 *	Hashes a password for insertion into
		 *	the database.
		 *
		 *	\param [in] $password
		 *		The plaintext password.
		 *
		 *	\return
		 *		A hash of that password.
		 */
		public static function PasswordHash ($password) {
		
			$bcrypt=new Bcrypt(BCRYPT_ROUNDS);
			
			return $bcrypt->hash($password);
		
		}
		
		
		/**
		 *	Creates a new user object.
		 *
		 *	\param [in] $user
		 *		A MySQLRow or array to wrap.
		 */
		public function __construct ($user=null) {
		
			if (!is_null($user)) {
			
				if (!(
					($user instanceof MySQLRow) ||
					is_array($user)
				)) throw new Exception('Type mismatch');
			
			}
		
			$this->user=$user;
			$this->org=null;
			$this->session_key=null;
		
		}
		
		
		/**
		 *	Saves this user's data to the database.
		 */
		public function Save () {
		
			//	If there's no ID, bail out
			if (!isset($this->user['id'])) throw new Exception('No ID');
		
			//	Get database access
			global $dependencies;
			$conn=$dependencies[USER_DB];
		
			//	Generate SET clause
			$set_clause='';
		
			foreach ($this->user as $field=>$value) {
			
				if ($field!=='id') {
				
					if ($set_clause!=='') $set_clause.=',';
					
					if (is_bool($value)) $value=$value ? 1 : 0;
					
					$set_clause.=sprintf(
						'`%s`=%s',
						preg_replace('/`/u','``',$field),
						is_null($value)
							?	'NULL'
							:	'\''.$conn->real_escape_string($value).'\''
					);
					
				}
				
			}
			
			//	Perform query against
			//	the database
			if ($conn->query(
				sprintf(
					'UPDATE `users` SET %s WHERE `id`=\'%s\'',
					$set_clause,
					$conn->real_escape_string($this->user['id'])
				)
			)===false) throw new Exception($conn->error);
		
		}
		
		
		/**
		 *	Checks to see if the user
		 *	account-in-question is able to login
		 *	independent of correct username/password.
		 *
		 *	The criteria used are:
		 *
		 *		-	To login a user's organization
		 *			must be enabled.
		 *		-	To login a user's organization
		 *			must not owe any dues.
		 *		-	To login a user must be
		 *			enabled.
		 *
		 *	Checks related to organization are
		 *	bypassed should the user not be a member
		 *	of an organization.
		 *
		 *	\param [in] $code
		 *		An option by reference parameter
		 *		which will communicate the reason
		 *		a check failed.
		 *
		 *	\return
		 *		\em true if the user may login,
		 *		\em false otherwise.
		 */
		public function Check (&$code=null) {
		
			//	Perform organization-related checks
			//	if applicable
			if (!is_null($this->org)) {
			
				if (!($this->org->has_paid || $this->org->perpetual)) {
				
					$code=4;
					
					return false;
				
				}
				
				if (!$this->org->enabled) {
				
					$code=3;
					
					return false;
				
				}
			
			}
			
			//	Check to make sure individual
			//	user account is enabled
			if ($this->enabled) {
			
				$code=0;
			
				return true;
			
			}
			
			$code=2;
			
			return false;
		
		}
		
		
		/**
		 *	Retrieves the user object from the database
		 *	that corresponds to a given user ID.
		 *
		 *	\param [in] $id
		 *		The ID of the user to retrieve.
		 *
		 *	\return
		 *		A user object representing the user
		 *		with \em id, or \em null if the user
		 *		could not be found.
		 */
		public static function GetByID ($id) {
		
			//	Guard against nulls
			if (is_null($id)) return null;
			
			//	Database access
			global $dependencies;
			$conn=$dependencies[USER_DB];
			
			//	Grab the user from the database
			$query=$conn->query(
				sprintf(
					'SELECT
						*
					FROM
						`users`
					WHERE
						`id`=\'%s\'',
					$conn->real_escape_string($id)
				)
			);
			
			//	Throw on error
			if ($query===false) throw new Exception($conn->error);
			
			//	If there are no rows,
			//	user doesn't exist
			if ($query->num_rows===0) return null;
			
			//	Grab the row
			$row=new MySQLRow($query);
			
			//	Create the object we'll return
			$user=new User();
			$user->user=$row;
			
			//	Grab the corresponding organization's
			//	information
			$user->org=Organization::GetByID($row['org_id']->GetValue());
			
			//	Return
			return $user;
		
		}
		
		
		/**
		 *	Attempts to log a user in and possibly
		 *	generate them a session.
		 *
		 *	\param [in] $username
		 *		The username the user supplied.
		 *	\param [in] $password
		 *		The password the user supplied.
		 *	\param [in] $remember_me
		 *		\em true if the user should be
		 *		remembered past the end of this
		 *		browser session, \em false if
		 *		they should be remembered only
		 *		until the end of this session,
		 *		and \em null if no session
		 *		should be generated for them
		 *		at all.  Defaults to \em true.
		 *	\param [in] $real_ip
		 *		The real IP the user is logging in
		 *		from, to be used if the user is
		 *		being logged in by a remote server
		 *		through the API.  This is to be set
		 *		to the actual IP of the client.
		 *		Defaults to \em null.
		 *
		 *	\return
		 *		A LoginAttempt object representing
		 *		the result of this login attempt.
		 */
		public static function Login ($username, $password, $remember_me=true, $real_ip=null) {
		
			//	Guard against nulls
			if (
				is_null($username) ||
				is_null($password)
			) return new LoginAttempt(1);
			
			//	Sanitize username, remove leading/
			//	trailing whitespace, make all
			//	lower case.
			$username=MBString::ToLower(
				MBString::Trim(
					$username
				)
			);
			
			//	Database access
			global $dependencies;
			$conn=$dependencies[USER_DB];
			
			//	Grab the user from the database
			$query=$conn->query(
				sprintf(
					'SELECT
						*
					FROM
						`users`
					WHERE
						`username`=\'%s\'',
					$conn->real_escape_string($username)
				)
			);
			
			//	Throw on error
			if ($query===false) throw new Exception($conn->error);
			
			//	Failure if there are no matching rows
			if ($query->num_rows===0) return new LoginAttempt(1);
			
			//	Extract row
			$row=new MySQLRow($query);
			
			//	We know user exists, check to
			//	see if password is correct
			
			$bcrypt=new Bcrypt(BCRYPT_ROUNDS);
			
			//	Due to legacy support, the database
			//	might contain either a bcrypted
			//	hashed and salted password, or
			//	an MD5 hashed password.
			//
			//	All bcrypt hashes begin with the \'$\'
			//	character, whereas MD5 hashes cannot
			//	contain the \'$\' character at all
			//	(as they're represented as hex), therefore
			//	we can tell which kind of hash in in
			//	the database by checking for a leading
			//	\'$\'.
			if (preg_match(
				'/^\\$/u',
				$row['password']
			)===0) {
			
				//	MD5 (legacy)
				
				//	Verify
				if (md5($password)!==$row['password']->GetValue()) return new LoginAttempt(1);
				
				//	The user-supplied password is correct,
				//	which means that $password contains
				//	the plaintext which hashes to the
				//	MD5 hash contained in the database.
				//
				//	MD5 is a weak hash, independent of any
				//	best practices, but without some kind
				//	of salt attacking the passwords is
				//	extremely easy.
				//
				//	bcrypt is a modern password hashing
				//	algorithm with salting built right in.
				//	It's considered secure.
				//
				//	Switch the legacy MD5 hash over to a
				//	modern bcrypt hash to improve the security
				//	of this user's login
				if ($conn->query(
					sprintf(
						'UPDATE
							`users`
						SET
							`password`=\'%s\'
						WHERE
							`username`=\'%s\'',
						$conn->real_escape_string(
							//	New bcrypt hash
							$bcrypt->hash($password)
						),
						$conn->real_escape_string($username)
					)
				)===false) throw new Exception($conn->error);
			
			} else {
			
				//	bcrypt (modern)
				
				//	Verify
				if (!$bcrypt->verify($password,$row['password']->GetValue())) return new LoginAttempt(1);
			
			}
			
			//	Now that the user is verified, create
			//	them an object and start filling in
			//	details
			$user=new User();
			$user->user=$row;
			
			//	Grab information about the user's
			//	organization
			$user->org=Organization::GetByID($row['org_id']->GetValue());
			
			//	Check to make sure user can
			//	login
			if (!$user->Check($code)) return new LoginAttempt(
				$code,
				$user
			);
			
			//	User is allowed to login
			
			//	Generate 128-bit cryptographically
			//	secure random number to use as the
			//	user's session key
			$user->session_key=bin2hex(CryptoRandom(128/8));
			
			//	Deduce the time at which the session
			//	key we just generated ought to
			//	expire
			$expiry=intval(time()+LOGIN_COOKIE_EXPIRY);
			//	Detect overflow and correct as best
			//	possible
			if ($expiry<0) $expiry=PHP_INT_MAX;
			//	Set
			$user->session_expiry=new DateTime();
			$user->session_expiry->setTimestamp($expiry);
			
			//	Create session in database
			if ($conn->query(
				sprintf(
					'INSERT INTO `sessions` (
						`key`,
						`user_id`,
						`created_ip`,
						`created`,
						`last_ip`,
						`last`,
						`expires`
					) VALUES (
						\'%1$s\',
						\'%2$s\',
						\'%3$s\',
						NOW(),
						\'%3$s\',
						NOW(),
						\'%4$s\'
					)',
					$conn->real_escape_string($user->session_key),
					$conn->real_escape_string($user->id),
					$conn->real_escape_string(
						is_null($real_ip)
							?	$_SERVER['REMOTE_ADDR']
							:	$real_ip
					),
					$conn->real_escape_string($user->session_expiry->format('Y-m-d H:i:s'))
				)	
			)===false) throw new Exception($conn->error);
			
			//	If $remember_me is null, bypass setting
			//	cookie
			if (!is_null($remember_me)) self::SetCookie($user->session_key,$remember_me);
			
			//	Return the user object and signal
			//	login success
			return new LoginAttempt(0,$user);
		
		}
		
		
		/**
		 *	Sends the user a session key.
		 *
		 *	\param [in] $session_key
		 *		The session key to set as a cookie
		 *		in the user's browser.
		 *	\param [in] $remember_me
		 *		Whether the user's login should be
		 *		remember past the end of this browsing
		 *		session.  Defaults to \em true.
		 */
		public static function SetCookie ($session_key, $remember_me=true) {
		
			//	Deduce the time at which the session
			//	key we just generated ought to
			//	expire
			$expiry=intval(time()+LOGIN_COOKIE_EXPIRY);
			//	Detect overflow and correct as best
			//	possible
			if ($expiry<0) $expiry=PHP_INT_MAX;
		
			//	If $remember_me is false, cookie
			//	expires when user closes browser
			if (!$remember_me) $expiry=0;
			
			//	Send the cookie
			if (!setcookie(
				LOGIN_COOKIE,
				$session_key,
				$expiry,
				LOGIN_COOKIE_PATH,
				LOGIN_COOKIE_DOMAIN,
				LOGIN_COOKIE_HTTPS,
				LOGIN_COOKIE_HTTP_ONLY
			)) throw new Exception('Could not set cookie');
		
		}
		
		
		/**
		 *	Attempts to resume a user's session.
		 *
		 *	\param [in] $session_key
		 *		The session key to attempt to
		 *		resume.  May be \em null in
		 *		which case the value (if any)
		 *		the client sent as a cookie
		 *		is used.
		 *	\param [in] $real_ip
		 *		The real IP the user is logging in
		 *		from, to be used if the user is
		 *		being logged in by a remote server
		 *		through the API.  This is to be set
		 *		to the actual IP of the client.
		 *		Defaults to \em null.
		 *
		 *	\return
		 *		A LoginAttempt object representing
		 *		the result of this login attempt.
		 */
		public static function Resume ($session_key=null, $real_ip=null) {
		
			//	Get the session key, regardless
			//	of where it's coming from
			if (is_null($session_key)) {
			
				if (isset($_COOKIE[LOGIN_COOKIE])) {
				
					$session_key=$_COOKIE[LOGIN_COOKIE];
				
				} else {
				
					return new LoginAttempt(5);
				
				}
			
			}
			
			//	Get database access
			global $dependencies;
			$conn=$dependencies[USER_DB];
			
			//	Attempt to retrieve user
			//	associated with session key
			$query=$conn->query(
				sprintf(
					'SELECT
						`users`.*
					FROM
						`sessions`,
						`users`
					WHERE
						`sessions`.`user_id`=`users`.`id` AND
						`sessions`.`key`=\'%s\' AND
						`expires`>NOW()',
					$conn->real_escape_string($session_key)
				)
			);
			
			//	Throw on error
			if ($query===false) throw new Exception($conn->error);
			
			//	If there are now rows that
			//	means the session is invalid
			if ($query->num_rows===0) return new LoginAttempt(6);
			
			//	Retrieve the user
			$user=new User();
			$user->user=new MySQLRow($query);
			
			//	Grab information about the user's
			//	organization (if any)
			$user->org=Organization::GetByID($user->user['org_id']->GetValue());
			
			//	Make sure user can login
			if (!$user->Check($code)) return new LoginAttempt(
				$code,
				$user
			);
			
			//	Update last login time
			$query=$conn->query(
				sprintf(
					'UPDATE
						`sessions`
					SET
						`last_ip`=\'%s\',
						`last`=NOW()
					WHERE
						`key`=\'%s\'',
					$conn->real_escape_string(
						is_null($real_ip)
							?	$_SERVER['REMOTE_ADDR']
							:	$real_ip
					),
					$conn->real_escape_string($session_key)
				)
			);
			
			if ($query===false) throw new Exception($conn->error);
			
			//	User is good, return success
			return new LoginAttempt(0,$user);
		
		}
		
		
		/**
		 *	Attempts to destroy a user's session.
		 *
		 *	\param [in] $session_key
		 *		The session key of the session
		 *		which is to be destroyed.  May be
		 *		\em null in which case the value
		 *		(if any) the client sent as a
		 *		cookie is used.  If \em null
		 *		this function will unset the
		 *		client's cookie as well.
		 */
		public static function Logout ($session_key=null) {
		
			//	If there's no session key
			//	at all, do nothing
			if (!(
				isset($session_key) ||
				isset($_COOKIE[LOGIN_COOKIE])
			)) return;
			
			//	Destroy the session in the
			//	database
			global $dependencies;
			$conn=$dependencies[USER_DB];
			
			if ($conn->query(
				sprintf(
					'DELETE FROM
						`sessions`
					WHERE
						`key`=\'%s\'',
					$conn->real_escape_string(
						isset($session_key)
							?	$session_key
							:	$_COOKIE[LOGIN_COOKIE]
					)
				)
			)===false) throw new Exception($conn->error);
			
			//	If the session key comes from
			//	a cookie rather than from a
			//	parameter, unset it
			if (!(
				isset($session_key) ||
				setcookie(
					LOGIN_COOKIE,
					'',
					1,
					LOGIN_COOKIE_PATH,
					LOGIN_COOKIE_DOMAIN,
					LOGIN_COOKIE_HTTPS,
					LOGIN_COOKIE_HTTP_ONLY
				)
			)) throw new Exception('Failed to unset cookie');
		
		}
		
		
		/**
		 *	Retrieves the user's name by substituting
		 *	into a format string.  The following
		 *	substitutions are made:
		 *
		 *	-	\'F\' becomes the user's first name.
		 *	-	\'L\' becomes the user's last name.
		 *	-	\'f\' becomes the user's first
		 *		initial.
		 *	-	\'l\' becomes the user's last
		 *		initial.
		 *	-	\'e\' becomes the user's e-mail address
		 *		case corrected to standard (all
		 *		e-mail addresses are lowercase by
		 *		definition and treated as such by
		 *		conforming MTAs.
		 *	-	\'E\' becomes the user's e-mail
		 *		address case corrected to upper case.
		 *	-	\'m\' becomes the user's e-mail
		 *		address without case correction.
		 *	-	\'U\' becomes the user's username
		 *		with initial case correction (i.e.
		 *		conversion of leading lowercase to
		 *		uppercase).
		 *	-	\'u\' becomes the user's username
		 *		in lowercase.
		 *	-	\'n\' becomes the user's username
		 *		without case correction.
		 *
		 *	All other characters are unaffected.
		 *
		 *	\param [in] $format
		 *		The format string into which substitutions
		 *		shall be made.  Defaults to \'F L\' which
		 *		shall output the user's full name with
		 *		case corrections.
		 *
		 *	\return
		 *		The formatted string.
		 */
		public function Name ($format='F L') {
		
			if (is_null($format)) return null;
		
			//	For capture by lambda
			$row=$this->user;
		
			return preg_replace_callback(
				'/[FLfleEmUun]/u',
				function ($matches) use ($row) {
				
					switch ($matches[0]) {
					
						case 'F':
							return FormatName($row['first_name']->GetValue());
						case 'f':
							//	Have to use preg_split otherwise
							//	code points represented by multiple
							//	code units will become mangled and
							//	illegal
							$split=preg_split(
								'/(?<!^)(?!$)/u',
								$row['first_name']->GetValue()
							);
							if (count($split)===0) return '';
							return MBString::ToUpper($split[0]);
						case 'L':
							return FormatName($row['last_name']->GetValue());
						case 'l':
							$split=preg_split(
								'/(?<!^)(?!$)/u',
								$row['last_name']->GetValue()
							);
							if (count($split)===0) return '';
							return MBString::ToUpper($split[0]);
						case 'e':
							return MBString::ToLower($row['email']->GetValue());
						case 'E':
							return MBString::ToUpper($row['email']->GetValue());
						case 'm':
							return $row['email']->GetValue();
						case 'U':
							return preg_replace_callback(
								'/^./u',
								function ($matches) {	return MBString::ToUpper($matches[0]);	},
								MBString::ToLower($row['username']->GetValue())
							);
						case 'u':
							return MBString::ToLower($row['username']->GetValue());
						case 'n':
							return $row['username']->GetValue();
						default:
							//	This should never happen,
							//	but just in case...
							return $matches[0];
					
					}
				
				},
				$format
			);
			
			
		}
		
		
		/**
		 *	Represents this object as an array.
		 *
		 *	\return
		 *		An associative array which represents
		 *		this object.
		 */
		public function ToArray () {
		
			$returnthis=array();
			
			$user=array();
			
			foreach ($this as $key=>$value) {
			
				$user[$key]=($value instanceof MySQLDatum) ? $value->GetValue() : $value;
			
			}
			
			$returnthis['user']=$user;
			$returnthis['organization']=is_null($this->org) ? null : $this->org->ToArray();
			$returnthis['session_key']=is_null($this->session_key) ? null : $this->session_key;
			$returnthis['session_expiry']=is_null($this->session_expiry) ? null : $this->session_expiry->format('Y,m,d,H,i,s');
			
			return $returnthis;
		
		}
		
		
		/**
		 *	Retrieves an iterator that can be used
		 *	to iterate the properties of this user.
		 *
		 *	\return
		 *		An iterator which traverses this
		 *		user.
		 */
		public function getIterator () {
		
			return $this->user->getIterator();
		
		}
		
		
		/**
		 *	Determines whether a given property of
		 *	this user is set.
		 *
		 *	\param [in] $name
		 *		The name of the property to check.
		 *
		 *	\return
		 *		\em true if the property is set,
		 *		\em false otherwise.
		 */
		public function __isset ($name) {
		
			switch ($name) {
			
				case 'organization':return isset($this->org);
				case 'session_key':return isset($this->session_key);
				case 'session_expiry':return isset($this->session_expiry);
			
			}
			
			if ($this->user instanceof MySQLRow) {
			
				return !(is_null($this->user[$name]) || is_null($this->user[$name]));
			
			} else {
			
				return isset($this->user[$name]);
			
			}
		
		}
		
		
		/**
		 *	Retrieves a certain property of this
		 *	user.
		 *
		 *	\param [in] $name
		 *		The name of the property to retrieve.
		 *
		 *	\return
		 *		The value of the requested property.
		 */
		public function __get ($name) {
		
			switch ($name) {
			
				case 'organization':return $this->org;
				case 'session_key':return $this->session_key;
				case 'session_expiry':return $this->session_expiry;
			
			}
			
			if (isset($this->user[$name])) {
			
				return ($this->user[$name] instanceof MySQLDatum)
					?	$this->user[$name]->GetValue()
					:	$this->user[$name];
				
			}
			
			return null;
		
		}
	
	
	}


?>