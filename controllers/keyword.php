<?php


	//	Get the keywords
	
	unset($keywords_str);
	
	if (is_post()) {
	
		if (!isset($_POST['keywords'])) error(HTTP_BAD_REQUEST);
		
		$keywords_str=$_POST['keywords'];
	
	} else if (!is_null($request->GetQueryString('keywords'))) {
	
		$keywords_str=$request->GetQueryString('keywords');
	
	}
	
	if (
		!isset($keywords_str) ||
		is_null($keywords_str) ||
		(($keywords_str=MBString::Trim($keywords_str))==='')
	) error(HTTP_BAD_REQUEST);
	
	
	$title='Search Results';
	
	
	//	Split up keywords
	$keywords=preg_split('/\\s+/u',$keywords_str);
	
	
	//	Get organization results
	
	$query=Organization::GetKeywordQuery($keywords);
	
	//	Get count
	$count=Organization::GetCount($query);
	
	//	Use shared paginated processing
	require(WHERE_LOCAL_PHP_INCLUDES.'list_shared.php');
	
	$org_template=new Template(WHERE_TEMPLATES);
	$org_template->page=$page_num;
	$org_template->per_page=$num_per_page;
	$org_template->set_size=$count;
	$org_template->pages=$num_pages;
	$org_template->results=Organization::GetPage(
		$page_num,
		$num_per_page,
		'`name` ASC',
		$query
	);
	$org_template->no_type_select=true;
	$org_template->keywords=$keywords_str;
	
	
	//	Get user results
	
	$query=User::GetKeywordQuery($keywords);
	
	//	Get count
	$count=User::GetCount($query);
	
	//	Use shared paginated processing
	require(WHERE_LOCAL_PHP_INCLUDES.'list_shared.php');
	
	$user_template=new Template(WHERE_TEMPLATES);
	$user_template=new Template(WHERE_TEMPLATES);
	$user_template->page=$page_num;
	$user_template->per_page=$num_per_page;
	$user_template->set_size=$count;
	$user_template->pages=$num_pages;
	$user_template->results=User::GetPage(
		$page_num,
		$num_per_page,
		'`last_name` ASC,`first_name` ASC',
		$query
	);
	$user_template->no_type_select=true;
	$user_template->keywords=$keywords_str;
	
	
	//	Render
	$template=new Template(WHERE_TEMPLATES);
	$template->org=$org_template;
	$template->user=$user_template;
	Render(
		$template,
		'keyword.phtml'
	);
	

?>