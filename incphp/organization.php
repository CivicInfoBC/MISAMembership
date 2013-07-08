<?php


	require_once(WHERE_PHP_INCLUDES.'mysqli_result.php');
	require_once(WHERE_PHP_INCLUDES.'define.php');


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
	
	
		private $row;
		private $has_paid;
		private $membership_type;
		
		
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
		
			//	Guard against bad types
			if (!is_integer($type_id)) return null;
		
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
		
			if (!(
				($row instanceof MySQLRow) ||
				is_array($row)
			)) throw new Exception('Type mismatch');
			
			if (!isset($row['id'])) throw new Exception('Schema mismatch');
			
			$this->row=$row;
			$this->has_paid=self::HasPaid($this->id);
			$this->membership_type=self::GetType($this->membership_type_id);
			$this->type=null;
		
		}
		
		
		/**
		 *	Saves this organization's data to the
		 *	database.
		 */
		public function Save () {
		
			//	If there's no ID, bail out
			if (!isset($this->row['id'])) throw new Exception('No ID');
		
			//	Get database connection
			global $dependencies;
			$conn=$dependencies[ORG_DB];
			
			//	Generate SET clause
			$set_clause='';
			
			foreach ($this->row as $field=>$value) {
			
				if ($value instanceof MySQLDatum) $value=$value->GetValue();
			
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
		
		}
		
		
		private static function get_inner_query ($active) {
		
			if (is_null($active)) return '`organizations`';
			
			if ($active) return '(
				SELECT
					`organizations`.*
				FROM
					`organizations`
				WHERE
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
			) `subquery`';
			
			return '(
				SELECT
					`organizations`.*
				FROM
					`organizations`
				WHERE
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
			) `subquery`';
		
		}
		
		
		/**
		 *	Retrieves the number of organizations in the
		 *	database.
		 *
		 *	\param [in] $active
		 *		If \em null, all results shall be included
		 *		in the result set.  If \em true only active
		 *		organizations shall be included.  If \em false
		 *		only inactive organizations shall be
		 *		included.  Defaults to \em null.
		 */
		public static function GetCount ($active=null) {
		
			//	Get database access
			global $dependencies;
			$conn=$dependencies[ORG_DB];
			
			//	Execute query
			$query=$conn->query(
				sprintf(
					'SELECT
						COUNT(*)
					FROM
						%s',
					self::get_inner_query($active)
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
		 *	\param [in] $num_per_page
		 *		The maximum number of results to return
		 *		on each page.
		 *	\param [in] $order_by
		 *		ORDER BY clauses which shall be used
		 *		to determine how results are
		 *		ordered.
		 *	\param [in] $active
		 *		If \em null, all results shall be
		 *		included in the result set.  If
		 *		\em true only active organizations
		 *		shall be included.  If \em false
		 *		only inactive organizations shall
		 *		be included.  Defaults to \em null.
		 *
		 *	\return
		 *		An enumerated array of Organization objects
		 *		representing the requested page.
		 */
		public static function GetPage ($page_num, $num_per_page, $order_by, $active=null) {
		
			//	Get database access
			global $dependencies;
			$conn=$dependencies[ORG_DB];
			
			//	Get the organizations on this
			//	page.
			$query=$conn->query(
				sprintf(
					'SELECT
						`id`
					FROM
						%s
					%s
					LIMIT %s,%s',
					self::get_inner_query($active),
					(
						(isset($order_by) && ($order_by!==''))
							?	'ORDER BY '.$order_by
							:	''
					),
					intval(($page_num-1)*$num_per_page),
					intval($num_per_page)
				)
			);
			
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
		
			$returnthis=array();
		
			foreach ($this as $key=>$value) {
			
				$returnthis[$key]=$value->GetValue();
			
			}
			
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
		
			if ($name==='has_paid') return $this->has_paid;
			if ($name==='membership_type') return $this->membership_type;
			
			if (isset($this->row[$name])) return
				($this->row instanceof MySQLRow)
					?	$this->row[$name]->GetValue()
					:	$this->row[$name];
			
			return null;
		
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
		
			if (
				($name==='has_paid') ||
				($name==='membership_type')
			) return true;
		
			return isset($this->row[$name]);
		
		}
	
	
	}


?>