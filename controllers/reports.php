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
							org_id,
							MAX(datepaid),
							id payment_id
						FROM
							payment
						WHERE
							paid AND
							type IN (\'membership renewal\',\'membership other\')
						GROUP BY
							org_id
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