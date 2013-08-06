<?php


	require_once(WHERE_PHP_INCLUDES.'mb.php');


	/**
	 *	\file
	 */
	 
	 
	/**
	 *	Tests a variable for emptiness, where
	 *	emptiness is defined as being \em null,
	 *	the empty string, or unset.
	 *
	 *	\param [in] $subject
	 *		The variable to test for emptiness.
	 *
	 *	\return
	 *		\em true if \em subject is \em null,
	 *		the empty string, or unset, \em false
	 *		otherwise.
	 */
	function is_empty ($subject) {
	
		return !(isset($subject) && !is_null($subject) && ($subject!==''));
	
	}
	
	
	/**
	 *	Ensures that a variable is a DateTime object.
	 *
	 *	\param [in] $date
	 *		The object to test.
	 *
	 *	\return
	 *		\em true of \em date is a DateTime,
	 *		\em false otherwise.
	 */
	function is_date ($date) {
	
		return !(
			is_null($date) ||
			(gettype($date)!=='object') ||
			(get_class($date)!=='DateTime') ||
			($date->format('Y m j')==='-0001 11 30')
		);
	
	}
	
	
	/**
	 *	Returns the value of \em int as an integer,
	 *	or \em null if \em int is not an integer.
	 *
	 *	\param [in] $int
	 *		The object to retrieve the integer
	 *		value of.
	 *
	 *	\return
	 *		An integer which is the integer value
	 *		of \em int, or \em null if \em int cannot
	 *		be represented as an integer.
	 */
	function to_int ($int) {
	
		if (is_integer($int)) return $int;
		
		if (
			is_numeric($int) &&
			(($intval=intval($int))==floatval($int))
		) return $intval;
		
		return null;
	
	}
	
	
	/**
	 *	Formats a time range in such a way
	 *	that information is not duplicated.
	 *
	 *	\param [in] $start
	 *		The start time of the range to
	 *		format.
	 *	\param [in] $end
	 *		The end time of the range to
	 *		format.
	 *
	 *	\return
	 *		A formatted string.
	 */
	function FormatTimeRange ($start, $end) {
	
		$time_format_no_ampm='g:i';
		$time_format='g:i A';
		
		//	If both are missing/improper, fail
		//	by returning the empty string
		if (!is_date($start) && !is_date($end)) return '';
		
		//	One is missing/improper, treat it as
		//	a "range" between start and start or
		//	end and end
		if (!is_date($start)) $start=$end;
		else if (!is_date($end)) $end=$start;
		
		//	They're both in the afternoon/evening
		if ($start->format('A')===$end->format('A')) {
		
			//	They're both at the same time
			if ($start->format('g:i')===$end->format('g:i')) {
			
				return $start->format($time_format);
			
			}
			
			return sprintf(
				'%s - %s',
				$start->format($time_format_no_ampm),
				$end->format($time_format)
			);
		
		}
		
		return sprintf(
			'%s - %s',
			$start->format($time_format),
			$end->format($time_format)
		);
	
	}
	
	
	/**
	 *	Formats a datetime range in such a way
	 *	that information is not duplicated.
	 *
	 *	\param [in] $start
	 *		The start datetime of the range to
	 *		format.
	 *	\param [in] $end
	 *		The end datetime of the range to
	 *		format.
	 *
	 *	\return
	 *		A formatted string.
	 */
	function FormatDateTimeRange ($start, $end) {
	
		$full_format='F jS, Y g:i A';
		$no_year_format='F jS';
		$month_format='F';
		$day_format='jS';
		$year_format='Y';
		$time_format='g:i A';
		$date_format='F jS, Y';
		$time_format_no_ampm='g:i';
	
		//	If both are missing/improper, fail
		//	by returning the empty string
		if (!is_date($start) && !is_date($end)) return '';
		
		//	One is missing/improper, treat it as
		//	a "range" between start and start or
		//	end and end
		if (!is_date($start)) $start=$end;
		else if (!is_date($end)) $end=$start;
		
		//	They're in the same year
		if ($start->format('Y')===$end->format('Y')) {
		
			//	They're in the same month
			if ($start->format('n')===$end->format('n')) {
			
				//	They're on the same day
				if ($start->format('j')===$end->format('j')) {
				
					//	They're both in the afternoon/morning
					if ($start->format('A')===$end->format('A')) {
					
						//	They're at the same time
						if ($start->format('g:i')===$end->format('g:i')) {
						
							return $start->format($full_format);
						
						}
						
						return sprintf(
							'%s %s - %s',
							$start->format($date_format),
							$start->format($time_format_no_ampm),
							$end->format($time_format)
						);
					
					}
					
					return sprintf(
						'%s - %s',
						$start->format($full_format),
						$end->format($time_format)
					);
					
				}
				
				return sprintf(
					'%s %s %s - %s %s %s',
					$start->format($month_format),
					$start->format($day_format),
					$start->format($time_format),
					$end->format($day_format),
					$end->format($time_format),
					$end->format($year_format)
				);
				
			}
			
			return sprintf(
				'%s %s - %s %s %s',
				$start->format($no_year_format),
				$start->format($time_format),
				$end->format($no_year_format),
				$end->format($time_format),
				$end->format($year_format)
			);
			
		}
		
		return sprintf(
			'%s - %s',
			$start->format($full_format),
			$end->format($full_format)
		);
	
	}
	

	/**
	 *	Formats a date range in such a way that
	 *	information isn't duplicated.
	 *
	 *	\param [in] $start
	 *		The start date of the range to
	 *		format.
	 *	\param [in] $end
	 *		The end date of the range to
	 *		format.
	 *
	 *	\return
	 *		A formatted string.
	 */
	function FormatDateRange ($start, $end) {
	
		$full_format='F jS, Y';
		$no_year_format='F jS';
		$month_format='F';
		$day_format='jS';
		$year_format='Y';
		
		//	They're both null/missing/improper
		if (!is_date($start) && !is_date($end)) return '';
		
		//	The first is missing/null/improper
		if (!is_date($start)) $start=$end;
		else if (!is_date($end)) $end=$start;
		
		//	They're both the same
		/*if ($start->format(DateTime::ISO8601)===$end->format(DateTime::ISO8601)) {
		
			//	Just output one of them
			return $start->format($full_format);
		
		}*/
		
		//	They're in the same year
		if ($start->format('Y')===$end->format('Y')) {
		
			//	They're in the same month
			if ($start->format('n')===$end->format('n')) {
			
				//	They're on the same day
				if ($start->format('j')===$end->format('j')) {
				
					return $start->format($full_format);
				
				} else {
				
					return sprintf(
						'%1$s %2$s - %3$s, %4$s',
						$start->format($month_format),
						$start->format($day_format),
						$end->format($day_format),
						$start->format($year_format)
					);
				
				}
			
			} else {
			
				return sprintf(
					'%1$s - %2$s, %3$s',
					$start->format($no_year_format),
					$end->format($no_year_format),
					$start->format($year_format)
				);
			
			}
		
		} else {
		
			return sprintf(
				'%1$s - %1$s',
				$start->format($full_format),
				$end->format($full_format)
			);
		
		}
	
	}
	
	
	/**
	 *	Formats a name in a presentable manner.
	 *	I.e. converts names to lowercase with
	 *	a leading capital, but attempts to
	 *	preserves constructs that should
	 *	result in capital letters in the middle
	 *	of a word, e.g.\ \"O'Leary\" or
	 *	\"McDonald\".
	 *
	 *	\param [in] $name
	 *		An unformatted name.
	 *
	 *	\return
	 *		\em name fit for presentation to
	 *		a user.
	 */
	function FormatName ($name) {
	
		//	Start by lower casing the name
		$name=MBString::ToLower(MBString::Trim($name));
		
		//	Filter the name through
		//	the substitutions
		foreach (array(
			'(?<=^|\\W)(\\w)' => function ($matches) {	return MBString::ToUpper($matches[0]);	},
			'(?<=^|\\W)O\'\\w' => function ($matches) {	return MBString::ToUpper($matches[0]);	},
			'(?<=^|\\W)(Ma?c)(\\w)' => function ($matches) {	return $matches[1].MBString::ToUpper($matches[2]);	}
		) as $pattern=>$substitution) {
		
			$pattern='/'.$pattern.'/u';
			
			$name=(
				($substitution instanceof Closure)
					?	preg_replace_callback($pattern,$substitution,$name)
					:	preg_replace($pattern,$substitution,$name)
			);
		
		}
		
		//	Return formatted name
		return $name;
	
	}


?>