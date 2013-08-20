<?php


	class KeyValueStore implements IteratorAggregate {
	
	
		private $vars=array();
		
		
		public function __set ($name, $value) {
		
			$this->vars[$name]=$value;
		
		}
		
		
		public function __isset ($name) {
		
			return isset($this->vars[$name]) && !is_null($this->vars[$name]);
		
		}
		
		
		public function __unset ($name) {
		
			unset($this->vars[$name]);
		
		}
		
		
		public function __get ($name) {
		
			if (isset($this->vars[$name])) return $this->vars[$name];
			
			return null;
		
		}
		
		
		public function getIterator () {
		
			return new ArrayIterator($this->vars);
		
		}
	
	
	}


?>