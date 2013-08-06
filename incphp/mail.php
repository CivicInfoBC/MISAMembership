<?php


	require_once(WHERE_PHP_INCLUDES.'template.php');
	
	
	class EMail {
	
	
		public $to;
		public $from;
		public $is_html=false;
		public $subject;
		
		
		public function Send ($message) {
		
			if ($message instanceof Template) {
			
				$args=func_get_args();
				
				if (count($args)<2) throw new Exception('Argument mismatch');
				
				$message=$message->Get($args[1]);
			
			}
			
			if (is_array($this->to)) {
			
				$to='';
				foreach ($this->to as $x) {
				
					if ($to!=='') $to.=',';
					
					$to.=$x;
				
				}
			
			} else {
			
				$to=$this->to;
			
			}
			
			if (is_null($to) || ($to==='')) return;
			
			$headers='';
			
			if (isset($this->from)) $headers='From: '.$this->from."\r\n";
			
			if ($this->is_html) $headers.="Content-Type: text/html\r\n";
			
			if ($headers==='') $headers=null;
			
			return mb_send_mail(
				$to,
				$this->subject,
				$message,
				$headers
			);
		
		}
	
	
	}


?>