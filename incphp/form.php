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
		 *		A string containing JavaScript.
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
			$form=	'<form action="'.
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
		public static $verify_template='';
		
		
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
				'/'.$this->regex.'/',
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
		 */
		public static $template='<div><div>%s:</div><div><input name="%s" value="%s" type="%s" /></div></div>';
		public static $verify_template=null;
		
		
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
		 */
		public function __construct ($key, $label, $regex, $value=null, $type='text') {
		
			parent::__construct($key,$regex,$value);
			
			$this->label=$label;
			$this->type=$type;
		
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
				htmlspecialchars($this->type)
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
			
			//	This flag gets set if the currently-selected
			//	option represents a value in the drop-down
			$is_dropdown=false;
			
			//	A string containing the HTML
			//	for the options tags that go
			//	in the select tag.
			$options='';
			
			//	Loop and create HTML for options
			foreach (self::$options as $value=>$label) {
			
				//	Is this the currently selected
				//	option?
				$is_this=MBString::Compare(
					$value,
					$this->value
				);
				
				//	If this option is the selected
				//	option, then the option is a
				//	drop-down option.
				//
				//	Communicate this.
				$is_dropdown=true;
				
				//	Render
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
				isset($arr[$this->key])
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
	
	
	}
	
	
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
		 */
		public static $verify_template='';
		

		private $confirm_value;
		
		
		/**
		 *	The label by which this form element shall be
		 *	identified.
		 */
		public $label;
	
	
		/**
		 *	Creates a new ChangePasswordFormElement.
		 *
		 *	\param [in] $key
		 *		The key that shall be associated
		 *		with this element.
		 */
		public function __construct ($key, $label) {
		
			parent::__construct($key);
			
			$this->confirm_value=null;
			$this->label=$label;
		
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
			//	the password, so that's fine
			if (
				(is_null($this->value) || ($this->value==='')) &&
				(is_null($this->confirm_value) || ($this->confirm_value===''))
			) return true;
			
			//	Otherwise make sure they're
			//	the same
			return $this->value===$this->confirm_value;
		
		}
		
		
		public function RenderVerify () {
		
			return sprintf(
				self::$verify_template,
				json_encode($this->key)
			);
		
		}
		
		
		public function Render () {
		
			return sprintf(
				self::$template,
				htmlspecialchars($this->label),
				htmlspecialchars($this->key)
			);
		
		}
	
	
	}


?>