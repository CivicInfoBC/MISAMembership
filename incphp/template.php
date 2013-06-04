<?php


	class Template {
	
	
		private static $include_error="No file %2\$s in directory %1\$s.";
	
	
		private $__template_dir="./";
		private $__vars=array();
		
		
		public function __construct ($template_dir=null) {
		
			if ($this->__template_dir!=null) $this->__template_dir=$template_dir;
		
		}
		
		
		public function __set ($name, $value) {
		
			$this->__vars[$name]=$value;
		
		}
		
		
		public function __isset ($name) {
		
			return isset($this->__vars[$name]);
		
		}
		
		
		public function __unset ($name) {
		
			unset($this->__vars[$name]);
		
		}
		
		
		public function & __get ($name) {
		
			return $this->__vars[$name];
		
		}
		
		
		public function Render ($template_file) {
		
			$template_filename=$this->__template_dir.$template_file;
		
			if (file_exists($template_filename)) include($template_filename);
			else throw new Exception(sprintf(
				self::$include_error,
				$this->__template_dir,
				$template_file
			));
		
		}
	
	
	}


?>