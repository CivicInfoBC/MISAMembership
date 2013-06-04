<?php


	/**
	 *	Resolves dependencies.
	 *
	 *	For example, you may have several different
	 *	database connections.  You don't wish to
	 *	define the logic for creating that connection
	 *	each time it's used, nor do you want to pass
	 *	it around as a parameter (not a scalable design).
	 *	Instead you define it as a dependency.  The
	 *	DependencyContainer will create the connection
	 *	the first time it's used, and return a reference
	 *	to it thereafter.
	 */
	class DependencyContainer implements arrayaccess {
	
	
		private $dependency_map;
		private $loaded_dependencies;
		
		
		/**
		 *	Creates a new DependencyContainer.
		 *
		 *	\param [in] $dependency_map
		 *		An array which maps dependency
		 *		names to the functions that must
		 *		be called to obtain them.  If
		 *		any function returns \em null or
		 *		\em false when called, or throws,
		 *		it is considered to have failed.
		 */
		public function __construct ($dependency_map) {
		
			if (!is_array($dependency_map)) throw new Exception('Type mismatch');
			
			$this->dependency_map=$dependency_map;
			$this->loaded_dependencies=array();
		
		}
		
		
		/**
		 *	Determines whether this container holds
		 *	a given dependency, either loaded or
		 *	not.
		 *
		 *	\param [in] $offset
		 *		The name of the dependency.
		 *
		 *	\return
		 *		\em true if the container holds
		 *		the dependency named \em offset,
		 *		\em false otherwise.
		 */
		public function offsetExists ($offset) {
		
			return (
				isset($this->loaded_dependencies[$offset]) ||
				(
					isset($this->dependency_map[$offset]) &&
					function_exists($this->dependency_map[$offset])
				)
			);
		
		}
		
		
		/**
		 *	Retrieves a given dependency, creating
		 *	it if it hasn't been used yet.
		 *
		 *	\param [in] $offset
		 *		The dependency to retrieve.
		 *
		 *	\return
		 *		The dependency named by \em offset.
		 */
		public function offsetGet ($offset) {
		
			if (isset($this->loaded_dependencies[$offset])) return $this->loaded_dependencies[$offset];
			
			if (
				isset($this->dependency_map[$offset]) &&
				function_exists($this->dependency_map[$offset])
			) {
			
				try {
				
					$dependency=call_user_func($this->dependency_map[$offset]);
					
					if (!(
						!isset($dependency) ||
						is_null($dependency) ||
						($dependency===false)
					)) {
					
						$this->loaded_dependencies[$offset]=$dependency;
						
						return $dependency;
					
					}
				
				} catch (Exception $e) {	}
				
				throw new Exception('Failed loading dependency!');
			
			}
			
			throw new Exception('Dependency does not exist');
		
		}
		
		
		/**
		 *	Allows a dependency to be changed or added.
		 *
		 *	\param [in] $offset
		 *		The name of the dependency to change or
		 *		add.
		 *	\param [in] $value
		 *		The dependency to set.
		 */
		public function offsetSet ($offset, $value) {
		
			$this->loaded_dependencies[$offset]=$value;
		
		}
		
		
		/**
		 *	Unloads a dependency if it is loaded.
		 *
		 *	It will be reloaded the next time it is used.
		 *
		 *	\param [in] $offset
		 *		The name of the dependency to unload.
		 */
		public function offsetUnset ($offset) {
		
			if (isset($this->loaded_dependencies[$offset])) unset($this->loaded_dependencies[$offset]);
		
		}
	
	
	}


?>