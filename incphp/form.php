<?php


	require_once(WHERE_PHP_INCLUDES.'mb.php');


	/**
	 *	The interface which all elements
	 *	to be rendered on a form must
	 *	implement.
	 */
	interface FormElement {
	
	
		/**
		 *	Renders the element.
		 *
		 *	\return
		 *		A string which represents
		 *		the HTML representation
		 *		of the element.
		 */
		public function Render ();
	
	
	}
	
	
	/**
	 *	A form element which contains or
	 *	collects some form of data.
	 */
	abstract class DataFormElement implements FormElement {
	
	
		/**
		 *	Stores the key in the GET
		 *	or POST array which this
		 *	element shall use for its
		 *	data needs.
		 */
		public $key;
		/**
		 *	Contains the element's
		 *	currently-associated
		 *	data.
		 */
		public $value;
		
		
		/**
		 *	Initializes this class'
		 *	members.
		 *
		 *	\param [in] $key
		 *		The key in the GET or POST
		 *		array this object shall use
		 *		for its data needs.
		 *	\param [in] $value
		 *		The value of this object,
		 *		if any.
		 */
		protected function __construct ($key, $value=null) {
		
			$this->key=$key;
			$this->value=$value;
		
		}
		
		
		/**
		 *	When implemented in a derived class
		 *	verifies that the data in this
		 *	element is safe for consumption.
		 *
		 *	\return
		 *		\em true if this element's value
		 *		is permissible, \em false otherwise.
		 */
		abstract public function Verify ();
		
		
		/**
		 *	When implemented in a deriver class
		 *	returns the JavaScript necessary to
		 *	run verification of this element on
		 *	the client side.
		 *
		 *	\return
		 *		A string containing JavaScript, or
		 *		\em null if no JavaScript verification
		 *		is appropriate.
		 */
		abstract public function RenderVerify ();
		
		
		/**
		 *	Populates this element with data from
		 *	the GET or POST array.
		 *
		 *	\param [in] $is_post
		 *		\em true if the POST array should
		 *		be used, \em false if the GET array
		 *		should be used.
		 */
		public function Populate ($is_post) {
		
			//	Verify that the array-in-question
			//	exists and obtain a reference
			//	to it
			if ($is_post) {
			
				if (!isset($_POST)) throw new Exception('POST array not set');
			
				$arr=&$_POST;
			
			} else {
			
				if (!isset($_GET)) throw new Exception('GET array not set');
				
				$arr=&$_GET;
			
			}
		
			//	Populate our data
			$this->value=(
				isset($arr[$this->key])
					//	Try and grab the value
					//	that corresponds to us
					?	$arr[$this->key]
					//	Otherwise just assume
					//	the empty string, since
					//	null doesn't make sense
					//	in the context of
					//	HTML forms
					:	''
			);
		
		}
	
	
	}


	/**
	 *	Encapsulates an HTML form by
	 *	containing form elements.
	 */
	class Form {
	
	
		private $elements;
		private $action;
		private $is_post;
		
		
		/**
		 *	Creates a new form with no
		 *	elements associated with
		 *	it.
		 *
		 *	\param [in] $action
		 *		The action that the form
		 *		shall take when submitted.
		 *		I.e.\ the web page that the
		 *		user's browser shall submit
		 *		to.
		 *	\param [in] $method
		 *		The HTTP method that the server
		 *		shall use for delivering the
		 *		data.  If not \"POST\" or
		 *		\"GET\" defaults to \"POST\".
		 *	\param [in] $elements
		 *		The elements that shall be associated
		 *		with this form.
		 */
		public function __construct ($action, $method, $elements) {
		
			$this->elements=$elements;
			$this->action=$action;
			
			$this->is_post=!MBString::Compare(
				'GET',
				MBString::ToUpper($method)
			);
		
		}
		
		
		/**
		 *	Populates the form with data from
		 *	a GET or POST request (depending
		 *	on what was specified in the
		 *	constructor).
		 */
		public function Populate () {
		
			foreach ($this->elements as $element) {
			
				if ($element instanceof DataFormElement) {
				
					$element->Populate($this->is_post);
				
				}
			
			}
		
		}
		
		
		/**
		 *	Verifies the data in all the form's
		 *	elements for which such an action
		 *	is appropriate.
		 *
		 *	\return
		 *		\em true if all data is acceptable,
		 *		\em false otherwise.
		 */
		public function Verify () {
		
			foreach ($this->elements as $element) {
		
				if (
					($element instanceof DataFormElement) &&
					!$element->Verify()
				) return false;
			
			}
			
			return true;
		
		}
		
		
		/**
		 *	Renders the form.
		 *
		 *	\return
		 *		A string containing the HTML
		 *		representation of the form.
		 */
		public function Render () {
		
			//	Form start
			$form=	'<form id="form" action="'.
					htmlspecialchars($this->action).
					'" method="'.
					htmlspecialchars(
						$this->is_post
							?	'POST'
							:	'GET'
					).
					'">';
		
		
			//	Render each element in turn
			foreach ($this->elements as $element) {
			
				$form.=$element->Render();
			
			}
			
			//	Form end
			return $form.'</form>';
		
		}
		
		
		/**
		 *	Renders the verification code
		 *	for the form.
		 *
		 *	\return
		 *		A string containing the
		 *		JavaScript required to verify
		 *		the form on the client side.
		 */
		public function RenderVerify () {
		
			$verify='';
			
			foreach ($this->elements as $element) {
			
				if ($element instanceof DataFormElement) {
				
					$verify.=$element->RenderVerify();
				
				}
			
			}
			
			return $verify;
		
		}
		
		
		/**
		 *	Retrieves an associative array representing
		 *	the form's current data.
		 *
		 *	\return
		 *		An associative array mapping keys to
		 *		values for each element in the form
		 *		that has or can have associated
		 *		data.
		 */
		public function GetValues () {
		
			$arr=array();
		
			foreach ($this->elements as $element) {
			
				if ($element instanceof DataFormElement) {
				
					$arr[$element->key]=$element->value;
				
				}
			
			}
			
			return $arr;
		
		}
	
	
	}
	
	
	/**
	 *	A base for elements that use regular
	 *	expressions to validate their
	 *	data.
	 */
	abstract class RegexFormElement extends DataFormElement {
	
		
		/**
		 *	The template that RegexFormElement
		 *	objects shall use to render their
		 *	verification code.
		 *
		 *	Uses late static binding so that this
		 *	may be replaced on a per class basis
		 *	while leveraging the methods supplied
		 *	by this class.
		 *
		 *	Shall be in the format expected for
		 *	sprintf, with the following substitutions
		 *	to be made:
		 *
		 *	1.	The regular expression which shall
		 *		be used to validate this element.
		 *		The requisite leading and trailing
		 *		solidus shall be added, and the
		 *		regular expression shall not be
		 *		quoted.
		 *	2.	The key associated with this text
		 *		form element.  This shall be quoted
		 *		and shall be in a format acceptable
		 *		for JavaScript.
		 */
		public static $verify_template='if (!document.getElementById(\'form\').elements[%2$s].value.match(%1$s)){ErrorElement(document.getElementById(\'form\').elements[%2$s]);verified=false;}else{UnerrorElement(document.getElementById(\'form\').elements[%2$s]);}';
		
		
		/**
		 *	The regular expression that shall be used
		 *	to verify this element's data on both the
		 *	server and client side.
		 *
		 *	Accordingly, this regex must be in an
		 *	acceptable format for PCRE (on the server
		 *	side) and JavaScript regex (on the client
		 *	side).
		 *
		 *	Do not add a trailing or leading solidus, these
		 *	will be added for you, however be sure
		 *	to accordingly escape the solidus literal
		 *	within the string or the results will be
		 *	unexpected.
		 */
		public $regex;
		
		
		/**
		 *	Creates a new RegexFormElement.
		 *
		 *	\param [in] $key
		 *		The key in the GET or POST array
		 *		this object shall use for its
		 *		data needs.
		 *	\param [in] $regex
		 *		The regular expression that this
		 *		object shall use to verify itself
		 *		(see notes on the member variable).
		 *	\param [in] $value
		 *		An initial value, if any.
		 */
		protected function __construct ($key, $regex, $value=null) {
		
			parent::__construct($key,$value);
			
			$this->regex=$regex;
		
		}
		
		
		public function Verify () {
		
			//	Treat null as the empty string,
			//	HTML forms are incapable of
			//	differentiating between them
			if (is_null($this->value)) $this->value='';
			
			//	Apply the regex
			return preg_match(
				'/'.$this->regex.'/u',
				$this->value
			)!==0;
		
		}
		
		
		public function RenderVerify () {
		
			return sprintf(
				//	Choose the template to use
				(
					(
						!isset(static::$verify_template) ||
						(static::$verify_template==='')
					)
						?	self::$verify_template
						:	static::$verify_template
				),
				(is_null($this->regex) || ($this->regex==='')) ? '/(?:)/' : '/'.$this->regex.'/',
				json_encode($this->key)
			);
		
		}
		
	
	}
	
	
	/**
	 *	A form element which corresponds to
	 *	a text input field with a label.
	 */
	class TextFormElement extends RegexFormElement {
	
	
		/**
		 *	The template that TextFormElement
		 *	objects shall use to render themselves.
		 *
		 *	Shall be in the format expected for
		 *	sprintf, with the following substitutions
		 *	to be made:
		 *
		 *	1.	The label associated with this text
		 *		form element.
		 *	2.	The key associated with this text
		 *		form element.
		 *	3.	The data associated with this text
		 *		form element (empty string if there
		 *		is no such data).
		 *	4.	The type of this input tag.
		 *	5.	The CSS classes to associate with
		 *		the input tag.
		 */
		public static $template='<div><div>%s:</div><div><input name="%s" value="%s" type="%s" class="%s" /></div></div>';
		public static $verify_template='';
		
		
		/**
		 *	The text label that shall be associated
		 *	with this text input form element.
		 */
		public $label;
		/**
		 *	The type of tag that shall be output.
		 */
		public $type;
		/**
		 *	The CSS classes the input element shall
		 *	have.
		 */
		public $classes;
		
		
		/**
		 *	Creates a new text form element.
		 *
		 *	\param [in] $key
		 *		The key in the GET or POST array
		 *		this object shall use for its
		 *		data needs.
		 *	\param [in] $label
		 *		The text label that shall be associated
		 *		with this element and shall be
		 *		substituted into the HTML template
		 *		while rendering it.
		 *	\param [in] $regex
		 *		The regular expression that this
		 *		object shall use to verify itself
		 *		(see notes on the member variable).
		 *	\param [in] $value
		 *		An initial value, if any.
		 *	\param [in] $type
		 *		The type of input tag to output.
		 *		Useful if you wish to output password
		 *		inputs.  Defaults to \"text\".
		 *	\param [in] $classes
		 *		The CSS classes to attach to the generated
		 *		input element.
		 */
		public function __construct ($key, $label, $regex, $value=null, $type='text', $classes=null) {
		
			parent::__construct($key,$regex,$value);
			
			$this->label=$label;
			$this->type=$type;
			$this->classes=$classes;
		
		}
		
		
		public function Render () {
		
			return sprintf(
				self::$template,
				htmlspecialchars($this->label),
				htmlspecialchars($this->key),
				htmlspecialchars(
					is_null($this->value)
						?	''
						:	$this->value
				),
				htmlspecialchars($this->type),
				htmlspecialchars($this->classes)
			);
		
		}
		
	
	}
	
	
	/**
	 *	A form elements which simply displays
	 *	a submit button.
	 */
	class SubmitFormElement implements FormElement {
	
	
		/**
		 *	The template which shall be used to render
		 *	the HTML for submit buttons.
		 *
		 *	Shall be in the format expected for sprintf,
		 *	with the following substitutions to be
		 *	made:
		 *
		 *	1.	The label associated with the submit
		 *		button.
		 */
		public static $template='<input type="submit" name="submit" value="%s" />';
	
		
		/**
		 *	The label which shall display
		 *	on the submit button.
		 */
		public $label;
	
	
		/**
		 *	Creates a new submit button form
		 *	element.
		 *
		 *	\param [in] $label
		 *		The label which shall display
		 *		on the submit button.
		 */
		public function __construct ($label) {
		
			$this->label=$label;
		
		}
		
		
		/**
		 *	Renders the submit button.
		 *
		 *	\return
		 *		A string containing the HTML
		 *		representing the submit
		 *		button.
		 */
		public function Render () {
		
			return sprintf(
				self::$template,
				htmlspecialchars($this->label)
			);
		
		}
	
	
	}
	
	
	/**
	 *	A form element which simply displays text
	 *	with a label.
	 */
	class TextElement implements FormElement {
	
	
		/**
		 *	The template which shall be used to render the
		 *	HTML for text elements.
		 *	
		 *	Shall be in the format expected for sprintf,
		 *	with the following substitutions to be made:
		 *
		 *	1.	The label associated with the text element.
		 *	2.	The text associated with the text element.
		 */
		public static $template='<div><div>%s:</div><div>%s</div></div>';
		
		
		/**
		 *	The label which shall be associated with
		 *	this text element.
		 */
		public $label;
		/**
		 *	The text which shall be associated with
		 *	this text element.
		 */
		public $value;
		
		
		/**
		 *	Creates a new text element.
		 *
		 *	\param [in] $label
		 *		The label to associate with this
		 *		text element.
		 *	\param [in] $value
		 *		The text to associate with this text
		 *		element.
		 */
		public function __construct ($label, $value) {
		
			$this->label=$label;
			$this->value=$value;
		
		}
		
		
		/**
		 *	Renders the text element.
		 *
		 *	\return
		 *		A string containing the HTML representation
		 *		of the text element.
		 */
		public function Render () {
		
			return sprintf(
				self::$template,
				htmlspecialchars($this->label),
				htmlspecialchars($this->value)
			);
		
		}
	
	
	}
	
	
	/**
	 *	A form element which allows the user to
	 *	select a province.
	 */
	class ProvinceFormElement extends RegexFormElement {
	
	
		/**
		 *	The template that ProvinceFormElement objects
		 *	shall use to render themselves.
		 *
		 *	Shall be in the format expected for sprintf,
		 *	with the following substitutions to be made:
		 *
		 *	1.	The label associated with the element.
		 *	2.	The key associated with the element.
		 *	3.	A list of <option> tags which give the
		 *		drop-down options for the element.
		 *	4.	The current value of the element, unless
		 *		it is one of the <option> tags.
		 *	5.	The style of the auxiliary text input.
		 *		This will be a style attribute which
		 *		contains "display: none;" if one of the
		 *		drop-down options are selected, otherwise
		 *		it shall be the empty string.
		 */
		public static $template='<div><div>%1$s:</div><div><select name="%2$s">%3$s</select><input type="text" name="%2$s_other" value="%4$s" %5$s /></div></div>';
		/**
		 *	The template that ProvinceFormElement objects
		 *	shall use to render their drop-downs.
		 *
		 *	Shall be in the format expected for sprintf,
		 *	with the following substitutions to be made:
		 *
		 *	1.	The value.
		 *	2.	The label.
		 *	3.	"selected" if this is the currently-selected
		 *		option, otherwise the empty string.
		 */
		public static $drop_down_template='<option value="%1$s" %3$s>%2$s</option>';
		/**
		 *	An associative array which maps province
		 *	drop-down values to labels.
		 */
		public static $options=array(
			'AB' => 'Canada - Alberta',
			'BC' => 'Canada - British Columbia',
			'MB' => 'Canada - Manitoba',
			'NB' => 'Canada - New Brunswick',
			'NL' => 'Canada - Newfoundland and Labrador',
			'NT' => 'Canada - Northwest Territories',
			'NS' => 'Canada - Nova Scotia',
			'NU' => 'Canada - Nunavut',
			'ON' => 'Canada - Ontario',
			'PE' => 'Canada - Prince Edward Island',
			'QC' => 'Canada - Quebec',
			'SK' => 'Canada - Saskatchewan',
			'YT' => 'Canada - Yukon',
			'AK' => 'United States of America - Alaska',
			'AL' => 'United States of America - Alabama',
			'AS' => 'United States of America - American Samoa',
			'AR' => 'United States of America - Arkansas',
			'AZ' => 'United States of America - Arizona',
			'CA' => 'United States of America - California',
			'CO' => 'United States of America - Colorado',
			'CT' => 'United States of America - Connecticut',
			'DE' => 'United States of America - Delaware',
			'DC' => 'United States of America - District of Columbia',
			'FL' => 'United States of America - Florida',
			'GA' => 'United States of America - Georgia',
			'GU' => 'United States of America - Guam',
			'HI' => 'United States of America - Hawai\'i',
			'ID' => 'United States of America - Idaho',
			'IL' => 'United States of America - Illinois',
			'IN' => 'United States of America - Indiana',
			'IA' => 'United States of America - Iowa',
			'KS' => 'United States of America - Kansas',
			'KY' => 'United States of America - Kentucky',
			'LA' => 'United States of America - Louisiana',
			'ME' => 'United States of America - Maine',
			'MD' => 'United States of America - Maryland',
			'MA' => 'United States of America - Massachusetts',
			'MI' => 'United States of America - Michigan',
			'MN' => 'United States of America - Minnesota',
			'MS' => 'United States of America - Mississippi',
			'MO' => 'United States of America - Missouri',
			'MT' => 'United States of America - Montana',
			'NC' => 'United States of America - North Carolina',
			'ND' => 'United States of America - North Dakota',
			'MP' => 'United States of America - Northern Mariana Islands',
			'NE' => 'United States of America - Nebraska',
			'NV' => 'United States of America - Nevada',
			'NH' => 'United States of America - New Hampshire',
			'NJ' => 'United States of America - New Jersey',
			'NM' => 'United States of America - New Mexico',
			'NY' => 'United States of America - New York',
			'OH' => 'United States of America - Ohio',
			'OK' => 'United States of America - Oklahoma',
			'OR' => 'United States of America - Oregon',
			'CZ' => 'United States of America - Panama Canal Zone',
			'PA' => 'United States of America - Pennsylvania',
			'PU' => 'United States of America - Puerto Rico',
			'RI' => 'United States of America - Rhode Island',
			'SC' => 'United States of America - South Carolina',
			'SD' => 'United States of America - South Dakota',
			'TN' => 'United States of America - Tennessee',
			'TX' => 'United States of America - Texas',
			'UT' => 'United States of America - Utah',
			'VT' => 'United States of America - Vermont',
			'VI' => 'United States of America - U.S. Virgin Islands',
			'VA' => 'United States of America - Virginia',
			'WA' => 'United States of America - Washington',
			'WV' => 'United States of America - West Virginia',
			'WI' => 'United States of America - Wisconsin',
			'WY' => 'United States of America - Wyoming',
			'FM' => 'Federated States of Micronesia',
			'MH' => 'Marshall Islands',
			'PW' => 'Palau',
			'' => 'Other'
		);
		/**
		 *	The key of the drop-down option that shall
		 *	be selected when the object has no value.
		 */
		public static $default='BC';
		public static $verify_template='if ((document.getElementById(\'form\').elements[%2$s].options[document.getElementById(\'form\').elements[%2$s].selectedIndex].value===\'\') &&!document.getElementById(\'form\').elements[%3$s].value.match(%1$s)){ErrorElement(document.getElementById(\'form\').elements[%2$s]);verified=false;}else{UnerrorElement(document.getElementById(\'form\').elements[%2$s]);}';
		
		
		/**
		 *	The label that shall be associated with
		 *	this element.
		 */
		public $label;
		
		
		/**
		 *	Creates a new ProvinceFormElement.
		 *
		 *	\param [in] $key
		 *		The key in the GET or POST array
		 *		that this element shall use for its
		 *		data needs.  Note that this element
		 *		will additionally use $key.'_other'
		 *		to check for data the user inputs
		 *		outside the range of the options
		 *		presented in the drop-down.
		 *	\param [in] $label
		 *		The text label that shall be associated
		 *		with this form element.
		 *	\param [in] $regex
		 *		The regular expression which shall
		 *		be used to validate the values the
		 *		user gives in the "other" field.
		 *	\param [in] $value
		 *		The value the element shall start off
		 *		with.  Optional.  Defaults to \em null.
		 */
		public function __construct ($key, $label, $regex='', $value=null) {
		
			parent::__construct($key,$regex,$value);
			
			$this->label=$label;
		
		}
		
		
		public function Render () {
		
			//	Normalize values
			
			//	If there's no selected option
			//	we select the default option
			if (!(isset($this->value) && ($this->value!==''))) {
			
				$this->value=self::$default;
			
			}
			
			$is_dropdown=false;
			
			//	Scan the drop-down elements to see
			//	if not-"Other" is selected
			foreach (self::$options as $value=>$label) {
			
				if (
					MBString::Compare($value,$this->value) ||
					MBString::Compare($label,$this->value)
				) {
				
					$is_dropdown=true;
				
				}
			
			}
			
			//	A string containing the HTML
			//	for the options tags that go
			//	in the select tag
			$options='';
			
			//	Loop and create HTML for options
			foreach (self::$options as $value=>$label) {
			
				//	Is this the currently-selected
				//	option?
				
				$is_this=(
					//	If this is "Other" and the selected
					//	option is not in the drop-down,
					//	this is the selected option
					($value==='')
						?	!$is_dropdown
							//	Otherwise only if there's
							//	a match is this selected
						:	(
								MBString::Compare($value,$this->value) ||
								MBString::Compare($label,$this->value)
							)
				);
				
				//	Create HTML
				$options.=sprintf(
					self::$drop_down_template,
					htmlspecialchars($value),
					htmlspecialchars($label),
					$is_this ? 'selected' : ''
				);
			
			}
			
			//	Create final HTML
			return sprintf(
				self::$template,
				htmlspecialchars($this->label),
				htmlspecialchars($this->key),
				$options,
				$is_dropdown ? '' : htmlspecialchars(
					$this->value
				),
				$is_dropdown ? 'style="display:none;"' : ''
			);
		
		}
		
		
		public function Populate ($is_post) {
		
			if ($is_post) {
			
				if (!isset($_POST)) throw new Exception('POST array not set');
				
				$arr=&$_POST;
			
			} else {
			
				if (!isset($_GET)) throw new Exception('GET array not set');
				
				$arr=&$_GET;
			
			}
			
			$this->value=(
				//	Prefer the drop-down
				(isset($arr[$this->key]) && ($arr[$this->key]!==''))
					?	$arr[$this->key]
					:	(
							//	Fallback to the text element
							isset($arr[$this->key.'_other'])
								?	$arr[$this->key.'_other']
								:	''
						)
			);
		
		}
		
		
		public function Verify () {
		
			//	If the value is in the drop-down
			//	we bypass regular expression
			//	validation
			if (isset($this->value) && ($this->value!=='') && in_array(
				$this->value,
				array_keys(self::$options),
				true
			)) return true;
			
			return parent::Verify();
		
		}
		
		
		public function RenderVerify () {
		
			return sprintf(
				self::$verify_template,
				(is_null($this->regex) || ($this->regex==='')) ? '/(?:)/' : '/'.$this->regex.'/',
				json_encode($this->key),
				json_encode($this->key.'_other')
			);
		
		}
		
		
		/**
		 *	Splits a value, obtaining the corresponding
		 *	country and province (if either is applicable).
		 *
		 *	\param [in] $value
		 *		The value generated by a ProvinceFormElement.
		 *
		 *	\return
		 *		An object with a \em territorial_unit and
		 *		\em country key, populated with the territorial
		 *		unit and country represented by \em value.
		 *		Either or both may be \em null, depending on
		 *		the value of \em value.
		 */
		public static function Split ($value) {
		
			$obj=(object)array(
				'territorial_unit' => null,
				'country' => null
			);
			
			//	If the value corresponds to
			//	a drop-down option, retrieve
			//	the drop-down option instead
			if (in_array(
				$value,
				array_keys(self::$options),
				true
			)) $value=self::$options[$value];
			
			//	A proper expression of a country and
			//	territorial unit is thus:
			//
			//	COUNTRY - TERRITORIAL UNIT
			//
			//	Therefore we look for this structure,
			//	or any structure like it.
			//
			//	We don't look for hyphens without whitespace
			//	on either side due to the risk of confusion.
			if (preg_match(
				'/(.+)\\s\\-\\s(.+)/u',
				$value,
				$matches
			)===0) {
			
				//	Just a country
				$obj->country=MBString::Trim($value);
			
			} else {
			
				//	Country and territorial unit
				$obj->country=MBString::Trim($matches[1]);
				$obj->territorial_unit=MBString::Trim($matches[2]);
			
			}
			
			return $obj;
		
		}
	
	
	}
	
	
	/**
	 *	A form element which encapsulates two logical
	 *	form elements: A password input box to enter
	 *	a new password, and a second input box which
	 *	must contain exactly the same password.
	 */
	class ChangePasswordFormElement extends DataFormElement {
	

		/**
		 *	The template that ChangePasswordFormElement
		 *	objects shall use to render their HTML.
		 *
		 *	Shall be in the format expected for sprintf,
		 *	with the following substitutions to be made:
		 *
		 *	1.	The label.
		 *	2.	The key.
		 */
		public static $template='<div><div>%1$s:</div><div><input type="password" name="%2$s" /></div></div><div><div>Confirm %1$s:</div><div><input type="password" name="%2$s_confirm" /></div></div>';
		
		
		/**
		 *	The template that ChangePasswordFormElement
		 *	objects shall use to render their JavaScript
		 *	validation code.
		 *
		 *	Shall be in the format expected for sprintf,
		 *	with the following substitutions to be made:
		 *
		 *	1.	The key for this element in a format acceptable
		 *		for a JavaScript string.
		 *	2.	The key for the second (confirm) element in
		 *		a format acceptable for a JavaScript string.
		 */
		public static $verify_template='if(document.getElementById(\'form\').elements[%1$s].value!==document.getElementById(\'form\').elements[%2$s].value){ErrorElement(document.getElementById(\'form\').elements[%1$s]);ErrorElement(document.getElementById(\'form\').elements[%2$s]);verified=false;}else{UnerrorElement(document.getElementById(\'form\').elements[%1$s]);UnerrorElement(document.getElementById(\'form\').elements[%2$s]);}';
		
		
		/**
		 *	The template that ChangePasswordFormElement
		 *	objects additionally use if they are not
		 *	optional to render their JavaScript verification
		 *	code.
		 *
		 *	Shall be in the format expected for sprintf,
		 *	with the following substitutions to be made:
		 *
		 *	1.	The key for this element in a format acceptable
		 *		for a JavaScript string.
		 *	2.	The key for the second (confirm) element in
		 *		a format acceptable for a JavaScript string.
		 */
		public static $non_optional_verify_template='if(document.getElementById(\'form\').elements[%1$s].value===\'\'){ErrorElement(document.getElementById(\'form\').elements[%1$s]);ErrorElement(document.getElementById(\'form\').elements[%2$s]);verified=false;}else{UnerrorElement(document.getElementById(\'form\').elements[%1$s]);UnerrorElement(document.getElementById(\'form\').elements[%2$s]);}';
		

		private $confirm_value;
		
		
		/**
		 *	The label by which this form element shall be
		 *	identified.
		 */
		public $label;
		/**
		 *	Whether this is optional or not.
		 */
		public $optional;
	
	
		/**
		 *	Creates a new ChangePasswordFormElement.
		 *
		 *	\param [in] $key
		 *		The key that shall be associated
		 *		with this element.
		 *	\param [in] $label
		 *		The text label that shall be associated
		 *		with this element.
		 *	\param [in] $optional
		 *		\em true if this element is optional,
		 *		\em false otherwise, defaults to \em true.
		 */
		public function __construct ($key, $label, $optional=true) {
		
			parent::__construct($key);
			
			$this->confirm_value=null;
			$this->label=$label;
			$this->optional=$optional;
		
		}
		
		
		public function Populate ($is_post) {
		
			if ($is_post) {
			
				if (!isset($_POST)) throw new Exception('POST array not set');
			
				$arr=&$_POST;
			
			} else {
			
				if (!isset($_GET)) throw new Exception('GET array not set');
				
				$arr=&$_GET;
			
			}
			
			$this->confirm_value=(
				isset($arr[$this->key.'_confirm'])
					?	$arr[$this->key.'_confirm']
					:	null
			);
			
			//	Chain through
			parent::Populate($is_post);
		
		}
		
		
		public function Verify () {
		
			//	If both are null or empty,
			//	we're not trying to change
			//	the password, so that's fine,
			//	unless this isn't optional
			if (
				!$this->optional &&
				(is_null($this->value) || ($this->value==='')) &&
				(is_null($this->confirm_value) || ($this->confirm_value===''))
			) return true;
			
			//	Otherwise make sure they're
			//	the same
			return $this->value===$this->confirm_value;
		
		}
		
		
		public function RenderVerify () {
		
			$output=sprintf(
				self::$verify_template,
				json_encode($this->key),
				json_encode($this->key.'_confirm')
			);
			
			if (!$this->optional) $output.=sprintf(
				self::$non_optional_verify_template,
				json_encode($this->key),
				json_encode($this->key.'_confirm')
			);
			
			return $output;
		
		}
		
		
		public function Render () {
		
			return sprintf(
				self::$template,
				htmlspecialchars($this->label),
				htmlspecialchars($this->key)
			);
		
		}
	
	
	}
	
	
	/**
	 *	A drop-down which may be populated either
	 *	from an array, an associative, array,
	 *	or from the database.
	 */
	class DropDownFormElement extends DataFormElement {
	
	
		public static $options_template='<option value="%1$s" %3$s>%2$s</option>';
		/**
		 *	The template that shall be used to render
		 *	HTML for this form element.
		 *
		 *	Shall be in the format expected for sprintf,
		 *	with the following substitutions to be
		 *	made:
		 *
		 *	1.	The label associated with this element.
		 *	2.	The key associated with this element.
		 *	3.	The option tags that represent the options
		 *		this element presents.
		 */
		public static $template='<div><div>%s:</div><div><select name="%s">%s</select></div></div>';
	
		
		private $arr;
		private $label;
		
		
		/**
		 *	Creates a new drop-down and populates
		 *	it with data.
		 *
		 *	May be populated either from a database
		 *	query or from an array.
		 *
		 *	The behaviour is as follows:
		 *
		 *	<B>Database:</B>
		 *
		 *	The form element shall use \em conn_or_arr
		 *	as a MySQLi database connection and shall
		 *	execute \em query.
		 *
		 *	The first column of the resultant data set
		 *	shall be used as the keys for the drop-down
		 *	items, the second column shall be used
		 *	as the labels.
		 *
		 *	If the data set has only one column, it shall
		 *	be used as both the keys and labels.
		 *
		 *	<B>Array:</B>
		 *
		 *	If an associative array is passed, the key
		 *	shall become the key, and the value shall
		 *	become the label of the resulting drop-down.
		 *
		 *	If an enumerated array is passed, the index
		 *	shall become the key, and the value shall
		 *	become the label.
		 *
		 *	In all cases the drop-down shall be populated
		 *	in the order in which data is returned.
		 *
		 *	\param [in] $key
		 *		The key that shall be associated
		 *		with this element.
		 *	\param [in] $value
		 *		The initial value of this element.
		 *	\param [in] $label
		 *		The text label that shall be associated
		 *		with this element.
		 *	\param [in] $conn_or_arr
		 *		A valid MySQLi database connection,
		 *		if \em query is not null, otherwise
		 *		an array which shall be used to
		 *		populate the drop-down.
		 *	\param [in] $empty
		 *		Whether no value makes sense for this
		 *		drop-down.  Ignored if not being
		 *		populated from a database query.
		 *	\param [in] $query
		 *		Must be provided if \em conn_or_arr
		 *		is a database connection.  The
		 *		query to execute to populate the
		 *		drop-down.
		 */
		public function __construct ($key, $value, $label, $conn_or_arr, $empty=null, $query=null) {
		
			parent::__construct($key,$value);
		
			$this->arr=array();
			
			if (is_array($conn_or_arr)) {
			
				//	Ignore fourth parameter,
				//	populate from array
				
				foreach ($conn_or_arr as $key=>$value) $this->arr[$key]=$value;
			
			} else {
			
				//	Throw if the fourth parameter is
				//	not set
				if (!isset($query)) throw new Exception('$query cannot be null');
				
				//	Perform query
				$query=$conn_or_arr->query($query);
				
				if ($query===false) throw new Exception($conn_or_arr->error);
				
				if ($empty) $this->arr['']='';
				
				if ($query->num_rows!==0) {
				
					//	Loop over result set
					for ($row=new MySQLRow($query);!is_null($row);$row=$row->Next()) {
					
						if (!isset($row[0])) throw new Exception('Query returned invalid results');
						
						$this->arr[(string)$row[0]]=(string)$row[isset($row[1]) ? 1 : 0];
					
					}
				
				}
			
			}
			
			$this->label=$label;
		
		}
		
		
		public function Verify () {
		
			if (is_numeric($this->value)) $this->value=intval($this->value);
		
			return in_array($this->value,array_keys($this->arr),true);
		
		}
		
		
		public function Render () {
		
			$options='';
		
			//	Loop and generate the <option>
			//	tag for each element
			foreach ($this->arr as $value=>$label) {
			
				$options.=sprintf(
					self::$options_template,
					htmlspecialchars($value),
					htmlspecialchars($label),
					($value===$this->value) ?	'selected' : ''
				);
			
			}
			
			//	Return output
			return sprintf(
				self::$template,
				htmlspecialchars($this->label),
				htmlspecialchars($this->key),
				$options
			);
		
		}
		
		
		public function RenderVerify () {
		
			//	No JavaScript verification code.
			//
			//	If the user is exploiting so they
			//	can send values not in the drop-down,
			//	we don't care if they get ugly
			//	back-end error messages.
			return null;
		
		}
		
	
	}
	
	
	/**
	 *	A set of radio buttons.
	 */
	class RadioButtonFormElement extends DataFormElement {
	
	
		public static $element_template='<div><input type="radio" name="%2$s" value="%3$s" %4$s /> %1$s</div>';
		public static $template='<div><div>%s:</div><div>%s</div></div>';
		public static $verify_template='do{var found=false;var elements=document.getElementsByName(%1$s);for(var i=0;i<elements.length;++i)if(elements[i].checked){found=true;break;}if(!found){ErrorElement(elements[0]);verified=false;}else{UnerrorElement(elements[0]);}}while(false);';
	
	
		private $label;
		private $values;
	
	
		public function __construct ($key, $values, $value, $label) {
		
			parent::__construct(
				$key,
				$value
			);
			
			$this->label=$label;
			$this->values=$values;
		
		}
		
		
		public function Verify () {
			
			//	Fail out on null
			if (is_null($this->value)) return false;
			
			//	Scan all values, if the value
			//	is amongst them, verification
			//	succeeds
			foreach ($this->values as $key=>$value) {
			
				if (((string)$this->value)===((string)$key)) return true;
				
			}
			
			//	Value wasn't among valid values,
			//	fail
			return false;
		
		}
		
		
		public function RenderVerify () {
		
			return sprintf(
				self::$verify_template,
				json_encode($this->key)
			);
		
		}
		
		
		public function Render () {
		
			$output='';
		
			foreach ($this->values as $key=>$label) {
			
				$output.=sprintf(
					self::$element_template,
					htmlspecialchars($label),
					htmlspecialchars($this->key),
					htmlspecialchars($key),
					!is_null($this->value) && (((string)$this->value)===((string)$key))
						?	'checked'
						:	''
				);
			
			}
			
			return sprintf(
				self::$template,
				$this->label,
				$output
			);
		
		}
	
	
	}
	
	
	/**
	 *	A checkbox.
	 */
	class CheckBoxFormElement extends DataFormElement {
	
	
		/**
		 *	The template which CheckBoxFormElement
		 *	objects shall use to render their HTML.
		 *
		 *	Shall be in the format expected for sprintf,
		 *	with the following substitutions to be
		 *	made.
		 *
		 *	1.	The label associated with this element.
		 *	2.	The key associated with this element.
		 *	3.	\"Checked\" if the checkbox starts out
		 *		checked, the empty string otherwise.
		 */
		public static $template='<div><div>%s:</div><div><input type="checkbox" value="true" name="%s" %s /></div></div>';
	
	
		private $label;
		
		
		/**
		 *	Creates a new CheckBoxFormElement.
		 *
		 *	\param [in] $key
		 *		The key that will be associated with
		 *		the element.
		 *	\param [in] $value
		 *		The value that will be associated with
		 *		the element.  Will be converted to a
		 *		boolean.
		 *	\param [in] $label
		 *		The text label that will be associated
		 *		with the element.
		 */
		public function __construct ($key, $value, $label) {
		
			parent::__construct(
				$key,
				$value ? true : false	//	Convert to a boolean
			);
		
			$this->label=$label;
		
		}
		
		
		public function Verify () {
		
			//	If the value is already a boolean,
			//	it checks out
			if (($this->value===true) || ($this->value===false)) return true;
			
			//	If it's exactly equal to the empty
			//	string, convert it to false and
			//	it's fine.
			if ($this->value==='') {
			
				$this->value=false;
				
				return true;
			
			}
			
			//	If it's exactly equal to "true",
			//	convert it to true and it's
			//	fine.
			if ($this->value==='true') {
			
				$this->value=true;
				
				return true;
			
			}
			
			//	Otherwise it's not a valid input
			//	so we fail.
			return false;
		
		}
		
		
		public function RenderVerify () {
		
			//	No JavaScript verification code.
			//
			//	If the user is exploiting so they
			//	can send values not in the drop-down,
			//	we don't care if they get ugly
			//	back-end error messages.
			return null;
		
		}
		
		
		public function Render () {
		
			return sprintf(
				self::$template,
				htmlspecialchars($this->label),
				htmlspecialchars($this->key),
				$this->value ? 'checked' : ''
			);
		
		}
	
	
	}
	
	
	class HiddenFormElement extends DataFormElement {
	
	
		public function __construct ($key, $value=null) {
		
			parent::__construct($key,$value);
		
		}
		
		
		public function RenderVerify () {
		
			return null;
		
		}
		
		
		public function Verify () {
		
			return true;
		
		}
		
		
		public function Render () {
		
			return sprintf(
				'<input type="hidden" name="%1$s" value="%2$s" />',
				htmlspecialchars($this->key),
				htmlspecialchars($this->value)
			);
		
		}
	
	
	}


?>