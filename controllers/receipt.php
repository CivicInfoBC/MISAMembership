<?php


	//	Don't do any further processing if this is
	//	an e-xact test request
	if ($request->GetQueryString('network_test')==='true') exit();


	require_once(WHERE_PHP_INCLUDES.'e-xact.php');
	
	
	//	Verify the request
	if (!(
		//	Must be a POST request
		is_post() &&
		//	Response code must be set and
		//	must be an integer
		isset($_POST['x_response_code']) &&
		!is_null($code=to_int($_POST['x_response_code'])) &&
		//	Transaction ID must be set
		isset($_POST['x_trans_id']) &&
		//	Customer ID must be set and
		//	match a certain structure
		isset($_POST['x_cust_id']) &&
		(preg_match(
			'/^(\\d+)\\|(\\d+)$/u',
			$_POST['x_cust_id'],
			$matches
		)===1) &&
		//	Card holder's name must be set
		//	and not be the empty string
		isset($_POST['CardHoldersName']) &&
		($_POST['CardHoldersName']!=='') &&
		//	Transaction card type must be set
		//	and not be the empty string
		isset($_POST['TransactionCardType']) &&
		($_POST['TransactionCardType']!=='') &&
		//	Hash must be set and not be the
		//	empty string
		isset($_POST['x_MD5_Hash']) &&
		($_POST['x_MD5_Hash']!=='') &&
		//	Amount must be set and must be a
		//	float
		isset($_POST['x_amount']) &&
		is_numeric($_POST['x_amount']) &&
		//	Verify hash
		EXact::Verify(
			$_POST['x_MD5_Hash'],
			$amount=floatval($_POST['x_amount']),
			$_POST['x_trans_id']
		)
	)) error(HTTP_BAD_REQUEST);
	
	
	//	Only proceed if the transaction was
	//	successful and was not a test
	if (
		($code!==1) ||
		(
			isset($_POST['x_test_request']) &&
			($_POST['x_test_request']==='TRUE')
		)
	) exit();
	
	
	//	Get the corresponding row from the database
	
	$conn=$dependencies['MISADBConn'];
	
	if (
		//	Lock
		($conn->query('LOCK TABLES `payment` WRITE')===false) ||
		//	Get row
		(($query=$conn->query(
			sprintf(
				'SELECT * FROM `payment` WHERE `org_id`=\'%s\' AND `membership_year_id`=\'%s\'',
				$conn->real_escape_string($matches[1]),
				$conn->real_escape_string($matches[2])
			)
		))===false)
	) throw new Exception($conn->error);
	
	//	Error out if row does not exist
	if ($query->num_rows===0) error(HTTP_BAD_REQUEST);
	
	$row=new MySQLRow($query);
	
	//	Do some checking
	if (
		//	If the totals do not match up...
		($row['total']->GetValue()!==$amount) ||
		//	...or the payment has already been
		//	made...
		$row['paid']->GetValue()
	//	...error
	) error(HTTP_BAD_REQUEST);
	
	if (
		//	Mark as paid
		($conn->query(
			sprintf(
				'UPDATE
					`payment`
				SET
					`paid`=\'1\',
					`cardname`=\'%s\',
					`paymethod`=\'%s\',
					`amountpaid`=\'%s\',
					`datepaid`=NOW()
				WHERE
					`id`=\'%s\'',
				$conn->real_escape_string($_POST['CardHoldersName']),
				$conn->real_escape_string($_POST['TransactionCardType']),
				$conn->real_escape_string($amount),
				$conn->real_escape_string($row['id'])
			)
		)===false) ||
		//	Unlock
		($conn->query('UNLOCK TABLES')===false)
	) throw new Exception($conn->error);


?>