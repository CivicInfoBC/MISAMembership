<?php


	require_once(WHERE_PHP_INCLUDES.'mb.php');


	/**
	 *	Encapsulates a single entry in a
	 *	single row of a MySQL result.
	 */
	class MySQLDatum {
	
		
		private $metadata;
		private $data;
		
		
		/**
		 *	Creates a new datum.
		 *
		 *	Should only be called internally.
		 *
		 *	\param [in] $data
		 *		The data to encapsulate.
		 *	\param [in] $metadata
		 *		The metadata object which contains
		 *		metadata about \em data.
		 */
		public function __construct ($data, $metadata) {
		
			$this->data=$data;
			$this->metadata=$metadata;
		
		}
		
		
		/**
		 *	Retrieves the encapsulated value in the
		 *	appropriate format.
		 *
		 *	For dates, times, timestamps, et cetera
		 *	a DateTime object shall be returned.
		 *
		 *	For decimal, fractional, and floating point
		 *	types a float shall be returned.
		 *
		 *	For integer types, an int shall be returned.
		 *
		 *	For bit types with a width of one bit, a
		 *	corresponding bool shall be returned,
		 *	otherwise a corresponding int shall be
		 *	returned.
		 *
		 *	For string or text or character types,
		 *	a corresponding string is returned.
		 *
		 *	For all other types they are returned
		 *	untouched.
		 *
		 *	\return
		 *		The value of this datum.
		 */
		public function GetValue () {
		
			//	Preserve NULL
			if (is_null($this->data)) return null;
		
			switch ($this->metadata->type) {
			
				//	Dates, times, et cetera
				case MYSQLI_TYPE_TIMESTAMP:
				case MYSQLI_TYPE_DATE:
				case MYSQLI_TYPE_TIME:
				case MYSQLI_TYPE_DATETIME:
				case MYSQLI_TYPE_YEAR:
				case MYSQLI_TYPE_NEWDATE:
				case MYSQLI_TYPE_INTERVAL:
				
					return new DateTime($this->data);
				
				//	Floating point/fractional types
				case MYSQLI_TYPE_DECIMAL:
				case MYSQLI_TYPE_NEWDECIMAL:
				case MYSQLI_TYPE_FLOAT:
				case MYSQLI_TYPE_DOUBLE:
				
					return floatval($this->data);
					
				//	Bit types (turn into boolean if
				//	one bit wide)
				case MYSQLI_TYPE_BIT:
				
					if ($this->metadata->length===1) return intval($this->data)===1;
					
					//	Falls through to integer handling if
					//	not 1 bit wide
					
				//	Integers
				case MYSQLI_TYPE_TINY:
				case MYSQLI_TYPE_SHORT:
				case MYSQLI_TYPE_LONG:
				case MYSQLI_TYPE_LONGLONG:
				case MYSQLI_TYPE_INT24:
				
					return intval($this->data);
					
				//	Strings and text
				case MYSQLI_TYPE_VAR_STRING:
				case MYSQLI_TYPE_STRING:
				case MYSQLI_TYPE_CHAR:
				
					return (string)($this->data);
				
				//	Everything else, just
				//	return the data raw
				default:
				
					return $this->data;
			
			}
		
		}
		
		
		/**
		 *	Returns a string representation of the value
		 *	of this datum.
		 *
		 *	\return
		 *		A string representation of the value
		 *		of this datum.
		 */
		public function __toString () {
		
			return (string)($this->data);
		
		}
		
	
	}


	/**
	 *	Encapsulates a row and provides faculties
	 *	for navigating an entire result set row-by-row.
	 */
	class MySQLRow implements arrayaccess {
	
	
		private $query;
		private $data;
		private $metadata;
		
		
		/**
		 *	Creates a new MySQLRow by extracting the
		 *	first row from a MySQLi result.
		 *
		 *	Throws if there are no rows.
		 *
		 *	\param [in] $query
		 *		A MySQLi result object.
		 */
		public function __construct ($query) {
		
			if (
				is_null($query) ||
				($query===false) ||
				(get_class($query)!=='mysqli_result')
			) throw new Exception('Query failed');
			
			$this->data=$query->fetch_assoc();
			
			if (is_null($this->data)) throw new Exception('No more results');
			
			$query->field_seek(0);
			
			foreach (array_keys($this->data) as $x) {
			
				$metadata=$query->fetch_field();
				
				if ($metadata===false) throw new Exception('Metadata/data mis-match');
				
				$this->metadata[$x]=$metadata;
			
			}
			
			$this->query=$query;
		
		}
		
		
		/**
		 *	Returns the next row in the result set, or
		 *	\em null if there are no more results.
		 *
		 *	\return
		 *		A MySQLRow object representing the next
		 *		row in the result set, or \em null.
		 */
		public function Next () {
		
			try {
			
				return new MySQLRow($this->query);
			
			} catch (Exception $e) {
			
				if ($e->getMessage()==='No more results') return null;
				
				throw $e;
			
			}
		
		}
		
		
		/**
		 *	Determines whether a given column exists in
		 *	this row.
		 *
		 *	Rank or column name may be specified.
		 *
		 *	\param [in] $offset
		 *		The column name or rank of the column
		 *		to query.
		 *
		 *	\return
		 *		\em true if the corresponding column
		 *		exists in this row, \em false otherwise.
		 */
		public function offsetExists ($offset) {
		
			$data_keys=array_keys($this->data);
			
			return in_array($offset,$data_keys,true) || isset($data_keys[$offset]);
		
		}
		
		
		/**
		 *	Retrieves a MySQLDatum object representing the
		 *	value of a certain column in this row, or \em null
		 *	if the specified column does not exist.
		 *
		 *	\param [in] $offset
		 *		The column name or rank of the column to
		 *		retrieve the value of.
		 *
		 *	\return
		 *		A MySQLDatum object if the column-in-question
		 *		exists, \em null otherwise.
		 */
		public function offsetGet ($offset) {
		
			$data_keys=array_keys($this->data);
			
			if (in_array($offset,$data_keys,true)) return new MySQLDatum(
				$this->data[$offset],
				$this->metadata[$offset]
			);
			
			if (isset($data_keys[$offset])) return new MySQLDatum(
				$this->data[$data_keys[$offset]],
				$this->metadata[$data_keys[$offset]]
			);
			
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