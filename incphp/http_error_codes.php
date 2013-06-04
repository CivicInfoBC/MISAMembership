<?php


	$http_error_code_map=array(
		100 => array(
			'title' => 'Continue',
			'desc' => 'Request headers received, proceed to send request body'
		),
		101 => array(
			'title' => 'Switching Protocols',
			'desc' => 'Server will switch protocols as per client\'s request'
		),
		102 => array(
			'title' => 'Processing',
			'desc' => 'Your request is being processed but no response is available yet'
		),
		200 => array(
			'title' => 'OK',
			'desc' => 'Your request was successful'
		),
		201 => array(
			'title' => 'Created',
			'desc' => 'Your request has been fulfilled and a new resource has been created'
		),
		202 => array(
			'title' => 'Accepted',
			'desc' => 'Your request has been accepted, but processing is not yet completed'
		),
		203 => array(
			'title' => 'Non-Authoritative Information',
			'desc' => 'Your request has been successfully processed, but the returned information may be from another source'
		),
		204 => array(
			'title' => 'No Content',
			'desc' => 'Your request was successful, but no content is being returned'
		),
		205 => array(
			'title' => 'Reset Content',
			'desc' => 'Your request was successful, but no content is being returned and you must reset your document view'
		),
		206 => array(
			'title' => 'Partial Content',
			'desc' => 'Only part of the resource is being delivered due to a range header'
		),
		207 => array(
			'title' => 'Multi-Status',
			'desc' => 'The message that follows is XML and may contain a number of separate response codes, one for each sub-request you made'
		),
		208 => array(
			'title' => 'Already Reported',
			'desc' => 'The members of a DAV binding have already been enumerated in a previous reply to this request and are not being included again'
		),
		226 => array(
			'title' => 'IM Used',
			'desc' => 'A GET request for the resource has been fulfilled and the response is a representation of the result of one or more instance-manipulations applied to the current instance'
		),
		300 => array(
			'title' => 'Multiple Choices',
			'desc' => 'The content is not here, you must choose where to proceed to get it'
		),
		301 => array(
			'title' => 'Moved Permenantly',
			'desc' => 'The content is not here and has been moved permenantly to a new, indicated location'
		),
		302 => array(
			'title' => 'Found',
			'desc' => 'The content is not here and may be found in the indicated location'
		),
		303 => array(
			'title' => 'See Other',
			'desc' => 'The response to your request can be retrieved by a GET request to the indicated location'
		),
		304 => array(
			'title' => 'Not Modified',
			'desc' => 'The content has not changed since you last requested it'
		),
		305 => array(
			'title' => 'Use Proxy',
			'desc' => 'Please proceed using the indicated proxy'
		),
		306 => array(
			'title' => 'Switch Proxy',
			'desc' => 'Subsequent requests should use the specified proxy'
		),
		307 => array(
			'title' => 'Temporary Redirect',
			'desc' => 'The content is not here but may temporarily be found at the indicated location'
		),
		308 => array(
			'title' => 'Permanent Redirect',
			'desc' => 'This and all future requests of this method should be directed to the indicated location'
		),
		400 => array(
			'title' => 'Bad Request',
			'desc' => 'Your request was poorly formed'
		),
		401 => array(
			'title' => 'Unauthorized',
			'desc' => 'You do not have the appropriate permissions to view the requested resource'
		),
		402 => array(
			'title' => 'Payment Required',
			'desc' => 'You must pay to proceed'
		),
		403 => array(
			'title' => 'Forbidden',
			'desc' => 'You may not proceed'
		),
		404 => array(
			'title' => 'Not Found',
			'desc' => 'The resource you requested cannot be found or may not exist'
		),
		405 => array(
			'title' => 'Method Not Allowed',
			'desc' => 'Please attempt again with a different HTTP method'
		),
		406 => array(
			'title' => 'Not Acceptable',
			'desc' => 'Your HTTP headers do not allow you to accept this kind of content'
		),
		407 => array(
			'title' => 'Proxy Authentication Required',
			'desc' => 'You must first authenticate with the proxy'
		),
		408 => array(
			'title' => 'Request Timeout',
			'desc' => 'The request took too long'
		),
		409 => array(
			'title' => 'Conflict',
			'desc' => 'The request could not be processed due to a conflict in the request'
		),
		410 => array(
			'title' => 'Gone',
			'desc' => 'The resource you requested existed at one point but no longer does and never will again'
		),
		411 => array(
			'title' => 'Length Required',
			'desc' => 'You must supply a length for your content'
		),
		412 => array(
			'title' => 'Precondition Failed',
			'desc' => 'The server does not meet a precondition specified'
		),
		413 => array(
			'title' => 'Request Entity Too Large',
			'desc' => 'The request is too large'
		),
		414 => array(
			'title' => 'Request-URI Too Long',
			'desc' => 'The URI you provided exceeds the acceptable length'
		),
		415 => array(
			'title' => 'Unsupported Media Type',
			'desc' => 'The request has a media type which the server or resource do not support'
		),
		416 => array(
			'title' => 'Requested Range Not Satisfiable',
			'desc' => 'The server cannot supply the specified portion of the file'
		),
		417 => array(
			'title' => 'Expectation Failed',
			'desc' => 'The server cannot meet the requirements specified by the Expect request header field'
		),
		418 => array(
			'title' => 'I\'m a teapot',
			'desc' => <<<'EOF'
I'm a little teapot short and stout
here is my handle
here is my spout

When I get all steamed up then I shout
"Tip me over and pour me out!"
EOF
		),
		420 => array(
			'title' => 'Enhance Your Calm',
			'desc' => 'You have submitted too many requests and are being rate limited'
		),
		423 => array(
			'title' => 'Locked',
			'desc' => 'The requested resource is locked'
		),
		424 => array(
			'title' => 'Failed Dependency',
			'desc' => 'A previous request failed, so this request fails'
		),
		425 => array(
			'title' => 'Unordered Collection'
		),
		426 => array(
			'title' => 'Upgrade Required',
			'desc' => 'Switch to a different protocol'
		),
		428 => array(
			'title' => 'Precondition Required',
			'desc' => 'The origin server requires this request to be conditional'
		),
		429 => array(
			'title' => 'Too Many Requests',
			'desc' => 'You have submitted too many requests and are being rate limited'
		),
		431 => array(
			'title' => 'Request Header Fields Too Large',
			'desc' => 'Due to the size of either a header fields, or your collection of header fields, this request will not be processed'
		),
		444 => array(
			'title' => 'No Response',
			'desc' => 'Server has returned no content and closed the connection'
		),
		449 => array(
			'title' => 'Retry With',
			'desc' => 'Retry after performing the appropriate action'
		),
		450 => array(
			'title' => 'Blocked by Windows Parental Controls',
			'desc' => 'Mommy or Daddy said no'
		),
		451 => array(
			'title' => 'Unavailable For Legal Reasons',
			'desc' => 'The law either in your area or our area does not permit this content to be displayed'
		),
		494 => array(
			'title' => 'Request Header Too Large',
			'desc' => 'Due to the size of either a header fields, or your collection of header fields, this request will not be processed'
		),
		495 => array(
			'title' => 'Cert Error',
			'desc' => 'SSL client certificate error'
		),
		496 => array(
			'title' => 'No Cert',
			'desc' => 'Client did not provide a certificate'
		),
		497 => array(
			'title' => 'HTTP to HTTPS',
			'desc' => 'An HTTP request was submitted to an HTTPS port'
		),
		499 => array(
			'title' => 'Client Closed Request',
			'desc' => 'Client closed connection prematurely'
		),
		500 => array(
			'title' => 'Internal Server Error',
			'desc' => 'An error was encountered while processing your request'
		),
		501 => array(
			'title' => 'Not Implemented',
			'desc' => 'Method not recognized, or server cannot fulfill request'
		),
		502 => array(
			'title' => 'Bad Gateway',
			'desc' => 'Invalid response received from upstream server'
		),
		503 => array(
			'title' => 'Service Unavailable',
			'desc' => 'Server unavailable'
		),
		504 => array(
			'title' => 'Gateway Timeout',
			'desc' => 'A response was not received from the upstream server in a timely manner'
		),
		505 => array(
			'title' => 'HTTP Version Not Supported',
			'desc' => 'The version of the HTTP protocol used for your request is not supported by this server'
		),
		506 => array(
			'title' => 'Variant Also Negotiates',
			'desc' => 'Transparent content negotiation for this request results in a circular reference'
		),
		507 => array(
			'title' => 'Insufficient Storage',
			'desc' => 'The server is unable to store the representation needed to complete this request'
		),
		508 => array(
			'title' => 'Loop Detected',
			'desc' => 'An infinite loop was detected processing this request'
		),
		509 => array(
			'title' => 'Bandwidth Limit Exceeded',
			'desc' => 'The client whose server you\'re submitting a request too has exceeded their bandwidth allocation'
		),
		510 => array(
			'title' => 'Not Extended',
			'desc' => 'In order to fulfill your request, further extentions to it will be required'
		),
		511 => array(
			'title' => 'Network Authentication Required',
			'desc' => 'You must authenticate to the network to proceed'
		),
		598 => array(
			'title' => 'Network read timeout error',
			'desc' => 'A read behind the proxy timed out'
		),
		599 => array(
			'title' => 'Network connect timeout error',
			'desc' => 'An attempt to connect behind the proxy timed out'
		)
	);
	
	
?>