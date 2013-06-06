<?php


	require_once('./script_config.php');
	
	
	function get_mysql_conn ($username, $password, $host, $database) {
	
		$conn=new mysqli(
			$host,
			$username,
			$password,
			$database
		);
		
		if ($conn->connect_error) throw new Exception($conn->connect_error);
		
		if ($conn->set_charset(SQL_CHARSET)===false) throw new Exception($conn->error);
		
		return $conn;
	
	}
	
	
	function make_sql ($conn, $val) {
	
		if (
			is_null($val) ||
			(
				is_string($val) &&
				($val==='')
			)
		) return 'NULL';
		
		return sprintf(
			'\'%s\'',
			$conn->real_escape_string($val)
		);
	
	}
	
	
	function scrub_row (&$row) {
	
		//	The source data has been polluted
		//	with people filling in fields with things like
		//	"N/A" or "XX".
		//
		//	Fix this
		foreach ($row as &$value) {
		
			if (is_null($value)) continue;
		
			if (
				//	Match "N/A" and variants
				(preg_match(
					'/^\\s*n\/?a\\s*$/ui',
					$value
				)!==0) ||
				//	At least one column in one
				//	row just has the content
				//	"a"
				(preg_match(
					'/^\\s*a\\s*$/ui',
					$value
				)!==0) ||
				//	There are rows in columns with
				//	"XX" or "xx" or "xxcx".
				(preg_match(
					'/^\\s*(?:x|c)+\\s*$/ui',
					$value
				)!==0) ||
				//	While we're at it let's clobber
				//	entries that are just whitespace
				(preg_match(
					'/^\\s*$/u',
					$value
				)!==0)
			) $value=null;	//	The proper way to do things
			//	For sanity's sake let's just make
			//	sure all the data is trimmed
			else $value=preg_replace(
				'/^\\s+|\\s+$/u',
				'',
				$value
			);
		
		}
	
	}
	
	
	//	Get database connections
	
	try {
	
		//	New database
		$new_conn=get_mysql_conn(
			NEW_USERNAME,
			NEW_PASSWORD,
			NEW_HOST,
			NEW_DATABASE
		);
		
		//	Old database
		$old_conn=get_mysql_conn(
			OLD_USERNAME,
			OLD_PASSWORD,
			OLD_HOST,
			OLD_DATABASE
		);
		
	} catch (Exception $e) {
	
		die($e->message);
	
	}
	
	
	//	Delete from tables in case we're re-running
	//	this script
	if (
		($new_conn->query('DELETE FROM `users`')===false) ||
		($new_conn->query('DELETE FROM `organizations`')===false) ||
		($new_conn->query('DELETE FROM `membership_types`')===false)
	) die($new_conn->error);
	
	
	//	Start by transforming membership types,
	//	since organizations and payment depend
	//	on that, and since pretty much everything
	//	else depends on those
	
	//	Select membership types from existing database
	$query=$old_conn->query('SELECT * FROM `membershiptypes`');
	
	//	Die on error
	if ($query===false) die($old_conn->error);
	
	//	Iterate and INSERT
	while (!is_null($row=$query->fetch_assoc())) {
	
		scrub_row($row);
	
		if ($new_conn->query(
			sprintf(
				'INSERT INTO `membership_types` (
					`name`,
					`description`,
					`price`,
					`is_municipality`
				) VALUES (
					%s,
					%s,
					%s,
					%s
				)',
				make_sql($new_conn,$row['name']),
				make_sql($new_conn,$row['description']),
				make_sql($new_conn,$row['price']),
				make_sql($new_conn,$row['isMunicipality']==='true' ? 1 : 0)
			)
		)===false) die($new_conn->error);
	
	}
	
	//	Next we transform the organizations table,
	//	since that will take care of most of the
	//	dependencies (the sessions table depends
	//	on the users table, but we'll not be populating
	//	that table).
	
	//	Select organizations from existing database
	//
	//	We make sure we select the name of their membership
	//	type, since we'll need that
	$query=$old_conn->query(
		'SELECT
			`organizations`.*,
			`membershiptypes`.`name` AS `membership_type_name`
		FROM
			`organizations`,
			`membershiptypes`
		WHERE
			`membershiptypes`.`id`=`organizations`.`membershipTypesId`'
	);
	
	//	Die on error
	if ($query===false) die($old_conn->error);
	
	//	Iterate and INSERT
	while (!is_null($row=$query->fetch_assoc())) {
	
		scrub_row($row);
	
		//	We need to get the new ID for this organization's
		//	membership type
		$mt_query=$new_conn->query(
			sprintf(
				'SELECT
					`id`
				FROM
					`membership_types`
				WHERE
					`name`=\'%s\'',
				$new_conn->real_escape_string($row['membership_type_name'])
			)
		);
		
		if ($mt_query===false) die($new_conn->error);
		
		if ($mt_query->num_rows===0) $mt_id=null;
		else {
		
			$mt_row=$mt_query->fetch_assoc();
			$mt_id=$mt_row['id'];
		
		}
		
		//	Switching to using tri-valued
		//	logic on 'enabled', make the transformation
		//	here
		switch ($row['enabled']) {
		
			case 'true':
			
				$enabled=1;
				break;
				
			case 'false':
			
				$enabled=0;
				break;
				
			default:
			
				$enabled=null;
				break;
		
		}
		
		//	Do some province/state processing
		if (
			is_null($row['otherProvince']) ||
			($row['otherProvince']==='')
		) $terr_unit=$row['province'];
		else switch ($row['otherProvince']) {
		
			//	Only populating values I've
			//	observed
			
			case 'WTF':
			
				//	Garbage, don't even add it
				continue;
				
			case 'California':
			
				$terr_unit='CA';
				break;
				
			case 'Idaho':
			
				$terr_unit='ID';
				break;
			
			//	Georgia
			case 'GA':
			
				$terr_unit='GA';
				break;
				
			case 'Northern Ireland':
			
				$terr_unit='Northern Ireland';
				break;
				
			case 'MA':
			
				//	Massachusetts
				$terr_unit='MA';
				break;
				
			case 'Georgia':
			
				$terr_unit='GA';
				break;
				
			case 'Wellington':
			
				$terr_unit='Wellington';
				break;
				
			case 'Atlanta':
			
				//	Who even did this????
				$terr_unit='GA';
				break;
				
			case 'Ohio':
			
				$terr_unit='OH';
				break;
				
			case 'Washington':
			
				$terr_unit='WA';
				break;
		
		}
		
		//	Insert data
		if ($new_conn->query(
			sprintf(
				'INSERT INTO `organizations` (
					`org_name`,
					`address1`,
					`address2`,
					`city`,
					`postal_code`,
					`territorial_unit`,
					`country`,
					`phone`,
					`membership_type_id`,
					`contact_name`,
					`contact_title`,
					`contact_email`,
					`contact_phone`,
					`contact_fax`,
					`secondary_contact_name`,
					`secondary_contact_title`,
					`secondary_contact_email`,
					`secondary_contact_phone`,
					`secondary_contact_fax`,
					`enabled`
				) VALUES (
					%1$s,
					%2$s,
					%3$s,
					%4$s,
					%5$s,
					%6$s,
					%7$s,
					%8$s,
					%9$s,
					%10$s,
					%11$s,
					%12$s,
					%8$s,
					%13$s,
					%14$s,
					%15$s,
					%16$s,
					%17$s,
					%18$s,
					%19$s
				)',
				make_sql($new_conn,$row['name']),					//	1
				make_sql($new_conn,$row['address1']),				//	2
				make_sql($new_conn,$row['address2']),				//	3
				make_sql($new_conn,$row['city']),					//	4
				make_sql($new_conn,$row['postalCode']),				//	5
				make_sql($new_conn,$terr_unit),						//	6
				make_sql($new_conn,$row['country']),				//	7
				make_sql($new_conn,$row['contactTelephone']),		//	8
				make_sql($new_conn,$mt_id),							//	9
				make_sql($new_conn,$row['contactName']),			//	10
				make_sql($new_conn,$row['contactTitle']),			//	11
				make_sql($new_conn,$row['contactEmail']),			//	12
				make_sql($new_conn,$row['contactFax']),				//	13
				make_sql($new_conn,$row['secondContactName']),		//	14
				make_sql($new_conn,$row['secondContactTitle']),		//	15
				make_sql($new_conn,$row['secondContactEmail']),		//	16
				make_sql($new_conn,$row['secondContactTelephone']),	//	17
				make_sql($new_conn,$row['secondContactFax']),		//	18
				make_sql($new_conn,$enabled)						//	19
			)
		)===false) die($new_conn->error);
	
	}
	
	
	//	Last we transform the users data
	
	//	Select existing users
	//
	//	We make sure to grab the name of their
	//	organization, since we'll need that
	//	to match them up with that organization's
	//	new ID.
	$query=$old_conn->query(
		'SELECT
			`users`.*,
			`organizations`.`name` AS `organization_name`
		FROM
			`users`,
			`organizations`
		WHERE
			`organizations`.`id`=`users`.`organizationsId`'
	);
	
	//	Die on error
	if ($query===false) die($old_conn->error);
	
	//	Loop for each user
	while (!is_null($row=$query->fetch_assoc())) {
	
		scrub_row($row);
		
		//	Skip garbage users
		if (
			(
				is_null($row['firstName']) ||
				($row['firstName']==='')
			) &&
			(
				is_null($row['lastName']) ||
				($row['lastName']==='')
			)
		) continue;
		
		//	If the user doesn't have a password,
		//	set their password to "password"
		if (
			is_null($row['password']) ||
			($row['password']==='')
		) $row['password']=md5('password');	//	I didn't choose MD5, new system's going to switch these to bcrypt
		
		//	We need to get the new ID for this user's
		//	organization, if they have one
		
		//	Do a preliminary sanity check before proceeding
		if (is_numeric($row['organization_name'])) {
		
			$org_query=$new_conn->query(
				sprintf(
					'SELECT
						`id`
					FROM
						`organizations`
					WHERE `name`=\'%s\'',
					$new_conn->real_escape
				)
			);
			
			//	Check for error
			if ($org_query===false) die($new_conn->error);
			
			//	Check to see if there are results
			if ($org_query->num_rows===0) $org_id=null;
			else {
			
				$org_row=$org_query->fetch_assoc();
				
				$org_id=$org_row['id'];
			
			}
		
		} else {
		
			$org_id=null;
		
		}
		
		//	Transform enabled field to new boolean
		//	values
		if ($row['enabled']==='true') $enabled=1;
		else $enabled=0;
		
		//	Transform province/otherProvince
		if (
			is_null($row['otherProvince']) ||
			($row['otherProvince']==='')
		) $terr_unit=$row['province'];
		else $terr_unit=$row['otherProvince'];
		
		//	Insert data
		if ($new_conn->query(
			sprintf(
				'INSERT INTO `users` (
					`first_name`,
					`last_name`,
					`email`,
					`title`,
					`username`,
					`password`,
					`address`,
					`address2`,
					`city`,
					`postal_code`,
					`territorial_unit`,
					`country`,
					`phone`,
					`fax`,
					`org_id`,
					`enabled`
				) VALUES (
					%1$s,
					%2$s,
					%3$s,
					%4$s,
					%5$s,
					%6$s,
					%7$s,
					%8$s,
					%9$s,
					%10$s,
					%11$s,
					%12$s,
					%13$s,
					%14$s,
					%15$s,
					%16$s
				)',
				make_sql($new_conn,$row['firstName']),	//	1
				make_sql($new_conn,$row['lastName']),	//	2
				make_sql($new_conn,$row['email']),		//	3
				make_sql($new_conn,$row['title']),		//	4
				make_sql($new_conn,$row['userName']),	//	5
				make_sql($new_conn,$row['password']),	//	6
				make_sql($new_conn,$row['address1']),	//	7
				make_sql($new_conn,$row['address2']),	//	8
				make_sql($new_conn,$row['city']),		//	9
				make_sql($new_conn,$row['postalCode']),	//	10
				make_sql($new_conn,$terr_unit),			//	11
				make_sql($new_conn,$row['country']),	//	12
				make_sql($new_conn,$row['phone']),		//	13
				make_sql($new_conn,$row['fax']),		//	14
				make_sql($new_conn,$org_id),			//	15
				make_sql($new_conn,$enabled)			//	16
			)
		)===false) die($new_conn->error);
	
	}


?>