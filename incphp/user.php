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
			1 => 'Incorrect e-mail or password',
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
		 *	Retrieves the reason string associated with
		 *	a given code.
		 *
		 *	\param [in] $code
		 *		The code to retrieve the reason string for.
		 *
		 *	\return
		 *		The associated reason string, or \em null
		 *		if there is no corresponding reason string.
		 */
		public static function GetReason ($code) {
		
			if (isset(self::$codes[$code])) return self::$codes[$code];
			
			return null;
		
		}
		
		
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
	
	
		public static $keyword_columns=array(
			'first_name',
			'last_name',
			'title',
			'address',
			'address2',
			'city',
			'postal_code',
			'territorial_unit',
			'country',
			'phone',
			'fax',
			'email',
			'type'
		);
	
	
		private $user;
		public $organization;
		public $session_key;
		public $session_expiry;
		
		
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
			
			return $bcrypt->hash(
				Normalizer::normalize(
					$password,
					Normalizer::FORM_C
				)
			);
		
		}
		
		
		/**
		 *	Creates a new user object.
		 *
		 *	\param [in] $user
		 *		A MySQLRow or array to wrap.
		 */
		public function __construct ($user) {
		
			if (!is_null($user)) {
			
				if ($user instanceof MySQLRow) {
				
					$this->user=array();
					
					foreach ($user as $key=>$value) $this->user[$key]=$value->GetValue();
				
				} else {
				
					$this->user=$user;
				
				}
			
			}
			
			$this->organization=null;
			$this->session_key=null;
		
		}
		
		
		/**
		 *	Saves this user's data to the database.
		 *
		 *	If no ID is specified for this user, a new
		 *	user is created in the database.
		 *
		 *	\return
		 *		\em null if an existing user's data
		 *		was updated.  The ID of the newly-inserted
		 *		user otherwise.
		 */
		public function Save () {

			//	Get database access
			global $dependencies;
			$conn=$dependencies[USER_DB];
			
			//	If there's an ID, UPDATE
			if (isset($this->user['id'])) {
		
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
				
				return null;
				
			}
			
			//	Otherwise, INSERT
			
			//	Generate VALUES listing
			//	and list of column names
			$values='';
			$columns='';
			
			foreach ($this->user as $field=>$value) {
				
				if ($values!=='') {
				
					$values.=',';
					$columns.=',';
				
				}
				
				if (is_bool($value)) $value=$value ? 1 : 0;
				
				$values.=is_null($value) ? 'NULL' : '\''.$conn->real_escape_string($value).'\'';
				$columns.='`'.preg_replace('/`/','``',$field).'`';
			
			}
			
			//	Perform query against the
			//	database
			if ($conn->query(
				sprintf(
					'INSERT INTO `users` (%s) VALUES (%s)',
					$columns,
					$values
				)
			)===false) throw new Exception($conn->error);
			
			return $conn->insert_id;
		
		}
		
		
		public static function GetKeywordQuery ($arr) {
		
			global $dependencies;
		
			if (
				is_null($arr) ||
				($arr==='') ||
				(count(self::$keyword_columns)===0)
			) return self::GetAllQuery();
			
			if (!is_array($arr)) $arr=array($arr);
			else if (count($arr)===0) return self::GetAllQuery();
			
			$like='';
			
			foreach ($arr as $keyword) {
			
				if ($like!=='') $like.=' AND ';
				$like.='(';
				
				$first=true;
				foreach (self::$keyword_columns as $column) {
				
					if ($first) $first=false;
					else $like.=' OR ';
					
					$like.='`'.preg_replace(
						'/`/u',
						'``',
						$column
					).'` LIKE \''.$dependencies[ORG_DB]->real_escape_string(
						'%'.preg_replace(
							'/([%_])/u',
							'$1$1',
							$keyword
						).'%'
					).'\'';
				
				}
				
				$like.=')';
			
			}
			
			return 'SELECT * FROM `users` WHERE '.$like;
		
		}
		
		
		public static function GetAllQuery () {
		
			return 'SELECT * FROM `users`';
		
		}
		
		
		public static function GetActiveQuery () {
		
			return 'SELECT
					`users`.*
				FROM
					`users`,
					`organizations`
				WHERE
					`organizations`.`id`=`users`.`org_id` AND
					`users`.`enabled`<>0 AND
					`organizations`.`enabled` IS NOT NULL AND
					`organizations`.`enabled`<>0 AND
					(
						(
							SELECT
								COUNT(*)
							FROM
								`payment`,
								`membership_years`
							WHERE
								`payment`.`paid`<>0 AND
								`membership_years`.`id`=`payment`.`membership_year_id` AND
								(
									`payment`.`type`=\'membership renewal\' OR
									`payment`.`type`=\'membership new\'
								) AND
								`organizations`.`id`=`payment`.`org_id` AND
								`membership_years`.`start`<=NOW() AND
								`membership_years`.`end`>=NOW()
						)<>0 OR
						`organizations`.`perpetual`<>0
					)
				UNION
				SELECT
					*
				FROM
					`users`
				WHERE
					`users`.`org_id` IS NULL AND
					`users`.`enabled`<>0';
		
		}
		
		
		public static function GetInactiveQuery () {
		
			return 'SELECT
					`users`.*
				FROM
					`users`,
					`organizations`
				WHERE
					`organizations`.`id`=`users`.`org_id` AND
					(
						`users`.`enabled`=0 OR
						`organizations`.`enabled` IS NULL OR
						`organizations`.`enabled`=0 OR
						(
							(
								SELECT
									COUNT(*)
								FROM
									`payment`,
									`membership_years`
								WHERE
									`payment`.`paid`<>0 AND
									`membership_years`.`id`=`payment`.`membership_year_id` AND
									(
										`payment`.`type`=\'membership renewal\' OR
										`payment`.`type`=\'membership new\'
									) AND
									`organizations`.`id`=`payment`.`org_id` AND
									`membership_years`.`start`<=NOW() AND
									`membership_years`.`end`>=NOW()
							)=0 AND
							`organizations`.`perpetual`=0
						)
					)
				UNION
				SELECT
					*
				FROM
					`users`
				WHERE
					`users`.`org_id` IS NULL AND
					`users`.`enabled`=0';
		
		}
		
		
		public static function GetTypeQuery ($type, $complement=false) {
		
			if (!in_array($type,array('admin','superuser','user'),true)) $type='user';
			
			//	We need a database connection for
			//	escaping
			global $dependencies;
			
			return sprintf(
				'SELECT `users`.* FROM `users` WHERE `type`%s\'%s\'',
				$complement ? '<>' : '=',
				$dependencies[USER_DB]->real_escape_string($type)
			);
		
		}
		
		
		/**
		 *	Retrieves the number of users in a certain
		 *	result set.
		 *
		 *	\param [in] $query
		 *		A string, or an object which defines
		 *		__toString, which shall be used as
		 *		a query, the size of the result set
		 *		of which shall be returned.  If
		 *		\em null all users shall be counted.
		 *		Defaults to \em null.
		 *
		 *	\return
		 *		The size of the result set created by
		 *		\em query.
		 */
		public static function GetCount ($query=null) {
		
			//	Get database access
			global $dependencies;
			$conn=$dependencies[USER_DB];
			
			//	Execute query
			$query=$conn->query(
				sprintf(
					'SELECT
						COUNT(*)
					FROM
						(%s) `subquery`',
					is_null($query) ? self::GetAllQuery() : $query
				)
			);
			
			//	Throw on error
			if ($query===false) throw new Exception($conn->error);
			
			//	Throw if there are no rows
			if ($query->num_rows===0) throw new Exception('No rows');
			
			//	Extract row
			$row=new MySQLRow($query);
			
			//	Return
			return $row[0]->GetValue();
		
		}
		
		
		/**
		 *	Retrieves a page of users from the database.
		 *
		 *	\param [in] $page_num
		 *		The number of the page to retrieve.
		 *		If \em null all results will be
		 *		returned.
		 *	\param [in] $num_per_page
		 *		The maximum number of results to
		 *		return on each page.  If \em null
		 *		all results will be returned.
		 *	\param [in] $order_by
		 *		ORDER BY clauses which shall be used
		 *		to determine how the results are
		 *		ordered.
		 *	\param [in] $query
		 *		The query that shall be used to obtain
		 *		the IDs of the users to paginate.  If
		 *		\em null a query returning all users
		 *		shall be used.  Defaults to
		 *		\em null.
		 *
		 *	\return
		 *		An enumerated array of User objects representing
		 *		the requested page.
		 */
		public static function GetPage ($page_num, $num_per_page, $order_by, $query=null) {
		
			//	Get database access
			global $dependencies;
			$conn=$dependencies[USER_DB];
			
			//	Build the query
			$query_text=sprintf(
				'SELECT `id` FROM (%s) `subquery`',
				is_null($query) ? self::GetAllQuery() : $query
			);
			
			if (!(
				is_null($order_by) ||
				($order_by==='')
			)) $query_text.=' ORDER BY '.$order_by;
			
			if (!(
				is_null($page_num) ||
				is_null($num_per_page)
			)) $query_text.=' LIMIT '.intval(($page_num-1)*$num_per_page).','.intval($num_per_page);
			
			//	Get the IDs of the users on
			//	this page
			$query=$conn->query($query_text);
			
			//	Throw on error
			if ($query===false) throw new Exception($conn->error);
			
			//	Prepare an array
			$results=array();
			
			//	Short-circuit out if no results
			if ($query->num_rows===0) return $results;
			
			//	Loop over the results
			for (
				$row=new MySQLRow($query);
				!is_null($row);
				$row=$row->Next()
			) $results[]=self::GetByID($row[0]->GetValue());
			
			return $results;
		
		}
		
		
		/**
		 *	Checks to see if the user
		 *	account-in-question is able to login
		 *	independent of correct e-mail/password.
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
			if (!is_null($this->organization)) {
			
				if (!($this->organization->has_paid || $this->organization->perpetual)) {
				
					$code=4;
					
					return false;
				
				}
				
				if (!$this->organization->enabled) {
				
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
		 *	that corresponds to a given e-mail address.
		 *
		 *	\param [in] $email
		 *		The e-mail address based on which to
		 *		retrieve a user object.
		 *
		 *	\return
		 *		A user object representing the user
		 *		whose e-mail address is given by
		 *		\em email, or \em null if such a
		 *		user could not be found.
		 */
		public static function GetByUsername ($email) {
		
			//	Guard against nulls
			if (is_null($email)) return null;
			
			//	E-mails are all lowercase
			$email=MBStrong::ToLower($email);
			
			//	Database access
			global $dependencies;
			$conn=$dependencies[USER_DB];
			
			//	Attempt to find user in database
			$query=$conn->query(
				sprintf(
					'SELECT
						*
					FROM
						`users`
					WHERE
						`email`=\'%s\'',
					$conn->real_escape_string($email)
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
			$user=new User($row);
			
			//	Grab the user's organization's
			//	information
			$user->organization=Organization::GetByID($row['org_id']->GetValue());
			
			//	Return
			return $user;
		
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
			$user=new User($row);
			
			//	Grab the corresponding organization's
			//	information
			$user->organization=Organization::GetByID($row['org_id']->GetValue());
			
			//	Return
			return $user;
		
		}
		
		
		/**
		 *	Attempts to log a user in and possibly
		 *	generate them a session.
		 *
		 *	\param [in] $email
		 *		The e-mail the user supplied.
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
		public static function Login ($email, $password, $remember_me=true, $real_ip=null) {
		
			//	Guard against nulls
			if (
				is_null($email) ||
				is_null($password)
			) return new LoginAttempt(1);
			
			//	Sanitize e-mail address, remove
			//	leading/trailing whitespace, make
			//	all lower case.
			$email=MBString::ToLower(
				MBString::Trim(
					$email
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
						`email`=\'%s\'',
					$conn->real_escape_string($email)
				)
			);
			
			//	Throw on error
			if ($query===false) throw new Exception($conn->error);
			
			//	Fail out if there are no
			//	matching rows
			if ($query->num_rows===0) return new LoginAttempt(1);
			
			//	Extract row
			$row=new MySQLRow($query);
			
			//	We know user exists, check to
			//	see if password is correct
			
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
				if (md5(
					Normalizer::normalize(
						$password,
						Normalizer::FORM_C
					)
				)!==$row['password']->GetValue()) return new LoginAttempt(1);
				
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
							`email`=\'%s\'',
						$conn->real_escape_string(
							//	New bcrypt hash
							self::PasswordHash($password)
						),
						$conn->real_escape_string($email)
					)
				)===false) throw new Exception($conn->error);
			
			} else {
			
				//	bcrypt (modern)
				
				$bcrypt=new Bcrypt(BCRYPT_ROUNDS);
				
				//	Verify
				if (!$bcrypt->verify(
					Normalizer::normalize(
						$password,
						Normalizer::FORM_C
					),
					$row['password']->GetValue()
				)) return new LoginAttempt(1);
			
			}
			
			//	Now that the user is verified, create
			//	them an object and start filling in
			//	details
			$user=new User($row);
			
			//	Grab information about the user's
			//	organization
			$user->organization=Organization::GetByID($row['org_id']->GetValue());
			
			//	User is allowed to login
			
			//	Create session
			$user->CreateSession();
			
			//	If $remember_me is null, bypass setting
			//	cookie
			if (!is_null($remember_me)) self::SetCookie($user->session_key,$remember_me);
			
			//	Get final login success code
			$user->Check($code);
			
			//	Return
			return new LoginAttempt(
				$code,
				$user
			);
		
		}
		
		
		/**
		 *	Creates the user a new login session.
		 *
		 *	\param [in] $real_ip
		 *		The IP the user is actually connecting
		 *		from.  Defaults to \em null.  If \em null
		 *		the IP of the current request's remote
		 *		host shall be used.
		 */
		public function CreateSession ($real_ip=null) {
		
			//	Get database access
			global $dependencies;
			$conn=$dependencies[USER_DB];
		
			//	Generate 128-bit cryptographically
			//	secure random number to use as the
			//	user's session key
			$this->session_key=bin2hex(CryptoRandom(128/8));
			
			//	Deduce the time at which the session
			//	key we just generated ought to
			//	expire
			$expiry=intval(time()+LOGIN_COOKIE_EXPIRY);
			//	Detect overflow and correct as best
			//	possible
			if ($expiry<0) $expiry=PHP_INT_MAX;
			//	Set
			$this->session_expiry=new DateTime();
			$this->session_expiry->setTimestamp($expiry);
			
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
					$conn->real_escape_string($this->session_key),
					$conn->real_escape_string($this->id),
					$conn->real_escape_string(
						is_null($real_ip)
							?	$_SERVER['REMOTE_ADDR']
							:	$real_ip
					),
					$conn->real_escape_string($this->session_expiry->format('Y-m-d H:i:s'))
				)	
			)===false) throw new Exception($conn->error);
		
		}
		
		
		/**
		 *	Sets this user's password.
		 *
		 *	\param [in] $password
		 *		The password to set this user's password
		 *		to.
		 */
		public function SetPassword ($password) {
		
			if (!is_string($password)) throw new Exception('Type mismatch');
			
			if ($password==='') throw new Exception('No password');
			
			//	Hash
			$this->password=self::PasswordHash($password);
			
			//	Get database access
			global $dependencies;
			$conn=$dependencies[USER_DB];
			
			//	Update the database
			if ($conn->query(
				sprintf(
					'UPDATE `users` SET `password`=\'%s\' WHERE `id`=\'%s\'',
					$conn->real_escape_string($this->password),
					$conn->real_escape_string($this->id)
				)
			)===false) throw new Exception($conn->error);
		
		}
		
		
		/**
		 *	Generates an activation key for this user and
		 *	stores it in the database.
		 */
		public function GenerateActivationKey () {
		
			//	Get database access
			global $dependencies;
			$conn=$dependencies[USER_DB];
			
			//	Get 128-bit cryptographically-secure
			//	PRNG
			$this->activation_key=bin2hex(CryptoRandom(128/8));
			
			//	Store in database
			if ($conn->query(
				sprintf(
					'UPDATE `users` SET `activation_key`=\'%s\' WHERE `id`=\'%s\'',
					$conn->real_escape_string($this->activation_key),
					$conn->real_escape_string($this->id)
				)
			)===false) throw new Exception($conn->error);
		
		}
		
		
		/**
		 *	Clears the activation key once it has been
		 *	consumed.
		 */
		public function ClearActivationKey () {
		
			//	Get database access
			global $dependencies;
			$conn=$dependencies[USER_DB];
			
			//	Update DB
			if ($conn->query(
				sprintf(
					'UPDATE `users` SET `activation_key`=NULL WHERE `id`=\'%s\'',
					$conn->real_escape_string($this->id)
				)
			)===false) throw new Exception($conn->error);
		
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
			$user=new User(new MySQLRow($query));
			
			//	Grab information about the user's
			//	organization (if any)
			$user->organization=Organization::GetByID($user->org_id);
			
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
			
			//	Get final login success code
			$user->Check($code);
			
			//	Return
			return new LoginAttempt(
				$code,
				$user
			);
		
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
		 *		conforming MTAs).
		 *	-	\'E\' becomes the user's e-mail
		 *		address case corrected to upper case.
		 *	-	\'m\' becomes the user's e-mail
		 *		address without case correction.
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
							return FormatName($row['first_name']);
						case 'f':
							//	Have to use preg_split otherwise
							//	code points represented by multiple
							//	code units will become mangled and
							//	illegal
							$split=preg_split(
								'/(?<!^)(?!$)/u',
								$row['first_name']
							);
							if (count($split)===0) return '';
							return MBString::ToUpper($split[0]);
						case 'L':
							return FormatName($row['last_name']);
						case 'l':
							$split=preg_split(
								'/(?<!^)(?!$)/u',
								$row['last_name']
							);
							if (count($split)===0) return '';
							return MBString::ToUpper($split[0]);
						case 'e':
							return MBString::ToLower($row['email']);
						case 'E':
							return MBString::ToUpper($row['email']);
						case 'm':
							return $row['email'];
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
		
			$returnthis=array(
				'user' => $this->user,
				'organization' => is_null($this->organization) ? null : $this->organization->ToArray()
			);
			
			if (!is_null($this->session_key)) $returnthis['session_key']=$this->session_key;
			if (!is_null($this->session_expiry)) $returnthis['session_expiry']=$this->session_expiry->format('Y,m,d,H,i,s');
			
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
			
			return isset($this->user[$name]);
		
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
			
			if (isset($this->user[$name])) return $this->user[$name];
			
			return null;
		
		}
		
		
		public function __set ($name, $value) {
		
			$this->user[$name]=$value;
		
		}
		
		
		public function __unset ($name) {
		
			unset($this->user[$name]);
		
		}
	
	
	}


?>