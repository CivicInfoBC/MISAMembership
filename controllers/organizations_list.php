<?php


	$title='Organizations';


	//	Active, inactive?
	$active=$request->GetQueryString('active');
	if (!is_null($active)) $active=$active===TRUE_STRING;

	//	Get the count
	$count=Organization::GetCount($active);
	
	//	Used shared paginated processing
	//	to get the page number et cetera
	require(WHERE_LOCAL_PHP_INCLUDES.'list_shared.php');
	
	//	Create and populated template
	$template=new Template(WHERE_TEMPLATES);
	$template->page=$page_num;
	$template->per_page=$num_per_page;
	$template->set_size=$count;
	$template->pages=$num_pages;
	$template->results=Organization::GetPage(
		$page_num,
		$num_per_page,
		'`name` ASC',
		$active
	);
	$template->inner_template='organizations_list.phtml';
	$template->active=$active;
	
	Render($template,'list.phtml');
	


?>