<?php


	$title='Users';

	//	Active, inactive?
	$active=$request->GetQueryString('active');
	$active=$request->GetQueryString('active');
	if (is_null($active)) $active=true;
	else if ($active===ALL_STRING) $active=null;
	else $active=$active===TRUE_STRING;

	//	Get the count
	$count=User::GetCount($active);
	
	//	Use shared paginated processing
	//	to get the page number et cetera
	require(WHERE_LOCAL_PHP_INCLUDES.'list_shared.php');
	
	//	Create and populate template
	$template=new Template(WHERE_TEMPLATES);
	$template->page=$page_num;
	$template->per_page=$num_per_page;
	$template->set_size=$count;
	$template->pages=$num_pages;
	$template->results=User::GetPage(
		$page_num,
		$num_per_page,
		'`last_name` ASC,`first_name` ASC',
		$active
	);
	$template->inner_template='users_list.phtml';
	$template->active=$active;
	
	Render($template,'list.phtml');


?>