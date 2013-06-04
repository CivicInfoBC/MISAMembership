<?php


	require_once(WHERE_PHP_INCLUDES.'mysqli_result.php');
	require_once(WHERE_PHP_INCLUDES.'bcrypt.php');
	require_once(WHERE_PHP_INCLUDES.'define.php');
	require_once(WHERE_PHP_INCLUDES.'mb.php');
	require_once(WHERE_PHP_INCLUDES.'crypto_random.php');
	
	
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
	class User {
	
	
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
		 *		on success, null otherwise.
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
		 *		or null if there is no session, or
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
	
	
	}


?>