<?php


	//	Only administrators may use this
	//	controller
	if ($user->type!=='admin') error(HTTP_FORBIDDEN);
	
	
	require_once(WHERE_PHP_INCLUDES.'key_value.php');
	
	
	$conn=$dependencies['MISADBConn'];
	
	
	$template=new Template(WHERE_TEMPLATES);
	
	
	$arg=$request->GetArg(0);
	
	
	if (is_null($arg)) {
	
		//	Display list of membership types
		
		$template->types=Organization::GetTypes();
		
		//	If there are no membership types, just
		//	skip right to adding one
		if (count($template->types)===0) goto add;
		
		$title='Edit Membership Types';
		
		usort(
			$template->types,
			function ($first, $second) {
			
				if (
					($first->order<1) &&
					($second->order>=1)
				) return 1;
				
				if (
					($second->order<1) &&
					($first->order>=1)
				) return -1;
			
				if ($first->order<$second->order) return -1;
				if ($first->order>$second->order) return 1;
			
				return Collator::Create(Collator::DEFAULT_VALUE)->compare(
					$first->name,
					$second->name
				);
			
			}
		);
		
		Render($template,'types_select.phtml');
		
	} else {
	
		if ($arg==='add') {
		
			add:
		
			$add=true;
			
			$template->type=new KeyValueStore();
			
			$title='Add Membership Type';
		
		} else if (
			is_numeric($arg) &&
			(($id=intval($arg))==floatval($arg))
		) {
		
			$add=false;
			
			//	Get the membership type to be
			//	edited from the database
			if (($query=$conn->query(
				sprintf(
					'SELECT * FROM `membership_types` WHERE `id`=\'%s\'',
					$conn->real_escape_string($id)
				)
			))===false) throw new Exception($conn->error);
			
			//	If that membership type doesn't
			//	exist, that's an error
			if ($query->num_rows===0) error(HTTP_BAD_REQUEST);
			
			$template->type=MySQLRow::FetchObject($query);
			
			$title='Edit Membership Type';
		
		} else {
		
			error(HTTP_BAD_REQUEST);
		
		}
		
		//	Handle POST backs
		if (is_post()) {
		
			//	Validate
			$template->type->name=fetch_post('name');
			$template->type->price=fetch_post('price');
			$template->type->description=fetch_post('description');
			$template->type->order=fetch_post_int('order');
			$template->type->is_municipality=fetch_post('is_municipality')===TRUE_STRING;
			$template->type->show=fetch_post('show')===TRUE_STRING;
			if (
				is_null($template->type->name) ||
				!is_numeric($template->type->price) ||
				is_null($template->type->order)
			) error(HTTP_BAD_REQUEST);
			$template->type->price=floatval($template->type->price);
			
			//	Save to database
			if ($add) {
			
				//	Adding a new entry
				$insert_fields='';
				$insert_values='';
				foreach ($template->type as $key=>$value) {
				
					if ($insert_fields!=='') {
					
						$insert_fields.=',';
						$insert_values.=',';
					
					}
					
					$insert_fields.='`'.preg_replace(
						'/`/u',
						'``',
						$key
					).'`';
					$insert_values.=(
						is_null($value)
							?	'NULL'
							:	'\''.$conn->real_escape_string(
									is_bool($value)
										?	($value ? 1 : 0)
										:	$value
								).'\''
					);
				
				}
				
				//	INSERT
				if ($conn->query(
					sprintf(
						'INSERT INTO `membership_types` (%s) VALUES (%s)',
						$insert_fields,
						$insert_values
					)
				)===false) throw new Exception($conn->error);
				
				//	Redirect to the edit page
				//	for this membership type
				header(
					'Location: '.$request->MakeLink(
						'membership_types',
						$conn->insert_id
					)
				);
				
				exit();
			
			} else {
			
				//	Editing an existing entry
				$update='';
				foreach ($template->type as $key=>$value) if ($key!=='id') {
				
					if ($update!=='') $update.=',';
					
					$update.='`'.preg_replace(
						'/`/u',
						'``',
						$key
					).'`='.(
						is_null($value)
							?	'NULL'
							:	'\''.$conn->real_escape_string(
									is_bool($value)
										?	($value ? 1 : 0)
										:	$value
								).'\''
					);
				
				}
				
				//	UPDATE
				if ($conn->query(
					sprintf(
						'UPDATE `membership_types` SET %s WHERE `id`=\'%s\'',
						$update,
						$conn->real_escape_string($template->type->id)
					)
				)===false) throw new Exception($conn->error);
			
			}
		
		}
		
		Render($template,'type_form.phtml');
	
	}
	

?>