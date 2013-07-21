<?php


	def('DEFAULT_PER_PAGE',10);
	def('DEFAULT_PAGE',1);
	
	
	//	Abstracts away "order_by" handling.
	function flatten_order_by ($order_by) {
	
		//	Sanity checks
		if (!(
			is_object($order_by) &&
			isset($order_by->column) &&
			(
				!isset($order_by->asc) ||
				is_bool($order_by->asc)
			)
		)) api_error(HTTP_BAD_REQUEST);
		
		//	Prepare ORDER BY clause
		return '`'.preg_replace(
			'/`/u',
			'``',
			$order_by->column
		).'` '.(
			(
				isset($order_by->asc) &&
				!$order_by->asc
			)
				?	'DESC'
				:	'ASC'
		);
	
	}


	//	An API query request may be made for either users
	//	or organizations.
	//
	//
	//	An API query request is at least:
	//
	//	{
	//		"action":	"query"
	//		"api_key":	<API key of API consumer>,
	//		"type":		<type of query to perform>
	//	}
	
	
	//	Make sure the "type" property
	//	is set
	if (!isset($api_request->type)) api_error(HTTP_BAD_REQUEST);

	
	//	Sanity checks on the "active"
	//	property, and turn its absence
	//	into something meaningful
	if (isset($api_request->active)) {
	
		if (!is_bool($api_request->active)) api_error(HTTP_BAD_REQUEST);
		
		$active=$api_request->active;
	
	} else {
	
		$active=null;
	
	}
	
	
	//	Get the query we'll be using
	$query=(
		(
			($api_request->type==='user') ||
			($api_request->type==='user_count')
		)
			?	(
					is_null($active)
						?	User::GetAllQuery()
						:	(
								$active
									?	User::GetActiveQuery()
									:	User::GetInactiveQuery()
							)
				)
			:	(
					is_null($active)
						?	Organization::GetAllQuery()
						:	(
								$active
									?	Organization::GetActiveQuery()
									:	Organization::GetInactiveQuery()
							)
				)
	);
	
	
	if (
		($api_request->type==='user') ||
		($api_request->type==='organization')
	) {
	
		
		//	An API query request for users or organizations shall
		//	be as follows:
		//
		//
		//	To query information for a specific user:
		//
		//	{
		//		"action":	"query",
		//		"api_key":	<API key of API consumer>,
		//		"type":		"user"/"organization",
		//		"id":		<numerical ID of user>
		//	}
		//
		//	The response for users shall be:
		//
		//	{
		//		"exists":		<true if the user exists and the preceding
		//						 fields should be regarded, false otherwise>
		//		"user":			<an object representing the user>,
		//		"organization":	<an object representing the organization
		//						 the user is a member of, or null if they're
		//						 not a member of any organization
		//	}
		//
		//	The response for organizations shall be:
		//
		//	{
		//		"exists":		<true if the organization exists and the preceding
		//						 fields should be regarded, false otherwise>
		//		"organization":	<an object representing the organization>
		//	}
		//
		//
		//	To obtain bulk user/organization information:
		//
		//	{
		//		"action":	"query",
		//		"api_key":	<API key of API consumer>,
		//		"type":		"user"/"organization",
		//		"page":		<page of results to show>,
		//		"per_page":	<number of results per page>,
		//		"order_by":	[
		//						{
		//							"column":	<column to order by>,
		//							"asc":		<
		//											true for ascending sort,
		//											false for descending sort,
		//											if omitted ascending is assumed
		//										>
		//						},
		//						...repeat as needed...
		//					]
		//		"active":	<Optional, may be set to true, false
		//					 or null.  If true only active users
		//					 or organizations will be counted, if
		//					 false only inactive users or organizations
		//					 will be counted.  If null all users
		//					 or organizations will be counted.>
		//	}
		//
		//	The response shall be:
		//
		//	{
		//		"page":			<the page actually being delivered>
		//		"count":		<the number of users>
		//		"num_pages":	<the total number of pages>
		//		"per_page":		<number of results per page,
		//						 not necessary the number of results
		//						 in "results">
		//		"results":[
		//			{
		//				"user":			...
		//				"organization":	...
		//			}
		//			...repeat up to the number of results per page...
		//		]
		//	}
		
		
		//	Handle single query
		if (isset($api_request->id)) {
		
			//	Sanity check on the ID
			if (!is_integer($api_request->id)) api_error(HTTP_BAD_REQUEST);
			
			//	Attempt to get result by ID
			$api_result=(
				($api_request->type==='user')
					?	User::GetByID($api_request->id)
					:	Organization::GetByID($api_request->id)
			);
			
			if (is_null($api_result)) {
			
				//	Does not exist
			
				$api_result=array(
					'exists' => false
				);
			
			} else {
			
				//	Exists
				
				if ($api_request->type==='user') {
				
					$api_result=$api_result->ToArray();
				
				} else {
				
					$arr=array();
					$arr['organization']=$api_result->ToArray();
					$api_result=$arr;
				
				}
				
				$api_result['exists']=true;
			
			}
		
		//	Handle paginated query
		} else {
		
			//	Get page number or set to
			//	default
			if (isset($api_request->page)) {
			
				//	Sanity check
				if (
					!is_integer($api_request->page) ||
					($api_request->page<=0)
				) api_error(HTTP_BAD_REQUEST);
				
				$page_num=$api_request->page;
			
			} else {
			
				$page_num=DEFAULT_PAGE;
			
			}
			
			//	Get number of results per
			//	page or set to default
			if (isset($api_request->per_page)) {
			
				//	Sanity check
				if (
					!is_integer($api_request->per_page) ||
					($api_request->per_page<=0)
				) api_error(HTTP_BAD_REQUEST);
				
				$num_per_page=$api_request->per_page;
			
			} else {
			
				$num_per_page=DEFAULT_PER_PAGE;
			
			}
			
			//	Sanity check the "order_by" property
			if (
				isset($api_request->order_by) &&
				!is_array($api_request->order_by)
			) api_error(HTTP_BAD_REQUEST);
			
			//	Get the number of results
			$count=($api_request->type==='user') ? User::GetCount($query) : Organization::GetCount($query);
			
			//	Determine the number of pages
			//	of results
			$num_pages=intval($count/$num_per_page);
			//	Add one if the last page
			//	would contain less than
			//	$num_per_page results (due
			//	to integer division truncating
			//	remainder)
			if (($count%$num_per_page)!==0) ++$num_pages;
			//	Are there no pages?
			//
			//	That wouldn't make sense, set
			//	it to 1
			if ($num_pages===0) $num_pages=1;
			
			//	Did the user request a page that's
			//	out of range?
			//
			//	If so, give them the last page
			if ($page_num>$num_pages) $page_num=$num_pages;
			
			//	Prepare ORDER BY clause
			$order_by='';
			$first=true;
			if (isset($api_request->order_by)) {

				foreach ($api_request->order_by as $obj) {
				
					if ($first) $first=false;
					else $order_by.=',';
					
					$order_by.=flatten_order_by($obj);
				
				}
				
			}
			
			if ($api_request->type==='user') {
			
				//	Retrieve results
				$results=User::GetPage(
					$page_num,
					$num_per_page,
					$order_by,
					$query
				);
				
				//	Generate output
				$results_json=array();
				foreach ($results as $x) $results_json[]=$x->ToArray();
			
			} else {
			
				//	Retrieve results
				$results=Organization::GetPage(
					$page_num,
					$num_per_page,
					$order_by,
					$query
				);
				
				//	Generate output
				$results_json=array();
				foreach ($results as $x) {
				
					$arr=array();
					$arr['organization']=$x->ToArray();
					
					$results_json[]=$arr;
				
				}
			
			}
			
			$api_result=array(
				'page' => $page_num,
				'num_pages' => $num_pages,
				'count' => $count,
				'per_page' => $num_per_page,
				'results' => $results_json
			);
		
		}
		
	
	//	Number of results
	} else if ($api_request->type==='user_count') {
	
	
		//	An API query request for the number of users
		//	or organizations shall be as follows:
		//
		//	{
		//		"action":	"query",
		//		"api_key":	<API key of API consumer>,
		//		"type":		"user_count"/"organization_count"
		//		"active":	<Optional, may be set to true, false
		//					 or null.  If true only active users
		//					 or organizations will be counted, if
		//					 false only inactive users or organizations
		//					 will be counted.  If null all users
		//					 or organizations will be counted.>
		//	}
		//
		//	The response shall be:
		//
		//	{
		//		"count":	<the number of users in the database>
		//	}
		
		
		$api_result=array(
			'count' => User::GetCount($query)
		);
		
	
	} else if ($api_request->type==='organization_count') {
	
		$api_result=array(
			'count' => Organization::GetCount($query)
		);
	
	} else {
	
		api_error(HTTP_BAD_REQUEST);
	
	}


?>