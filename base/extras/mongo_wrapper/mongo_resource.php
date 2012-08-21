<?php
namespace Base\Resources;

use \Base\Config\Node;

class Mongo extends \Base\Resource {
	
	private $conn;
	
	protected function setUp(Node $config) {
		
		if ($this->conn != null) {
			if (isset($config['user']) && isset($config['user'])) {
				$this->conn->authenticate($config['user'],$config['pass']);
			}
			return $this->conn->selectDB($config['data']);	
		}
				
		$userpass = '';
		
		if (isset($config['user']) && isset($config['pass'])) {
				$userpass = "{$config['user']}:{$config['pass']}@";
		} 
		
		$dsn = "mongodb://{$userpass}{$config['host']}:{$config['port']}/{$config['data']}";		
		
		try {
			$this->conn = new \Mongo($dsn,array("persist" => md5(__FILE__),));
			return $this->conn->selectDB($config['data']);
		}
		catch (\MongoConnectionException $e) {
			throw $e;
		}
	}
}