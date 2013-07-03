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
			
			//	We want dues paid for this
			//	calendar year
			$year=new DateTime();	//	Right now
			$year=$year->format('Y');	//	Four digit year
			
			$query=$conn->query(
				sprintf(
					'SELECT
						*
					FROM
						`payment`
					WHERE
						`org_id`=\'%s\' AND
						`membership_year`=\'%s\' AND
						`paid`=\'1\'',
					$conn->real_escape_string($id),
					$conn->real_escape_string($year)
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