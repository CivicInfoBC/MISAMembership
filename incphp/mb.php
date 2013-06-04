<?php


	mb_internal_encoding('UTF-8');
	mb_regex_encoding('UTF-8');


	/**
	 *	Contains static members for manipulating
	 *	multi-byte strings.
	 */
	class MBString {
	
	
		/**
		 *	Trims a multi-byte string, or an array
		 *	of multi-byte strings.
		 *
		 *	If trimming an array, the array is trimmed
		 *	in place.
		 *
		 *	If passed anything other than a string or
		 *	an array, conversion to string is attempted.
		 *
		 *	\param [in,out] $string
		 *		The item to trim.
		 *
		 *	\return
		 *		If passed an array returns nothing (as
		 *		the array is trimmed in place),
		 *		otherwise returns the trimmed result.
		 */
		public static function Trim ($string) {
		
			if (is_array($string)) {
			
				foreach ($string as &$x) {
				
					if (is_array($x)) self::Trim($x);
					else $x=self::Trim($x);
				
				}
				
			} else {
			
				return mb_ereg_replace('^\s+|\s+$','',(string)$string);
			
			}
		
		}
		
		
		/**
		 *	Places a multi-byte string in Normalization
		 *	Form Canonical Composition.
		 *
		 *	If passed an array, normalizes each element
		 *	of that array in place.
		 *
		 *	If passed anything other than a string or
		 *	an array, conversion to string is attempted.
		 *
		 *	\param [in,out] $string
		 *		The item to normalize.
		 *
		 *	\return
		 *		If passed an array returns nothing (as
		 *		the array is normalized in place),
		 *		otherwise returns the normalized result.
		 */
		public static function Normalize ($string) {
		
			if (is_array($string)) {
			
				foreach ($string as &$x) {
				
					if (is_array($x)) self::Normalize($x);
					else $x=self::Normalize($x);
				
				}
			
			} else {
			
				return Normalizer::normalize((string)$string,Normalizer::FORM_C);
			
			}
		
		}
		
		
		/**
		 *	Compares two multi-byte strings for canonical
		 *	equivalence.
		 *
		 *	If passed an arrays, compares the items within
		 *	that array as though they were strings.
		 *
		 *	If passed anything other than a string or
		 *	an array, \em false is returned.
		 *
		 *	\param [in] $string1
		 *		An item to compare.
		 *	\param [in] $string2
		 *		An item to compare.
		 *
		 *	\return
		 *		\em true if \em string1 and \em string2
		 *		are canonically equivalent strings (or
		 *		arrays containing canonically equivalent
		 *		strings), \em false otherwise.
		 */
		public static function Compare ($string1, $string2) {
		
			if (is_array($string1)) {
			
				if (
					is_array($string2) &&
					(count($string1)===count($string2))
				) {
				
					for ($i=0;$i<count($string1);++$i) {
					
						if (!self::Compare($string1[$i],$string2[$i])) return false;
					
					}
					
					return true;
				
				}
				
				return false;
			
			}
			
			if (is_null($string1) && is_null($string2)) return true;
			
			if (is_null($string1) || is_null($string2)) return false;
			
			if (!(is_string($string1) && is_string($string2))) return false;
			
			return self::Normalize($string1)===self::Normalize($string2);
		
		}
		
		
		/**
		 *	Escapes \em escape so that it may be inserted
		 *	into a regular expression without any character
		 *	or sequence therein being interpreted specially
		 *	by the regular expression engine.
		 *
		 *	If \em escape is not a string, conversion to
		 *	string is attempted.
		 *
		 *	\param [in] $escape
		 *		The string to escape.
		 *
		 *	\return
		 *		\em escape escaped such that no sequence
		 *		or character therein will be interpreted
		 *		by the regular expression engine as anything
		 *		other than a literal.
		 */
		public static function RegexEscape ($escape) {
		
			return mb_ereg_replace(
				'(\\[|\\]|\\(|\\)|\\{|\\}|\\$|\\^|\\?|\\*|\\.|\\+|\\||\\\\)',
				'\\\\1',
				$escape
			);
		
		}
		
		
		/**
		 *	Converts a string to upper case based upon
		 *	Unicode case mappings.
		 *
		 *	If passed an array, performs this conversion
		 *	on each element in that array in place.
		 *
		 *	If passed anything other than a string or
		 *	an array, attempts to convert that item
		 *	to a string and then perform the conversion.
		 *
		 *	\param [in,out] $string
		 *		The item to convert.
		 *
		 *	\return
		 *		If passed an array returns nothing (as
		 *		the conversions are performed in place),
		 *		otherwise returns the Unicode upper case
		 *		conversion of \em string
		 */
		public static function ToUpper ($string) {
		
			if (is_array($string)) {
			
				foreach ($string as &$x) {
				
					if (is_array($x)) self::ToUpper($x);
					else $x=self::ToUpper($x);
				
				}
			
			} else {
			
				return mb_strtoupper((string)$string);
			
			}
		
		}
		
		
		/**
		 *	Converts a string to lower case based upon
		 *	Unicode case mappings.
		 *
		 *	If passed an array, performs this conversion
		 *	on each element in that array in place.
		 *
		 *	If passed anything other than a string
		 *	or an array, attempts to convert that item
		 *	to a string and then perform the conversion.
		 *
		 *	\param [in,out] $string
		 *		The item to convert.
		 *
		 *	\return
		 *		If passed an array returns nothing (as
		 *		the conversions are performed in place),
		 *		otherwise returns the Unicode lower case
		 *		conversion of \em string.
		 */
		public static function ToLower ($string) {
		
			if (is_array($string)) {
			
				foreach ($string as &$x) {
				
					if (is_array($x)) self::ToLower($x);
					else $x=self::ToLower($x);
				
				}
			
			} else {
			
				return mb_strtolower((string)$string);
			
			}
		
		}
		
		
		/**
		 *	Returns the number of Unicode code points
		 *	in the string.
		 *
		 *	If passed anything other than a string,
		 *	attempts to convert that item to a string
		 *	and then determine the number of Unicode
		 *	code points therein.
		 *
		 *	\param [in] $string
		 *		A string.
		 *
		 *	\return
		 *		The number of Unicode code points in
		 *		the string.
		 */
		public static function Length ($string) {
		
			return mb_strlen($string);
		
		}	
	
	
	}


?>