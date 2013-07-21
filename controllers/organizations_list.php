<?php


	$title='Organizations';


	//	Active, inactive?
	$active=$request->GetQueryString('active');
	if (is_null($active)) {
	
		$active=true;
	
	} else {
	
		$active=(
			($active===ALL_STRING)
				?	null
				:	(
						($active===FALSE_STRING)
							?	false
							:	true
					)
		);
	
	}
	
	//	Select the correct query
	$query=(
		is_null($active)
			?	Organization::GetAllQuery()
			:	(
					$active
						?	Organization::GetActiveQuery()
						:	Organization::GetInactiveQuery()
				)
	);

	//	Get the count
	$count=Organization::GetCount($query);
	
	//	Used shared paginated processing
	//	to get the page number et cetera
	require(WHERE_LOCAL_PHP_INCLUDES.'list_shared.php');
	
	//	Create and populate template
	$template=new Template(WHERE_TEMPLATES);
	$template->page=$page_num;
	$template->per_page=$num_per_page;
	$template->set_size=$count;
	$template->pages=$num_pages;
	$template->results=Organization::GetPage(
		$page_num,
		$num_per_page,
		'`name` ASC',
		$query
	);
	$template->active=$active;
	
	Render(
		$template,
		array(
			'list.phtml',
			'organizations_list.phtml'
		)
	);


?>