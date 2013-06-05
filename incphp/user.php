<?php


	require_once(WHERE_PHP_INCLUDES.'mysqli_result.php');
	require_once(WHERE_PHP_INCLUDES.'bcrypt.php');
	require_once(WHERE_PHP_INCLUDES.'define.php');
	require_once(WHERE_PHP_INCLUDES.'mb.php');
	require_once(WHERE_PHP_INCLUDES.'crypto_random.php');
	require_once(WHERE_PHP_INCLUDES.'utils.php');
	
	
	//	Number of rounds to use in bcrypt hashing
	//	of passwords
	def('BCRYPT_ROUNDS',15);
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
	 *	Manages logging users in and out
	 *	and checking their sessions.
	 */
	class User implements arrayaccess {
	
	
		private $row;
		
		
		/**
		 *	Attempts to log a user in and
		 *	generate them a session.
		 *
		 *	\param [in] $username
		 *		The username the user supplied.
		 *	\param [in] $password
		 *		The password the user supplied.
		 *	\param [in] $remember_me
		 *		\em true if the user should be
		 *		remembered past the end of this
		 *		browser session, \em false
		 *		otherwise.  Defaults to \em true.
		 *
		 *	\return
		 *		A User object representing the user
		 *		on success, \em null otherwise.
		 */
		public static function Login ($username, $password, $remember_me=true) {
		
			//	Database access
			global $dependencies;
			$conn=$dependencies['MISADBConn'];
			
			//	Lowercase usernames and
			//	leading- and trailing-whitespace
			//	agnostic
			$username=MBString::ToLower(
				MBString::Trim(
					$username
				)
			);
			
			//	Select the appropriate user
			$query=$conn->query(
				sprintf(
					'SELECT * FROM `users` WHERE `username`=\'%s\'',
					$conn->real_escape_string($username)
				)
			);
			
			//	Throw on error
			if ($query===false) throw new Exception($conn->error);
			
			//	Failure if there are no
			//	matching rows
			if ($query->num_rows===0) return null;
			
			//	Extract row
			$row=new MySQLRow($query);
			
			//	Time to check the password
			
			$bcrypt=new Bcrypt(BCRYPT_ROUNDS);
			
			//	Does the database have a bcrypt or
			//	MD5 hashed password?
			//
			//	bcrypt hashes always begin with $2,
			//	whereas MD5 hashes can't contain
			//	the '$' character at all, so we
			//	check for that to tell the difference
			if (preg_match(
				'/^\\$/u',
				$row['password']
			)!==0) {
			
				//	bcrypt
				
				//	If hashed password from database
				//	and hashed password from user don't
				//	match, don't log them in
				if (!$bcrypt->verify($password,$row['password']->GetValue())) return null;
			
			} else {
			
				//	MD5
				
				//	Verify and fail if hashes
				//	don't match
				if (md5($password)!==$row['password']->GetValue()) return null;
				
				//	We now know that the password
				//	the user supplies is the plaintext
				//	which hashes to the MD5 hash
				//	from the database.
				//
				//	This means that we can transparently
				//	replace their MD5 hashed password
				//	with a new, more secure, bcrypted hash.
				$hash=$bcrypt->hash($password);
				
				if ($conn->query(
					sprintf(
						'UPDATE `users` SET `password`=\'%s\' WHERE `username`=\'%s\'',
						$conn->real_escape_string($hash),
						$conn->real_escape_string($username)
					)
				)===false) throw new Exception($conn->error);
			
			}
			
			//	User verified, give them a session
			//	cookie
			
			//	Generate a 128-bit cryptographically
			//	secure random number to use as the
			//	user's session key
			$key=bin2hex(CryptoRandom(128/8));
			
			//	Deduce the expiry time
			
			//	Add expiry time to current
			//	UNIX timestamp
			$expiry=intval(time()+LOGIN_COOKIE_EXPIRY);
			//	Detect overflow and correct
			//	as best possible
			if ($expiry<0) $expiry=PHP_INT_MAX;
			
			//	Format MySQL expiry
			$mysql_expiry=new DateTime();
			$mysql_expiry->setTimestamp($expiry);
			$mysql_expiry=$mysql_expiry->format('Y-m-d H:i:s');
			
			//	Attempt to create session in
			//	the database
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
					$conn->real_escape_string($key),
					$conn->real_escape_string($row['id']->GetValue()),
					$conn->real_escape_string($_SERVER['REMOTE_ADDR']),
					$conn->real_escape_string($mysql_expiry)
				)	
			)===false) throw new Exception($conn->error);
			
			//	If $remember_me is false, cookie
			//	expires when user closes browse
			if (!$remember_me) $expiry=0;
			
			//	Send the cookie
			if (!setcookie(
				LOGIN_COOKIE,
				$key,
				$expiry,
				LOGIN_COOKIE_PATH,
				LOGIN_COOKIE_DOMAIN,
				LOGIN_COOKIE_HTTPS,
				LOGIN_COOKIE_HTTP_ONLY
			)) throw new Exception('Could not set cookie');
			
			//	Legacy password hash algorithm
			//	update complete, user verified,
			//	and session created, so we
			//	create an object and return it
			//	to notify the caller that the
			//	user has been logged in and
			//	supply them with their information
			return new User($row);
		
		}
		
		
		/**
		 *	Attempts to retrieve the user associated
		 *	with this session.
		 *
		 *	\return
		 *		The User object representing the user
		 *		associated with the current session,
		 *		or \em null if there is no session, or
		 *		if the session is invalid/expired.
		 */
		public static function Resume () {
		
			//	Database access
			global $dependencies;
			$conn=$dependencies['MISADBConn'];
			
			//	Is there a session at all?
			if (!isset($_COOKIE[LOGIN_COOKIE])) return null;
			
			//	Attempt to grab the user
			//	associated with this session
			//	from the database
			$query=$conn->query(
				sprintf(
					'SELECT
						`users`.*
					FROM
						`users`,
						`sessions`
					WHERE
						`sessions`.`key`=\'%s\' AND
						`sessions`.`expires`>NOW() AND
						`users`.`id`=`sessions`.`user_id`',
					$conn->real_escape_string($_COOKIE[LOGIN_COOKIE])
				)
			);
			
			//	Throw on error
			if ($query===false) throw new Exception($conn->error);
			
			//	Fail if no row was returned
			if ($query->num_rows===0) return null;
			
			//	Update session information
			if ($conn->query(
				sprintf(
					'UPDATE
						`sessions`
					SET
						`last`=NOW(),
						`last_ip`=\'%s\'
					WHERE
						`key`=\'%s\'',
					$conn->real_escape_string($_SERVER['REMOTE_ADDR']),
					$conn->real_escape_string($_COOKIE[LOGIN_COOKIE])
				)
			)===false) throw new Exception($conn->error);
			
			//	Return user associated with session
			return new User(new MySQLRow($query));
		
		}
		
		
		/**
		 *	Logs the currently logged in user out,
		 *	destroyed their session cookie and
		 *	eliminating the session from the database.
		 *
		 *	If there is no active session, nothing
		 *	happens.
		 */
		public static function Logout () {
		
			//	Database access
			global $dependencies;
			$conn=$dependencies['MISADBConn'];
			
			//	Is there a session?
			//
			//	Short-circuit out if not
			if (!isset($_COOKIE[LOGIN_COOKIE])) return;
			
			//	Delete the session from the
			//	database
			if ($conn->query(
				sprintf(
					'DELETE FROM `sessions` WHERE `key`=\'%s\'',
					$conn->real_escape_string($_COOKIE[LOGIN_COOKIE])
				)
			)===false) throw new Exception($conn->error);
			
			//	Destroy the user's cookie
			if (!setcookie(
				LOGIN_COOKIE,
				'',
				1,
				LOGIN_COOKIE_PATH,
				LOGIN_COOKIE_DOMAIN,
				LOGIN_COOKIE_HTTPS,
				LOGIN_COOKIE_HTTP_ONLY
			)) throw new Exception('Could not delete cookie');
		
		}
		
		
		/**
		 *	Creates a new user object from
		 *	a database row.
		 *
		 *	Should not be used directly, use
		 *	the static factory methods instead.
		 *
		 *	\param [in] $row
		 *		The database row representing
		 *		the user-in-question.
		 */
		public function __construct ($row) {
		
			if (!($row instanceof MySQLRow)) throw new Exception('Type mismatch');
		
			$this->row=$row;
		
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
			$row=$this->row;
		
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
		 *	Checks to see if this user has a certain
		 *	property associated with them.
		 *
		 *	Note that unlike the PHP built-in semantics,
		 *	a property may exist but have a value of
		 *	\em null.
		 *
		 *	\param [in] $offset
		 *		The property whose existence shall be
		 *		checked.
		 *
		 *	\return
		 *		\em true if the property exists,
		 *		\em false otherwise.
		 */
		public function offsetExists ($offset) {
		
			return isset($this->row[$offset]);
		
		}
		
		
		/**
		 *	Retrieves the value of a property.
		 *
		 *	\param [in] $offset
		 *		The property to retrieve.
		 *
		 *	\return
		 *		The value of the property given by
		 *		\em offset, or \em null if that
		 *		property does not exist.
		 */
		public function offsetGet ($offset) {
		
			if (isset($this->row[$offset])) return $this->row[$offset]->GetValue();
			
			return null;
		
		}
		
		
		/**
		 *	Does nothing.
		 *
		 *	\param [in] $offset
		 *		Ignored.
		 *	\param [in] $value
		 *		Ignored.
		 */
		public function offsetSet ($offset, $value) {	}
		
		
		/**
		 *	Does nothing.
		 *
		 *	\param [in] $offset
		 *		Ignored.
		 */
		public function offsetUnset ($offset) {	}
	
	
	}


?>