<?php


	$template=new Template(WHERE_TEMPLATES);
	
	
	if (
		//	Only perform search if it's
		//	a POST request
		is_post() &&
		//	There must be POST parameters
		isset($_POST['keywords']) &&
		//	The POST parameters must not
		//	be empty
		(($keywords_str=MBString::Trim($_POST['keywords']))!=='')
	) {
	
		//	Perform search/display results
		
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
		$template->org=$org_template;
		$template->user=$user_template;
		Render(
			$template,
			'keyword.phtml'
		);
	
	} else {
	
		//	Display form
		
		$title='Search';
		
		Render(
			$template,
			'keyword_search.phtml'
		);
		
	}
	
	
?>