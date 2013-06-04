<?php


	/**
	 *	Generates \em num_bytes bytes of
	 *	cryptographically strong randomness.
	 *
	 *	\param [in] $num_bytes
	 *		The number of bytes of randomness
	 *		desired.
	 */
	function CryptoRandom ($num_bytes) {
	
		//	Attempt to open /dev/urandom
		//	(Linux)
		$fp=@fopen('/dev/urandom','rb');
		if ($fp!==false) {
		
			$pr=@fread($fp,$num_bytes);
			
			@fclose($fp);
		
		//	Couldn't open /dev/urandom,
		//	must be Windows
		} else if (@class_exists('COM')) {
		
			$capi_util=new COM('CAPICOM.Utilities.1');
			$pr=$capi_util->GetRandom($num_bytes,0);
			$pr=base64_decode($pr);
		
		//	None of the above, fail
		} else {
		
			throw new Exception('No cryptographically secure random number generator available');
		
		}
		
		return $pr;
	
	}


?>