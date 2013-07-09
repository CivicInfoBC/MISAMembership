<?php


	require_once('./script_config.php');
	
	
	function get_mysql_conn ($username, $password, $host, $database, $set_charset=true) {
	
		$conn=new mysqli(
			$host,
			$username,
			$password,
			$database
		);
		
		if ($conn->connect_error) throw new Exception($conn->connect_error);
		
		if ($set_charset && ($conn->set_charset(SQL_CHARSET)===false)) throw new Exception($conn->error);
		
		return $conn;
	
	}
	
	
	function make_sql ($conn, $val) {
	
		if (
			is_null($val) ||
			($val==='')
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
	
	
	function fix_terr_unit (&$terr_unit, &$country) {
	
		//	Get rid of two letter acronyms in favour of
		//	new values
		switch ($terr_unit) {
		
			case 'WTF':
			case 'XX':
				$terr_unit=null;
				break;
			case 'GA':
			//	Who even did this?
			case 'Atlanta':
				$terr_unit='Georgia';
				$country='United States of America';
				break;
			case 'SK':
				$terr_unit='Saskatchewan';
				$country='Canada';
				break;
			case 'BC':
				$terr_unit='British Columbia';
				$country='Canada';
				break;
			case 'ON':
				$terr_unit='Ontario';
				$country='Canada';
				break;
			case 'AB':
				$terr_unit='Alberta';
				$country='Canada';
				break;
			case 'QC':
				$terr_unit='Quebec';
				$country='Canada';
				break;
			case 'YK':
				$terr_unit='Yukon';
				$country='Canada';
				break;
			case 'NT':
				$terr_unit='Northwest Territories';
				$country='Canada';
				break;
			case 'NF':
				$terr_unit='Newfoundland and Labrador';
				$country='Canada';
				break;
			case 'MB':
				$terr_unit='Manitoba';
				$country='Canada';
				break;
			case 'CA':
				$terr_unit='California';
				$country='United States of America';
				break;
			case 'NU':
				$terr_unit='Nunavut';
				$country='Canada';
				break;
			case 'NB':
				$terr_unit='New Brunswick';
				$country='Canada';
				break;
			case 'MA':
				$terr_unit='Massachusetts';
				$country='United States of America';
				break;
			default:break;
		
		}
	
	}
	
	
	function fix_country (&$country) {
	
		switch ($country) {
		
			case 'CA':
				$country='Canada';
				break;
			case 'US':
				$country='United States of America';
				break;
			case 'NZ':
				$country='New Zealand';
				break;
			case 'UK':
				$country='United Kingdom of Great Britain and Northern Ireland';
				break;
		
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
			OLD_DATABASE,
			false
		);
		
	} catch (Exception $e) {
	
		die($e->getMessage());
	
	}
	
	
	//	Delete from tables in case we're re-running
	//	this script
	if (
		($new_conn->query('DELETE FROM `users`')===false) ||
		($new_conn->query('DELETE FROM `organizations`')===false) ||
		($new_conn->query('DELETE FROM `payment`')===false) ||
		($new_conn->query('DELETE FROM `membership_types`')===false) ||
		($new_conn->query('DELETE FROM `membership_years`')===false)
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
		
		$terr_unit=(
			is_null($row['otherProvince']) ||
			($row['otherProvince']==='')
		) ? $row['province'] : $row['otherProvince'];
		$country=$row['country'];
		
		fix_terr_unit($terr_unit,$country);
		fix_country($country);
		
		$query_text=sprintf(
			'INSERT INTO `organizations` (
				`name`,
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
			make_sql($new_conn,$country),						//	7
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
		);
		
		//	Insert data
		if ($new_conn->query($query_text)===false) die($new_conn->error."\r\n".$query_text);
	
	}
	
	
	//	Transform the users data
	
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
			`users`
			LEFT OUTER JOIN `organizations`
			ON `organizations`.`id`=`users`.`organizationsId`'
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
		if (is_null($row['organization_name'])) {
		
			$org_id=null;
		
		} else {
		
			$org_query=$new_conn->query(
				sprintf(
					'SELECT
						`id`
					FROM
						`organizations`
					WHERE
						`name`=\'%s\'',
					$new_conn->real_escape_string($row['organization_name'])
				)
			);
			
			if ($org_query===false) die($new_conn->error);
			
			if ($org_query->num_rows===0) {
			
				$org_id=null;
			
			} else {
			
				$org_row=$org_query->fetch_assoc();
				
				$org_id=$org_row['id'];
			
			}
		
		}
		
		//	Transform enabled field to new boolean
		//	values
		$enabled=($row['enabled']==='true') ? 1 : 0;
		
		//	Fix country/province/state
		$terr_unit=(
			is_null($row['otherProvince']) ||
			($row['otherProvince']==='')
		) ? $row['province'] : $row['otherProvince'];
		$country=$row['country'];
		
		fix_terr_unit($terr_unit,$country);
		fix_country($country);
		
		//	Transform to new user type
		switch ($row['rolesId']) {
		
			case 1:
				$type='admin';
				break;
			case 2:
			case 3:
				$type='superuser';
				break;
			case 4:
			default:
				$type='user';
				break;
		
		}
		
		$query_text=sprintf(
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
				`enabled`,
				`type`
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
				%16$s,
				%17$s
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
			make_sql($new_conn,$country),			//	12
			make_sql($new_conn,$row['phone']),		//	13
			make_sql($new_conn,$row['fax']),		//	14
			make_sql($new_conn,$org_id),			//	15
			make_sql($new_conn,$enabled),			//	16
			make_sql($new_conn,$type)				//	17
		);
		
		//	Insert data
		if ($new_conn->query($query_text)===false) die($new_conn->error."\r\n".$query_text);
	
	}
	
	
	//	Scrape payment information
	
	//	Get the years that certain organizations
	//	were new
	$query=$old_conn->query(
		'SELECT
			`organizationsId`,
			`membershipYear`
		FROM
			`transactions`
		WHERE
			`appliesto`=\'other\''
	);
	
	if ($query===false) die($old_conn->error);
	
	$year_new=array();
	
	while (!is_null($row=$query->fetch_assoc())) $year_new[$row['organizationsId']]=$row['membershipYear'];
	
	//	Get payment information
	$query=$old_conn->query(
		'SELECT
			`membershiptypes`.`name` AS `membershiptype_name`,
			`organizations`.`name` AS `org_name`,
			`transactions`.*
		FROM
			`transactions`
			LEFT OUTER JOIN (
				SELECT
					*
				FROM
					`onlinepaymentlog`
				WHERE
					`conferenceExhibitorId` IS NULL AND
					`conferenceDelegateId` IS NULL AND
					`transaction_approved`<>0 AND
					`transaction_result`=\'Transaction Normal\' AND
					`membershiptypeId` IS NOT NULL
				GROUP BY
					`organizationsId`,
					YEAR(`paymentdate`)
			) `onlinepaymentlog` ON (
				`onlinepaymentlog`.`organizationsId`=`transactions`.`organizationsId` AND
				`transactions`.`amount`=`onlinepaymentlog`.`amount` AND
				YEAR(`onlinepaymentlog`.`paymentdate`)=`transactions`.`membershipyear`
			)
			LEFT OUTER JOIN `membershiptypes`
			ON `membershiptypes`.`id`=`onlinepaymentlog`.`membershipTypeId`,
			`organizations`
		WHERE
			`transactions`.`appliesto`=\'membership\' AND
			`organizations`.`id`=`transactions`.`organizationsId` AND
			NOT (
				`membershiptypes`.`id` IS NULL AND
				`transactions`.`amount`=\'0.00\'
			)'
	);
	
	if ($query===false) die($old_conn->error);
	
	//	A map of years we've added to the
	//	`membership_years` table
	$years=array();
	
	//	Loop over each row of the payment
	//	information
	while (!is_null($row=$query->fetch_assoc())) {
	
		//	Get the corresponding organization
		$org_query=$new_conn->query(
			sprintf(
				'SELECT
					`id`
				FROM
					`organizations`
				WHERE
					`name`=\'%s\'',
				$new_conn->real_escape_string($row['org_name'])
			)
		);
		
		if ($org_query===false) die($new_conn->error);
		
		if ($org_query->num_rows===0) {
		
			var_dump($row);
		
			die('Org mismatch');
			
		}
		
		$org_row=$org_query->fetch_assoc();
		$org_id=$org_row['id'];
		
		//	Add this year if we haven't previously
		if (!isset($years[$row['membershipYear']])) {
			
			if ($new_conn->query(
				sprintf(
					'INSERT INTO `membership_years` (
						`name`,
						`start`,
						`end`
					) VALUES (
						\'%1$s\',
						\'%1$s-01-01 00:00:00\',
						\'%1$s-12-31 23:59:59\'
					)',
					$new_conn->real_escape_string($row['membershipYear'])
				)
			)===false) die($new_conn->error);
			
			//	Specify that we've now set this
			$years[$row['membershipYear']]=$new_conn->insert_id;
		
		}
		
		//	Get the membership type
		if (is_null($row['membershiptype_name'])) {
		
			$membership_type=null;
		
		} else {
		
			$mt_query=$new_conn->query(
				sprintf(
					'SELECT
						`id`
					FROM
						`membership_types`
					WHERE
						`name`=\'%s\'',
					$new_conn->real_escape_string($row['membershiptype_name'])
				)
			);
			
			if ($mt_query===false) die($new_conn->error);
			
			if ($mt_query->num_rows===0) {
			
				$membership_type=null;
			
			} else {
			
				$mt_row=$mt_query->fetch_assoc();
				$membership_type=$mt_row['id'];
			
			}
		
		}
		
		//	INSERT
		if ($new_conn->query(
			sprintf(
				'INSERT INTO `payment` (
					`membership_year_id`,
					`type`,
					`created`,
					`subtotal`,
					`tax`,
					`total`,
					`paid`,
					`org_id`,
					`datepaid`,
					`membership_type_id`,
					`amountpaid`,
					`notes`
				) VALUES (
					\'%1$s\',
					\'%2$s\',
					\'%3$s\',
					\'0.00\',
					\'0.00\',
					\'%4$s\',
					\'1\',
					\'%5$s\',
					\'%3$s\',
					%6$s,
					\'%4$s\',
					%7$s
				)',
				$new_conn->real_escape_string($years[$row['membershipYear']]),
				(
					(
						isset($year_new[$row['organizationsId']]) &&
						($year_new[$row['organizationsId']]==$row['membershipYear'])
					)
						?	'membership new'
						:	'membership renewal'
				),
				$new_conn->real_escape_string($row['entryDateTime']),
				$new_conn->real_escape_string($row['amount']),
				$new_conn->real_escape_string($org_id),
				is_null($membership_type) ? 'NULL' : '\''.$new_conn->real_escape_string($membership_type).'\'',
				(is_null($row['comments']) || ($row['comments']==='')) ? 'NULL' : '\''.$new_conn->real_escape_string($row['comments']).'\''
			)
		)===false) die($new_conn->error);
	
	}


?>