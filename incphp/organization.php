<?php


	require_once(WHERE_PHP_INCLUDES.'mysqli_result.php');
	require_once(WHERE_PHP_INCLUDES.'define.php');
	require_once(WHERE_LOCAL_PHP_INCLUDES.'user.php');


	//	Database dependency to use for retrieving
	//	information about organizations.
	def('ORG_DB','MISADBConn');
	//	Database dependency to use for retrieving
	//	information about whether or not an
	//	organization has paid.
	def('PAID_DB','MISADBConn');
	

	/**
	 *	Encapsulates an organization.
	 */
	class Organization implements IteratorAggregate {
	
	
		public static $keyword_columns=array(
			'name',
			'address1',
			'address2',
			'city',
			'postal_code',
			'territorial_unit',
			'country',
			'phone',
			'fax'
		);
	
	
		private $row;
		public $has_paid;
		public $membership_type;
		
		
		/**
		 *	Checks to see whether the organization
		 *	given by a certain ID has paid their
		 *	membership dues.
		 *
		 *	\param [in] $id
		 *		The ID of the organization-in-question.
		 *
		 *	\return
		 *		\em true if the organization given by
		 *		\em id has paid, \em false otherwise.
		 *		Note that \em false will be returned
		 *		in the case where the organization
		 *		does not exist at all.
		 */
		public static function HasPaid ($id) {
		
			//	Guard against nulls
			if (is_null($id)) return false;
		
			//	Fetch database connection
			global $dependencies;
			$conn=$dependencies[PAID_DB];
			
			$query=$conn->query(
				sprintf(
					'SELECT
						*
					FROM
						`payment`,
						`membership_years`
					WHERE
						`payment`.`membership_year_id`=`membership_years`.`id` AND
						`payment`.`org_id`=\'%s\' AND
						`membership_years`.`start`<=NOW() AND
						`membership_years`.`end`>=NOW() AND
						`payment`.`paid`=\'1\'',
					$conn->real_escape_string($id)
				)	
			);
			
			//	Throw on error
			if ($query===false) throw new Exception($conn->error);
			
			//	If there's at least one
			//	row, we're good, otherwise
			//	they haven't paid
			$has_paid=$query->num_rows!==0;
			
			$query->free();
			
			return $has_paid;
		
		}
		
		
		/**
		 *	Attempts to retrieve an organization
		 *	based on an ID.
		 *
		 *	\return
		 *		If the organization exists, returns
		 *		an Organization object representing it,
		 *		otherwise returns \em null.
		 */
		public static function GetByID ($id) {
		
			//	Guard against nulls
			if (is_null($id)) return null;
			
			//	Fetch database connection
			global $dependencies;
			$conn=$dependencies[ORG_DB];
			
			//	Attempt to grab the organization
			//	from the database
			$query=$conn->query(
				sprintf(
					'SELECT
						*
					FROM
						`organizations`
					WHERE
						`id`=\'%s\'',
					$conn->real_escape_string($id)
				)
			);
			
			//	Throw on error
			if ($query===false) throw new Exception($conn->error);
			
			//	If there are zero rows, fail out
			if ($query->num_rows===0) return null;
			
			//	Otherwise grab the row and
			//	return an Organization object
			return new Organization(new MySQLRow($query));
		
		}
		
		
		/**
		 *	Retrieves the name of the type given by
		 *	\em type_id.
		 *
		 *	\param [in] $type_id
		 *		The ID of the type to retriev.
		 *
		 *	\return
		 *		The name of the type identified by
		 *		\em type_id, \em null otherwise.
		 */
		public static function GetType ($type_id) {
		
			//	Fetch database connection
			global $dependencies;
			$conn=$dependencies[ORG_DB];
			
			//	Attempt to grab type name from
			//	the database
			$query=$conn->query(
				sprintf(
					'SELECT `name` FROM `membership_types` WHERE `id`=\'%s\'',
					$conn->real_escape_string($type_id)
				)
			);
			
			//	Throw on error
			if ($query===false) throw new Exception($conn->error);
			
			//	If there are zero rows, fail out
			if ($query->num_rows===0) return null;
			
			//	Grab the row
			$row=new MySQLRow($query);
			
			//	Return
			return $row['name']->GetValue();
		
		}
		
		
		/**
		 *	Gets the per year price for this organization.
		 *
		 *	\return
		 *		The per year price for this organization.
		 */
		public function GetPrice () {
			
			//	Get database access
			global $dependencies;
			$conn=$dependencies[ORG_DB];
			
			//	Get type amount from the database
			$query=$conn->query(
				sprintf(
					'SELECT `price` FROM `membership_types` WHERE `id`=\'%s\'',
					$conn->real_escape_string($this->membership_type_id)
				)
			);
			
			//	Throw on error
			if ($query===false) throw new Exception($conn->error);
			
			//	Throw if there are no rows
			if ($query->num_rows===0) throw new Exception('Invalid membership_type_id');
			
			//	Fetch row
			$row=new MySQLRow($query);
			
			//	Return
			return $row[0]->GetValue();
		
		}
		
		
		/**
		 *	Gets all membership types from the database.
		 *
		 *	\return
		 *		An enumerated array of MySQLRow objects
		 *		representing each membership type from
		 *		the database.  The rows are not sorted.
		 */
		public static function GetTypes () {
		
			//	Get database connection
			global $dependencies;
			$conn=$dependencies[ORG_DB];
			
			//	Get all membership types
			//	from the database
			$query=$conn->query('SELECT * FROM `membership_types`');
			
			//	Throw on error
			if ($query===false) throw new Exception($conn->error);
			
			//	Create a result set
			$results=array();
			
			//	Fetch all
			if ($query->num_rows===0) return $results;
			
			for (
				$row=new MySQLRow($query);
				!is_null($row);
				$row=$row->Next()
			) {

				//	TODO: Replace this by generalizing the
				//	object-which-encapsulates-a-MySQLRow
				//	concept
			
				$temp=array();
				
				foreach ($row as $key=>$value) $temp[$key]=$value->GetValue();
				
				$results[]=(object)$temp;
				
			}
			
			return $results;
		
		}
		
		
		/**
		 *	Creates a new Organization object which
		 *	wraps a database row.
		 *
		 *	Using this directly should be avoided
		 *	in favour of provided factories.
		 *
		 *	\param [in] $row
		 *		The database row to wrap.
		 */
		public function __construct ($row) {
		
			if ($row instanceof MySQLRow) {
			
				$this->row=array();
			
				foreach ($row as $key=>$value) $this->row[$key]=$value->GetValue();
			
			} else {
			
				$this->row=$row;
			
			}
			
			$this->has_paid=isset($row['id']) ? self::HasPaid($this->id) : null;
			$this->membership_type=self::GetType($this->membership_type_id);
		
		}
		
		
		/**
		 *	Saves this organization's data to the
		 *	database.
		 *
		 *	If no ID is specified for this organization,
		 *	a new organization is created in the database.
		 *
		 *	\return
		 *		\em null if an existing organization's
		 *		data was updated.  The ID of this newly-inserted
		 *		organization otherwise.
		 */
		public function Save () {

			//	Get database connection
			global $dependencies;
			$conn=$dependencies[ORG_DB];
		
			//	If there's an ID, UPDATE
			if (isset($this->row['id'])) {
			
				//	Generate SET clause
				$set_clause='';
				
				foreach ($this->row as $field=>$value) {
				
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
				
				//	Perform query against the
				//	database
				if ($conn->query(
					sprintf(
						'UPDATE `organizations` SET %s WHERE `id`=\'%s\'',
						$set_clause,
						$conn->real_escape_string($this->row['id'])
					)
				)===false) throw new Exception($conn->error);
				
				return null;
				
			}
			
			//	Otherwise, INSERT
			
			//	Generate VALUES listing
			//	and list of column names
			$values='';
			$columns='';
			
			foreach ($this->row as $field=>$value) {
				
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
					'INSERT INTO `organizations` (%s) VALUES (%s)',
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
			
			return 'SELECT * FROM `organizations` WHERE '.$like;
		
		}
		
		
		public static function GetAllQuery () {
		
			return 'SELECT * FROM `organizations`';
		
		}
		
		
		public static function GetPendingQuery () {
		
			return 'SELECT * FROM `organizations` WHERE `enabled` IS NULL';
		
		}
		
		
		public static function GetActiveQuery () {
		
			return 'SELECT
					`organizations`.*
				FROM
					`organizations`
				WHERE
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
					)';
					
		}
		
		
		public static function GetInactiveQuery () {
			
			return 'SELECT
					`organizations`.*
				FROM
					`organizations`
				WHERE
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
					)';
		
		}
		
		
		public static function GetMembershipTypeQuery ($type, $complement) {
		
			//	We need a database connection
			//	for escaping
			global $dependencies;
			$conn=$dependencies[ORG_DB];
			
			if (!is_array($type)) $type=array($type);
			
			//	Generate WHERE clause
			$or_clause='';
			foreach ($type as $t) {
			
				if ($or_clause!=='') $or_clause.=' OR ';
				
				$or_clause.='`membership_type_id`=\''.$conn->real_escape_string($t).'\'';
			
			}
			
			//	Return query
			return sprintf(
				'SELECT
					*
				FROM
					`organizations`
				WHERE
					%s',
				$or_clause
			);
		
		}
		
		
		/**
		 *	Determines the number of organizations in a
		 *	certain result set.
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
			$conn=$dependencies[ORG_DB];
			
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
		 *	Retrieves a page of organizations from the
		 *	database.
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
		 *		to determine how results are
		 *		ordered.
		 *	\param [in] $query
		 *		A query that shall be used to obtain
		 *		the IDs of the organizations to paginate.
		 *		If \em null a query returning all
		 *		organizations shall be used.  Defaults to
		 *		\em null.
		 *
		 *	\return
		 *		An enumerated array of Organization objects
		 *		representing the requested page.
		 */
		public static function GetPage ($page_num, $num_per_page, $order_by, $query=null) {
		
			//	Get database access
			global $dependencies;
			$conn=$dependencies[ORG_DB];
			
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
			
			//	Get the organizations on this
			//	page.
			$query=$conn->query($query_text);
			
			//	Throw on error
			if ($query===false) throw new Exception($conn->error);
			
			//	Prepare an array
			$results=array();
			
			//	Short-circuit out if there are
			//	no results
			if ($query->num_rows===0) return $results;
			
			//	Loop over the results adding
			//	them to the array to return
			for (
				$row=new MySQLRow($query);
				!is_null($row);
				$row=$row->Next()
			) $results[]=self::GetByID($row['id']->GetValue());
			
			return $results;
		
		}
		
		
		/**
		 *	Retrieves all payment information for this
		 *	organization from the database.
		 *
		 *	\return
		 *		An array of MySQLRow objects representing
		 *		the payment history for this organization.
		 */
		public function PaymentHistory () {
		
			//	Database access
			global $dependencies;
			$conn=$dependencies[ORG_DB];
			
			//	Get rows
			$query=$conn->query(
				sprintf(
					'SELECT
						*
					FROM
						`payment`
					WHERE
						`org_id`=\'%s\'
					ORDER BY
						`created` DESC',
					$conn->real_escape_string($this->id)
				)
			);
		
			//	Throw on error
			if ($query===false) throw new Exception($conn->error);
			
			//	Prepare result set
			$results=array();
			
			//	Short-circuit out if there are
			//	no rows
			if ($query->num_rows===0) return $results;
			
			//	Get all rows
			for (
				$row=new MySQLRow($query);
				!is_null($row);
				$row=$row->Next()
			) $results[]=$row;
			
			return $results;
		
		}
		
		
		public function GetUsers () {
		
			//	Database access
			global $dependencies;
			$conn=$dependencies[ORG_DB];
			
			//	Get IDs of all users in
			//	this organization
			$query=$conn->query(
				sprintf(
					'SELECT
						`id`
					FROM
						`users`
					WHERE
						`org_id`=\'%s\'
					ORDER BY
						`last_name` ASC,
						`first_name` ASC',
					$conn->real_escape_string($this->id)
				)
			);
			
			//	Throw on error
			if ($query===false) throw new Exception($conn->error);
			
			//	Prepare result set
			$results=array();
			
			//	Short-circuit out if there are no
			//	rows
			if ($query->num_rows===0) return $results;
			
			//	Get all rows
			for (
				$row=new MySQLRow($query);
				!is_null($row);
				$row=$row->Next()
			) $results[]=User::GetByID($row['id']->GetValue());
			
			return $results;
		
		}
		
		
		/**
		 *	Checks to see if a given organization
		 *	owes membership dues.
		 *
		 *	This differs from !HasPaid because this
		 *	function takes into account the \em perpetual
		 *	field, i.e. an organization marked
		 *	\em perpetual does not owe even if they
		 *	have not paid.
		 *
		 *	\return
		 *		\em true if this organization owes
		 *		membership dues, \em false otherwise.
		 */
		public function DoesOwe () {
		
			return $this->has_paid || $this->perpetual;
		
		}
		
		
		/**
		 *	Retrieves an enumerated array of all the
		 *	membership years for which the organization
		 *	has not paid dues, but which are still in
		 *	the future.
		 *
		 *	\return
		 *		A list of membership years for which this
		 *		organization has not paid dues, sorted
		 *		by date with the earlier years coming
		 *		first.
		 */
		public function UnpaidYears () {
		
			//	Get database access
			global $dependencies;
			$conn=$dependencies[ORG_DB];
			
			//	Execute query
			$query=$conn->query(
				sprintf(
					'SELECT
						`membership_years`.*
					FROM
						`membership_years` LEFT OUTER JOIN
						(
							SELECT
								*
							FROM
								`payment`
							WHERE
								`org_id`=\'%s\'
						) `payment` ON `payment`.`membership_year_id`=`membership_years`.`id`
					WHERE
						`membership_years`.`end`>=NOW() AND
						(
							`payment`.`paid` IS NULL OR
							`payment`.`paid`=0
						)
					ORDER BY
						`membership_years`.`start` ASC',
					$conn->real_escape_string($this->id)
				)
			);
			
			//	Throw on error
			if ($query===false) throw new Exception($conn->error);
			
			//	Prepare result set
			$results=array();
			
			//	Short-circuit out if no results
			if ($query->num_rows===0) return $results;
			
			for (
				$row=new MySQLRow($query);
				!is_null($row);
				$row=$row->Next()
			) {
			
				$result=array();
				
				foreach ($row as $field=>$value) $result[$field]=$value->GetValue();
				
				$results[]=(object)$result;
			
			}
			
			return $results;
		
		}
		
		
		/**
		 *	Retrieves the amount of time this organization
		 *	has been unpaid.
		 *
		 *	\return
		 *		An integer representing the number of seconds
		 *		since this organization last paid membership
		 *		dues.  Returns zero if this organization has
		 *		paid for this year.  Returns \em null if this
		 *		organization has never paid.
		 */
		public function UnpaidDuration () {
		
			//	Get database access
			global $dependencies;
			$conn=$dependencies[ORG_DB];
			
			//	Query for duration
			$query=$conn->query(
				sprintf(
					'SELECT
						UNIX_TIMESTAMP(NOW())-UNIX_TIMESTAMP(`end`) AS `duration`
					FROM
						(
							SELECT
								`membership_years`.`end`
							FROM
								`membership_years` LEFT OUTER JOIN
								(
									SELECT
										`membership_year_id`,
										`paid`
									FROM
										`payment`
									WHERE
										`org_id`=\'%s\'
								) `payment` ON `payment`.`membership_year_id`=`membership_years`.`id`
							WHERE
								`membership_years`.`start`<=NOW() AND
								`payment`.`paid`=1
							ORDER BY
								`membership_years`.`start` DESC
							LIMIT 1
						) `membership_years`',
					$conn->real_escape_string($this->id)
				)
			);
			
			//	Throw on error
			if ($query===false) throw new Exception($conn->error);
			
			//	Return immediately if the organization
			//	has never paid
			if ($query->num_rows===0) return null;
			
			$row=new MySQLRow($query);
			
			$duration=$row['duration']->GetValue();
			
			//	Return duration
			return ($duration<0) ? 0 : $duration;
		
		}
		
		
		/**
		 *
		 */
		public function GetNotes () {
		
			//	Get database access
			global $dependencies;
			$conn=$dependencies[ORG_DB];
			
			if (($query=$conn->query(
				sprintf(
					'SELECT * FROM `organization_notes` WHERE `org_id`=\'%s\'',
					$conn->real_escape_string($this->id)
				)
			))===false) throw new Exception($conn->error);
			
			$retr=array();
			
			if ($query->num_rows===0) return $retr;
			
			for (
				$row=new MySQLRow($query);
				!is_null($row);
				$row=$row->Next()
			) $retr[]=$row->ToObject();
			
			return $retr;
		
		}
		
		
		/**
		 *	Gets an iterator which allows you to
		 *	traverse the various properties of the
		 *	organization.
		 *
		 *	\return
		 *		An iterator which traverses this
		 *		object.
		 */
		public function getIterator () {
		
			return $this->row->getIterator();
		
		}
		
		
		/**
		 *	Serializes this organization into an array
		 *	suitable for JSON encoding.
		 *
		 *	\return
		 *		An associative array which represents this
		 *		object.
		 */
		public function ToArray () {
		
			$returnthis=$this->row;
			
			$returnthis['has_paid']=$this->has_paid ? 1 : 0;
			$returnthis['membership_type']=$this->membership_type;
			
			return $returnthis;
		
		}
		
		
		/**
		 *	Retrieves a property of this organization.
		 *
		 *	\param [in] $name
		 *		The name of the property to retrieve.
		 *
		 *	\return
		 *		The value associated with that property,
		 *		or \em null if that property does not
		 *		exist.
		 */
		public function __get ($name) {
			
			if (isset($this->row[$name])) return $this->row[$name];
			
			return null;
		
		}
		
		
		/**
		 *	Sets a property of this organization.
		 *
		 *	\param [in] $name
		 *		The name of the property to set.
		 *	\param [in] $value
		 *		The value to set \em name to.
		 */
		public function __set ($name, $value) {
		
			$this->row[$name]=$value;
		
		}
		
		
		/**
		 *	Unsets a property of this organization.
		 *
		 *	\param [in] $name
		 *		The name of the property to unset.
		 */
		public function __unset ($name) {
		
			unset($this->row[$name]);
		
		}
		
		
		/**
		 *	Checks to see if a property of this organization
		 *	has a value.
		 *
		 *	\param [in] $name
		 *		The property to check.
		 *
		 *	\return
		 *		\em true if \em name has a value, \em false
		 *		otherwise.  Not that just because a property
		 *		has a value, does not mean that value is not
		 *		\em null.
		 */
		public function __isset ($name) {
		
			return isset($this->row[$name]);
		
		}
	
	
	}


?>