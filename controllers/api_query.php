<?php


	require_once(WHERE_PHP_INCLUDES.'mb.php');


	//	Handles selecting the appropriate query
	//	for the request-in-question
	function get_query () {
	
		global $api_request;
	
		if (isset($api_request->query)) {
		
			$users=($api_request->type==='users') || ($api_request->type==='user_count');
		
			//	A specific query was specified
			if ($api_request->query==='active') {
			
				$query=$users ? User::GetActiveQuery() : Organization::GetActiveQuery();
			
			} else if ($api_request->query==='inactive') {
			
				$query=$users ? User::GetInactiveQuery() : Organization::GetInactiveQuery();
			
			} else if (
				!$users &&
				($api_request->query==='pending')
			) {
			
				$query=Organization::GetPendingQuery();
			
			} else if ($api_request->query==='keyword') {
			
				if (!isset($api_request->keywords)) api_error(HTTP_BAD_REQUEST);
				
				if (is_string($api_request->keywords)) {
				
					if (($api_request->keywords=MBString::Trim($api_request->keywords))==='') api_error(HTTP_BAD_REQUEST);
				
					$keywords=preg_split(
						'/\\s+/u',
						$api_request->keywords
					);
				
				} else if (is_array($api_request->keywords)) {
				
					if (count($api_request->keywords)===0) api_error(HTTP_BAD_REQUEST);
				
					$keywords=$api_request->keywords;
				
				} else {
				
					api_error(HTTP_BAD_REQUEST);
				
				}
				
				$query=$users ? User::GetKeywordQuery($keywords) : Organization::GetKeywordQuery($keywords);
			
			} else {
			
				api_error(HTTP_BAD_REQUEST);
			
			}
		
		} else {
		
			//	No query was specified, default
			//	to all results
			
			$query=$users ? User::GetAllQuery() : Organization::GetAllQuery();
		
		}
		
		return $query;
	
	}


	//	An API query may be made for one of six things:
	//
	//	1.	A particular organization.
	//	2.	A particular user.
	//	3.	A set of organizations.
	//	4.	A set of users.
	//	5.	The number of organizations in a particular
	//		result set.
	//	6.	The number of users in a particular result
	//		set.
	//
	//	A query is at least:
	//
	//	{
	//		"action":	"query",
	//		"api_key":	<API key of API consumer>,
	//		"type":		"user"/"organization"/"users"/"organizations"/"user_count"/"organization_count"
	//	}
	
	
	//	Ensure the "type" property is present
	if (!isset($api_request->type)) api_error(HTTP_BAD_REQUEST);
	
	
	//	Branch
	
	//	Query for a single user or organization
	if (
		($api_request->type==='organization') ||
		($api_request->type==='user')
	) {
	
		//	An API query may be made for a particular user
		//	or organization by setting the "id" property to
		//	the numerical ID of the user or organization
		//	to retrieve.
		//
		//	An API query may be made for a particular user
		//	by setting the "username" property to the username
		//	or e-mail of the user to retrieve.
		//
		//	The response shall be:
		//
		//	For users:
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
		//	For organizations:
		//
		//	{
		//		"exists":		<true if the organization exists and the preceding
		//						 fields should be regarded, false otherwise>
		//		"organization":	<an object representing the organization>
		//	}
		
		if (isset($api_request->id)) {
		
			if (!is_integer($api_request->id)) api_error(HTTP_BAD_REQUEST);
			
			$api_result=($api_request->type==='user') ? User::GetByID($api_request->id) : Organization::GetByID($api_request->id);
		
		} else if (
			isset($api_request->username) &&
			is_string($api_request->username) &&
			($api_request->type==='user')
		) {
		
			$api_result=User::GetByUsername($api_request->username);
		
		} else {
		
			api_error(HTTP_BAD_REQUEST);
		
		}
		
		//	Does not exist
		if (is_null($api_result)) {
		
			$api_result=array('exists' => false);
		
		//	Exists
		} else {
		
			$api_result=($api_request->type==='user') ? $api_result->ToArray() : array('organization' => $api_result->ToArray());
			
			$api_result['exists']=true;
			
		}
		
	//	An API query involving a result set -- i.e. for
	//	a number of results or for the number of results
	//	in a given result set -- may be made with the
	//	following base:
	//
	//	{
	//		"action":	"query",
	//		"api_key":	<API key of API consumer>,
	//		"type":		"users"/"organizations"/"user_count"/"organization_count"
	//	}
	//	
	//	And the following optional segments merged into the
	//	base to refine the results to be retrieved/counted.
	//
	//	To paginate the results:
	//
	//	{
	//		"page":		<An integer given the page number, if
	//					 greater than the number of pages, it
	//					 will be changed to the number of the
	//					 last page>
	//		"per_page":	<OPTIONAL.  The number of results per
	//					 page, if ommitted defaults to 10>
	//	}
	//
	//	To specify a certain result set:
	//
	//	{
	//		"query":	"active"/"inactive"/"pending"/"keyword"
	//	}
	//
	//	"pending" is applicable to organizations only.
	//
	//	For the "keyword" query, the "keywords" property must
	//	be set.  It can either be a string with whitespace-delimited
	//	keywords, or an array of keywords (which will not be
	//	recursively processed to break them up based on whitespace).
	//
	//	The response for count queries will be:
	//
	//	{
	//		"count":	<The number of results in the set specified>
	//	}
	//
	//	For requests for results:
	//
	//	{
	//		"results":	<An array of result objects of the appropriate
	//					 type>
	//	}
	//
	//	the following fields will be added for paginated replies:
	//
	//	{
	//		"page":		<The actual page number retrieved>
	//		"per_page":	<The actual number of results per page>
	//		"count":	<The totas number of results in the result set>
	//		"pages":	<The total number of pages in the result set>
	//	}
	
	//	Query for a number of results
	} else if (
		($api_request->type==='users') ||
		($api_request->type==='organizations')
	) {
	
		//	Determine which query to use
		$query=get_query();
		
		//	Generate an ORDER BY string if appropriate
		$order_by=null;
		if (isset($api_request->order_by)) {
		
			if (!is_array($api_request->order_by)) $api_request->order_by=array($api_request->order_by);
			
			if (count($api_request->order_by)!==0) {
			
				$order_by='';
				
				foreach ($api_request->order_by as $x) {
				
					//	Default sort order is ascending
					$direction='ASC';
				
					if (is_object($x)) {
					
						if (!isset($x->column)) api_error(HTTP_BAD_REQUEST);
						
						if (isset($x->asc)) {
						
							if (!is_bool($x->asc)) api_error(HTTP_BAD_REQUEST);
							
							if (!$x->asc) $direction='DESC';
						
						}
						
						$column=$x->column;
					
					} else if (is_string($x)) {
					
						$column=$x;
					
					} else {
					
						api_error(HTTP_BAD_REQUEST);
					
					}
					
					if ($order_by!=='') $order_by.=',';
					
					$order_by.='`'.preg_replace(
						'/`/u',
						'``',
						$column
					).'` '.$direction;
				
				}
				
			}
		
		}
		
		//	Handle pagination if necessary
		$page_num=null;
		$num_per_page=null;
		$num_pages=null;
		$count=null;		
		if (isset($api_request->page)) {
		
			if (!is_integer($api_request->page)) api_error(HTTP_BAD_REQUEST);
			
			$page_num=$api_request->page;
			
			if (isset($api_request->per_page)) {
			
				if (!(
					is_integer($api_request->per_page) &&
					($api_request->per_page>0)
				)) api_error(HTTP_BAD_REQUEST);
				
				$num_per_page=$api_request->per_page;
			
			} else {
			
				$num_per_page=10;
			
			}
			
			$count=($api_request->type==='users') ? User::GetCount($query) : Organization::GetCount($query);
			
			$num_pages=intval($count/$num_per_page);
			if (($count%$num_per_page)!==0) ++$num_pages;
			
			if ($page_num>$num_pages) $page_num=$num_pages;
		
		}
		
		//	Perform query
		if ($api_request->type==='users') {
		
			//	Users
			
			$db_results=User::GetPage(
				$page_num,
				$num_per_page,
				$order_by,
				$query
			);
			
			$results=array();
			foreach ($db_results as $x) $results[]=$x->ToArray();
		
		} else {
		
			//	Organizations
			
			$db_results=Organization::GetPage(
				$page_num,
				$num_per_page,
				$order_by,
				$query
			);
			
			$results=array();
			foreach ($db_results as $x) $results[]=array('organization' => $x->ToArray());
		
		}
		
		$api_result=array(
			'results' => $results
		);
		
		if (!is_null($page_num)) {
		
			$api_result['page']=$page_num;
			$api_result['pages']=$num_pages;
			$api_result['count']=$count;
			$api_result['per_page']=$num_per_page;
		
		}
		
	} else if ($api_request->type==='user_count') {
	
		$api_result=array(
			'count' => User::GetCount(get_query())
		);
	
	} else if ($api_request->type==='organization_count') {
	
		$api_result=array(
			'count' => Organization::GetCount(get_query())
		);
	
	} else {
	
		api_error(HTTP_BAD_REQUEST);
	
	}


?>