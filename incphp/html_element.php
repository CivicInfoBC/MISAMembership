<?php

	
	/**
	 *	An element in an HTML document
	 *	which represents literal text.
	 */
	class HTMLTextElement {
	
	
		/**
		 *	The text of this HTML text element.
		 */
		public $Value;
		
		
		/**
		 *	Creates a new HTMLTextElement with a
		 *	given textual value.
		 *
		 *	\param [in] $value
		 *		The text that shall be stored
		 *		within this element.
		 */
		public function __construct ($value) {
		
			$this->Value=$value;
		
		}
		
		
		/**
		 *	Converts this element to HTML.
		 *
		 *	\return
		 *		The HTML representation of this
		 *		element.
		 */
		public function __toString () {
		
			return htmlspecialchars($this->Value);
		
		}
	
	
	}


	/**
	 *	An element in an HTML document.
	 */
	class HTMLElement {
	
	
		/**
		 *	All nodes within this node in the
		 *	tree.
		 */
		public $Children;
		/**
		 *	This element's tag.
		 */
		public $TagName;
		/**
		 *	An array of key value pairs specifying
		 *	this element's attributes.
		 */
		public $Attributes;
		/**
		 *	Whether the element must always be explicitly
		 *	closed.
		 */
		public $ExplicitClose;
		
		
		/**
		 *	Creates a new element with no children.
		 *
		 *	\param [in] $tag_name
		 *		This element's tag name.
		 *	\param [in] $attributes
		 *		An array of key value pairs representing
		 *		the attributes of this element.
		 */
		public function __construct ($tag_name, $attributes=null) {
		
			$this->Children=array();
			
			if ($attributes===null) $this->Attributes=array();
			else if (is_array($attributes)) $this->Attributes=$attributes;
			else $this->Attributes=array($attributes);
			
			$this->TagName=$tag_name;
			
			$this->ExplicitClose=false;
		
		}
		
		
		/**
		 *	Converts this element to HTML.
		 *
		 *	\return
		 *		The HTML representation of this element
		 *		and all children.
		 */
		public function __toString () {
		
			$attributes='';
			foreach ($this->Attributes as $key=>$value) {
			
				$attributes.=' '.htmlspecialchars($key).'="'.htmlspecialchars($value).'"';
			
			}
			
			if (
				(count($this->Children)===0) &&
				!$this->ExplicitClose
			) {
			
				return '<'.htmlspecialchars($this->TagName).$attributes.' />';
			
			}
			
			$children='';
			foreach ($this->Children as $x) $children.=(string)$x;
			
			return '<'.htmlspecialchars($this->TagName).$attributes.'>'.$children.'</'.htmlspecialchars($this->TagName).'>';
		
		}
	
	
	}


?>