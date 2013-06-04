<?php


	/**
	 *	Defines a constant, but only if
	 *	it is not already defined.
	 *
	 *	\param [in] $key
	 *		The name of the constant to
	 *		define.
	 *	\param [in] $value
	 *		The value to define as
	 *		\em key.
	 */
	function def ($key, $value) {
	
		if (!defined($key)) define($key,$value);
	
	}


?>