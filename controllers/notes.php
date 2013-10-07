<?php


	//	Only administrators can use this
	//	controller
	if ($user->type!=='admin') error(HTTP_FORBIDDEN);
	
	
	$template=new Template(WHERE_TEMPLATES);
	
	
	if (is_null($request->GetArg(0))) error(HTTP_BAD_REQUEST);
	
	
	$conn=$dependencies['MISADBConn'];
	
	
	//	If we're deleting, do so
	if ($request->GetArg(0)==='delete') {
	
		//	Verify that the ID we've been
		//	passed is actually numeric
		if (!(
			is_numeric($request->GetArg(1)) &&
			(($id=intval($request->GetArg(1)))==floatval($request->GetArg(1)))
		)) error(HTTP_BAD_REQUEST);
		
		//	Attempt to retrieve the organization
		//	associated with this note so we
		//	can redirect to it.
		if (($query=$conn->query(
			sprintf(
				'SELECT `org_id` FROM `organization_notes` WHERE `id`=\'%s\'',
				$conn->real_escape_string($id)
			)
		))===false) throw new Exception($conn->error);
		
		//	If there is no such note, this is
		//	a bad request
		if ($query->num_rows===0) error(HTTP_BAD_REQUEST);
		
		$org_id=MySQLRow::FetchObject($query)->org_id;
		
		//	Attempt to delete
		if ($conn->query(
			sprintf(
				'DELETE FROM `organization_notes` WHERE `id`=\'%s\'',
				$conn->real_escape_string($id)
			)
		)===false) throw new Exception($conn->error);
		
		//	Redirect
		header(
			'Location: '.$request->MakeLink(
				'organization',
				$org_id
			)
		);
		
		//	STOP
		exit();
	
	}
	
	
	//	Gather necessary information
	//	and validate arguments
	if ($request->GetArg(0)==='add') {
	
		//	Adding new note
		
		$title='Add Note';
	
		$add=true;
		
		$org_id=$request->GetArg(1);
		
		if (!(
			is_numeric($org_id) &&
			(($org_id=intval($org_id))==floatval($request->GetArg(1)))
		)) error(HTTP_BAD_REQUEST);
		
		$template->organization=Organization::GetByID($org_id);
		
		if (is_null($template->organization)) error(HTTP_BAD_REQUEST);
		
		$template->note=new KeyValueStore();
	
	} else if (
		is_numeric($id=$request->GetArg(0)) &&
		(($id=intval($id))==floatval($request->GetArg(0)))
	) {
	
		//	Editing existing note
		
		$title='Edit Note';
		
		$add=false;
		
		//	Fetch note
		if (($query=$conn->query(
			sprintf(
				'SELECT * FROM `organization_notes` WHERE `id`=\'%s\'',
				$conn->real_escape_string($id)
			)
		))===false) throw new Exception($conn->error);
		
		if ($query->num_rows===0) error(HTTP_BAD_REQUEST);
		
		$template->note=MySQLRow::FetchObject($query);
		
		$template->organization=Organization::GetByID($template->note->org_id);
		$template->created_by=User::GetByID($template->note->created_by);
		$template->modified_by=User::GetByID($template->note->modified_by);
		
		//	If the organization isn't present,
		//	given that it is referenced by a
		//	foreign key, it's either a
		//	database engine failure or a case
		//	of concurrent access
		if (is_null($template->organization)) error(HTTP_INTERNAL_SERVER_ERROR);		
	
	} else {
	
		error(HTTP_BAD_REQUEST);
	
	}
	
	
	$template->add=$add;
	
	
	//	Handle POST backs
	if (is_post()) {
	
		$text=fetch_post('text');
		if (is_null($text)) error(HTTP_BAD_REQUEST);
	
		if ($add) {
		
			//	Adding a new note
			
			//	INSERT
			if ($conn->query(
				sprintf(
					'INSERT INTO `organization_notes` (
						`created`,
						`created_by`,
						`modified`,
						`modified_by`,
						`org_id`,
						`text`
					) VALUES (
						NOW(),
						\'%s\',
						NOW(),
						\'%s\',
						\'%s\',
						\'%s\'
					)',
					$conn->real_escape_string($user->id),
					$conn->real_escape_string($user->id),
					$conn->real_escape_string($org_id),
					$conn->real_escape_string($text)
				)
			)===false) throw new Exception($conn->error);
			
			//	Redirect back to organization page
			header(
				'Location: '.$request->MakeLink(
					'organization',
					$org_id
				)
			);
			
			exit();
		
		//	Editing existing note
		//
		//	Since we track modification time,
		//	ensure the note was actually
		//	modified before updating
		} else if (!MBString::Compare($text,$template->note->text)) {
		
			$template->note->text=$text;
		
			//	UPDATE
			if ($conn->query(
				sprintf(
					'UPDATE
						`organization_notes`
					SET
						`text`=\'%s\',
						`modified`=NOW(),
						`modified_by`=\'%s\'
					WHERE
						`id`=\'%s\'',
					$conn->real_escape_string($text),
					$conn->real_escape_string($user->id),
					$conn->real_escape_string($template->note->id)
				)
			)===false) throw new Exception($conn->error);
			
			//	Update modified time/modified by for
			//	client as well
			$template->note->modified=new DateTime();
			$template->modified_by=$user;
			
			//	Redirect back to organization page
			header(
				'Location: '.$request->MakeLink(
					'organization',
					$template->note->org_id
				)
			);
			
			exit();
		
		}
	
	}
	
	
	//	Render
	Render($template,'notes_form.phtml');


?>