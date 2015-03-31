<?php


	//	User must be a sitewide admin to view
	//	reports
	if ($user->type!=='admin') error(HTTP_FORBIDDEN);
	
	
	//	Reports
	$reports=array(
		'organizations' => (object)array(
			'columns' => array(
				'id' => 'ID',
				'name' => 'Name',
				'address1' => 'Address',
				'address2' => 'Address (Cont.)',
				'city' => 'City',
				'postal_code' => 'Postal Code',
				'territorial_unit' => 'Province',
				'country' => 'Country',
				'phone' => 'Phone',
				'fax' => 'Fax',
				'url' => 'URL',
				'contact_name' => 'Contact Name',
				'contact_title' => 'Contact Title',
				'contact_email' => 'Contact E-Mail',
				'contact_phone' => 'Contact Phone',
				'contact_fax' => 'Contact Fax',
				'secondary_contact_name' => 'Secondary Contact Name',
				'secondary_contact_title' => 'Secondary Contact Title',
				'secondary_contact_email' => 'Secondary Contact E-Mail',
				'secondary_contact_phone' => 'Secondary Contact Phone',
				'secondary_contact_fax' => 'Secondary Contact Fax',
				'perpetual' => 'Perpetual?',
				'enabled' => 'Enabled?',
				'type_name' => 'Membership Type',
				'price' => 'Price',
				'is_municipality' => 'Municipality?',
				'invoice' => 'Invoice #',
				'payment_type' => 'Payment Type',
				'invoice_amount' => 'Total',
				'amountpaid' => 'Amount Paid',
				'datepaid' => 'Date Paid',
				'membership_year' => 'Membership Year'
			),
			'query' =>
				'SELECT
					o.id,
					o.name,
					o.address1,
					o.address2,
					o.city,
					o.postal_code,
					o.territorial_unit,
					o.country,
					o.phone,
					o.fax,
					o.url,
					o.contact_name,
					o.contact_title,
					o.contact_email,
					o.contact_phone,
					o.contact_fax,
					o.secondary_contact_name,
					o.secondary_contact_title,
					o.secondary_contact_email,
					o.secondary_contact_phone,
					o.secondary_contact_fax,
					IF(o.perpetual,\'Yes\',\'No\') perpetual,
					IF(o.enabled,\'Yes\',\'No\') enabled,
					t.name AS type_name,
					CONCAT(\'$\',FORMAT(t.price,2)) price,
					IF(t.is_municipality,\'Yes\',\'No\') is_municipality,
					p.invoice,
					p.type payment_type,
					CONCAT(\'$\',FORMAT(p.total,2)) invoice_amount,
					CONCAT(\'$\',FORMAT(p.amountpaid,2)) amountpaid,
					p.datepaid,
					p.membership_year
				FROM
					organizations o
					LEFT JOIN membership_types t ON o.membership_type_id=t.id
					LEFT JOIN (
						SELECT
							p.org_id,
							p.datepaid,
							p.id payment_id
						FROM
							payment p,
							(
								SELECT
									org_id,
									MAX(datepaid) datepaid
								FROM
									payment
								WHERE
									paid AND
									type IN (\'membership renewal\',\'membership other\')
								GROUP BY
									org_id
							) m
						WHERE
							p.datepaid=m.datepaid AND
							p.org_id=m.org_id
						GROUP BY
							p.org_id
					) ps ON o.id = ps.org_id
					LEFT JOIN (
						SELECT
							in2.id payment_id,
							in2.org_id,
							in2.invoice,
							in2.type,
							in2.created,
							in2.total,
							in2.amountpaid,
							in2.datepaid,
							in3.name membership_year
						FROM
							payment in2
							INNER JOIN membership_years in3 ON in2.membership_year_id=in3.id
					) p ON ps.payment_id = p.payment_id'
		),
		'users' => (object)array(
			'columns' => array(
				'id' => 'ID',
				'first_name' => 'First Name',
				'last_name' => 'Last Name',
				'email' => 'E-Mail',
				'title' => 'Title',
				'address' => 'Address',
				'address2' => 'Address (Cont.)',
				'city' => 'City',
				'territorial_unit' => 'Province',
				'country' => 'Country',
				'phone' => 'Phone',
				'cell' => 'Mobile',
				'fax' => 'Fax',
				'enabled' => 'Enabled?',
				'type' => 'Type',
				'opt_out' => 'Opt Out?',
				'org_id' => 'Organization ID',
				'org_name' => 'Organization Name',
				'membership_type' => 'Membership Type',
				'is_municipality' => 'Municipality?',
				'org_enabled' => 'Organization Enabled?',
				'org_perpetual' => 'Organization Perpetual?'
			),
			'query' =>
				'SELECT
					u.id,
					u.first_name,
					u.last_name,
					u.email,
					u.title,
					u.address,
					u.address2,
					u.city,
					u.territorial_unit,
					u.postal_code,
					u.country,
					u.phone,
					u.cell,
					u.fax,
					u.org_id,
					IF(u.enabled,\'Yes\',\'No\') enabled,
					u.type,
					IF(u.opt_out,\'Yes\',\'No\') opt_out,
					o.name org_name,
					m.name membership_type,
					IF(m.is_municipality,\'Yes\',\'No\') is_municipality,
					IF(o.enabled,\'Yes\',\'No\') org_enabled,
					IF(o.perpetual,\'Yes\',\'No\') org_perpetual
				FROM
					users u
					LEFT JOIN organizations o ON u.org_id = o.id
					LEFT JOIN membership_types m ON o.membership_type_id = m.id'
		),
		'cc_payments' => (object)array(
			'columns' => array(
				'org_name' => 'Organization Name',
				'datepaid' => 'Date Paid',
				'paymethod' => 'Card Type',
				'cardname' => 'Name on Credit Card',
				'amountpaid' => 'Amount'
			),
			'query' =>
				'SELECT
					`organizations`.`name` `org_name`,
					`payment`.`datepaid` `datepaid`,
					`payment`.`paymethod` `paymethod`,
					`payment`.`cardname` `cardname`,
					CONCAT(\'$\',FORMAT(`payment`.`amountpaid`,2)) `amountpaid`
				FROM
					`organizations`,
					`payment`
				WHERE
					`organizations`.`id`=`payment`.`org_id` AND
					`cardname` IS NOT NULL
				ORDER BY
					`datepaid` DESC'
		)
	);
	
	
	//	Retrieve requested report
	$name=$request->GetArg(0);
	if (is_null($name) || !isset($reports[$name])) error(404);
	$report=$reports[$name];
	
	
	$conn=$dependencies['MISADBConn'];
	if (($query=$conn->query($report->query))===false) throw new \Exception($conn->error);
	
	
	$template=new \Template(WHERE_TEMPLATES);
	$template->report=$report;
	$template->query=$query;
	
	
	$template->Render('csv.phtml');


?>