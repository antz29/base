<?php
namespace Base\Config;

class Node implements \ArrayAccess {
	
	protected $_data;
	
	public function __construct($data) {		
		$this->_data = $data;		
	}	
	
	public function __get($name) {
		return $this->get($name);
	}
	
	public function get($name) {
		$val = isset($this->_data[$name]) ? $this->_data[$name] : null;
		
		if ($val == null) return null;
		if (is_array($val)) {	
			if (isset($val[0])) {
				return $val;
			} else {
				return new Node($val);
			}
		}
		
		return $val;
	}
	
	public function has($name) {
		return isset($this->_data[$name]);
	}
	
	public function __isset($name) {
		return $this->has($name);
	}

	public function getData() {
		return $this->_data;
	}
	
	public function offsetExists($name) {
		return $this->has($name);
	}

	public function offsetGet($name) {
		return $this->get($name);
	}
	
	public function offsetSet($name,$value) {
		return false;
	}
	
	public function offsetUnset($name) {
		return false;
	}
}