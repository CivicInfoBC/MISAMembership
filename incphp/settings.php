<?php


	require_once(WHERE_PHP_INCLUDES.'mysqli_result.php');
	require_once(WHERE_PHP_INCLUDES.'define.php');
	
	
	def('SETTING_DB','MISADBConn');
	def('SETTING_TABLE','settings');
	
	
	/**
	 *	Gets the value(s) associated with a
	 *	given key in the settings database
	 *	table.
	 *
	 *	\param [in] $key
	 *		The key whose values shall be
	 *		fetched.
	 *
	 *	\return
	 *		An enumerated array of the values
	 *		associated with \em key.
	 */
	function GetSetting ($key) {
	
		//	Database access
		global $dependencies;
		$conn=$dependencies[SETTING_DB];
		
		//	Get the setting
		$query=$conn->query(
			sprintf(
				'SELECT
					`value`
				FROM
					`%s`
				WHERE
					`key`=\'%s\'',
				preg_replace('/`/u','``',SETTING_TABLE),
				$conn->real_escape_string($key)
			)
		);
		
		//	Throw on error
		if ($query===false) throw new Exception($conn->error);
		
		$results=array();
		
		if ($query->num_rows===0) return $results;
		
		for (
			$row=new MySQLRow($query);
			!is_null($row);
			$row=$row->Next()
		) $results[]=$row[0]->GetValue();
		
		return $results;
	
	}
	
	
	/**
	 *	Sets the value(s) associated with a
	 *	given key in the settings database
	 *	table.
	 *
	 *	\param [in] $key
	 *		The key whose values shall be
	 *		set.
	 *	\param [in] $values
	 *		An array of values to associate
	 *		with \em key.  If \em null all
	 *		values associated with \em key
	 *		shall be deleted.
	 */
	function SetSetting ($key, $values=null) {
	
		if (is_null($key)) throw new Exception('Schema mismatch');
	
		if (is_null($values)) $values=array();
		else if (!is_array($values)) $values=array($values);
		
		//	Get database access
		global $dependencies;
		$conn=$dependencies[SETTING_DB];
		
		$table=preg_replace('/`/u','``',SETTING_TABLE);
		
		//	Lock table
		if ($conn->query(
			sprintf(
				'LOCK TABLES `%s` WRITE',
				$table
			)
		)===false) throw new Exception($conn->error);
		
		try {
		
			//	Delete all values associated
			//	with the target key
			if ($conn->query(
				sprintf(
					'DELETE FROM `%s` WHERE `key`=\'%s\'',
					$table,
					$conn->real_escape_string($key)
				)
			)===false) throw new Exception($conn->error);
			
			//	Insert all new values
			foreach ($values as $value) if ($conn->query(
				sprintf(
					'INSERT INTO `%s` (`key`,`value`) VALUES (\'%s\',\'%s\')',
					$table,
					$conn->real_escape_string($key),
					$conn->real_escape_string($value)
				)
			)===false) throw new Exception($conn->error);
			
		} catch (Exception $e) {
		
			//	Unlock table
			if ($conn->query('UNLOCK TABLES')===false) throw new Exception($conn->error);
		
			throw $e;
			
		}
		
		//	Unlock table
		if ($conn->query('UNLOCK TABLES')===false) throw new Exception($conn->error);
	
	}
	
	
	/**
	 *	For settings which can be either
	 *	\em true or \em false, checks to
	 *	see if the setting is \em true.
	 *
	 *	\param [in] $arr
	 *		The result of calling GetSetting
	 *		for the setting-in-question.
	 *
	 *	\return
	 *		\em true if \em arr is an array
	 *		and the first element should be
	 *		interpreted is \em true, \em false
	 *		otherwise.
	 */
	function IsSettingTrue ($arr) {
	
		return (
			!is_null($arr) &&
			is_array($arr) &&
			(count($arr)!==0) &&
			($arr[0]===TRUE_STRING)
		);
	
	}
	
	
	/**
	 *	Gets the first setting.
	 *
	 *	\param [in] $arr
	 *		An array of settings.
	 *
	 *	\return
	 *		The first setting.
	 */
	function GetSettingValue ($arr) {
	
		if (is_null($arr)) return null;
		
		if (is_array($arr)) {
		
			if (count($arr)===0) return null;
			
			return $arr[0];
		
		}
		
		return $arr;
	
	}


?>