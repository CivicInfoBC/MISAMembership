<?php


	def('DEFAULT_PER_PAGE',25);
	def('PER_PAGE_KEY','results_per_page');
	def('PAGE_ARG','page');


	//	Get the desired page
	
	$page_num=is_null($request->GetArg(0)) ? $request->GetQueryString(PAGE_ARG) : $request->GetArg(0);
	
	//	Default to first page if no
	//	page argument -- or a bad page
	//	argument -- provided
	if (
		is_null($page_num) ||
		!is_numeric($page_num) ||
		(intval($page_num)!=floatval($page_num)) ||
		(($page_num=intval($page_num))<=0)
	) $page_num=1;
	
	//	Get number per page
	
	//	Default to default number per page
	//	if no argument provided
	if (is_null($request->GetQueryString(PER_PAGE_KEY))) {
	
		$num_per_page=DEFAULT_PER_PAGE;
	
	//	If there is an argument, check
	//	to make sure it's a sane integer
	} else if (!(
		is_numeric($request->GetQueryString(PER_PAGE_KEY)) &&
		(($num_per_page=intval($request->GetQueryString(PER_PAGE_KEY)))==floatval($request->GetQueryString(PER_PAGE_KEY))) &&
		($num_per_page>0)
	)) {
	
		//	Not a sane integer, fail
		error(HTTP_BAD_REQUEST);
	
	}
	
	//	Determine the number of pages
	//	in the result set
	$num_pages=intval($count/$num_per_page);
	//	If the number per page does not
	//	evenly divide the count, we need
	//	another page to hold the remainder
	if (($count%$num_per_page)!==0) ++$num_pages;
	//	If there are no pages, there's just
	//	one empty page
	if ($num_pages===0) $num_pages=1;
	
	//	Was a page outside the range requested?
	//
	//	If so, give the last page
	if ($page_num>$num_pages) $page_num=$num_pages;


?>