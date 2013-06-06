<?php


	require_once(WHERE_PHP_INCLUDES.'define.php');
	
	
	//	SQL Constants
	def('SQL_HOST','127.0.0.1:3306');
	def('SQL_CHARSET','utf8mb4');
	
	
	//	MISA Database Constants
	def('MISA_USERNAME','');
	def('MISA_PASSWORD','');
	def('MISA_DATABASE','');
	
	
	function get_mysql_conn ($username, $password, $host, $database) {
	
		$conn=new mysqli(
			$host,
			$username,
			$password,
			$database
		);
		
		if ($conn->connect_error) throw new Exception($conn->connect_error);
		
		if ($conn->set_charset(SQL_CHARSET)===false) throw new Exception($conn->error);
		
		return $conn;
	
	}
	
	
	function get_misa_conn () {
	
		return get_mysql_conn(
			MISA_USERNAME,
			MISA_PASSWORD,
			SQL_HOST,
			MISA_DATABASE
		);
	
	}
	
	
	$dependency_map=array(
		'MISADBConn' => 'get_misa_conn'
	);


?>