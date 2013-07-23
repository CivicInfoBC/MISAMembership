<?php


	class Template {
	
	
		private static $include_error="No file %2\$s in directory %1\$s.";
	
	
		private $__template_dir="./";
		private $__vars=array();
		private $__files=null;
		
		
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
		
		
		public function Next () {
		
			if (
				is_null($this->__files) ||
				(count($this->__files)===0)
			) return;
			
			$file=array_shift($this->__files);
			
			$filename=$this->__template_dir.$file;
			
			if (file_exists($filename)) include($filename);
			else throw new Exception(sprintf(
				self::$include_error,
				$this->__template_dir,
				$file
			));
		
		}
		
		
		public function Render ($file) {
		
			if (is_null($file)) return;
			
			if (!is_array($file)) $file=array($file);
		
			$this->__files=$file;
			
			while (count($this->__files)!==0) $this->Next();
		
		}
		
		
		public function Get ($file) {
		
			ob_start();
			
			$this->Render($file);
			
			$str=ob_get_contents();
			
			ob_end_clean();
			
			return $str;
		
		}
	
	
	}


?>