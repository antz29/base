<?php

namespace Base;

class HttpMessage {
	
	const REQUEST = 1;
	const RESPONSE = 2;
	
	private $_url = null;
	private $_params = array();
	private $_method = "GET";
	private $_headers = array();
	private $_content = "";
	private $_timeout = 30;
	private $_type = self::REQUEST;
	private $_status = 0;
	private $_user;
	private $_pass;
	
	public function getHash() {
		return md5($this->_url . $this->_method . implode(array_keys($this->_params)) . implode(array_values($this->_params)));
	}
	
	public function setUrl($url) {
		if ($this->_type == self::RESPONSE) return null;
		$this->_url = $url;
	}

	public function getUrl() {
		if ($this->_type == self::RESPONSE) return null;
		return $this->_url;
	}

	public function setParams($params) {
		if ($this->_type == self::RESPONSE) return null;
		$this->_params = $params;
	}	
	
	public function setParam($name,$value) {
		if ($this->_type == self::RESPONSE) return null;
		$this->_params[$name] = $value;
	}
	
	public function setHeader($name,$value) {
		if ($this->_type == self::RESPONSE) return null;
		$this->_headers[$name] = $value;
	}
	
	public function getHeader($name) {
		return isset($this->_headers[$name]) ? $this->_headers[$name] : null;
	}	
	
	public function getHeaders() {
		return $this->_headers;
	}	

	public function getStatus() {
		return $this->_status;
	}
	
	public function getContent() {
		return $this->_content;
	}
	
	public function setContent($content) {
		if ($this->_type == self::RESPONSE) return null;
		$this->_content = $content;
	}
	
	public function setMethod($method) {
		if ($this->_type == self::RESPONSE) return null;
		$this->_method = strtoupper($method);
	}
	
	public function setTimeout($timeout) {
		if ($this->_type == self::RESPONSE) return null;
		$this->_timeout = (int) $timeout;
	}
	
	public function getTimeout() {
		if ($this->_type == self::RESPONSE) return null;		
		return $this->_timeout;
	}
	
	public function setBasicAuth($user,$pass) {
		if ($this->_type == self::RESPONSE) return null;
		$this->_user = $user;
		$this->_pass = $pass;
	}
	
	public function exec() {
		if ($this->_type == self::RESPONSE) return null;

		$url = $this->_url;
	
		if (count($this->_params)) {
			if (!$this->_content && !isset($this->_headers['content-type']) && $this->_method != 'GET') {
				$this->setHeader('content-type','application/x-www-form-urlencoded');
				$this->_content = http_build_query($this->_params); 
			}
			else {
				$url .= '?' . http_build_query($this->_params);	
			} 
		}	
	
		$ch = curl_init();
		curl_setopt_array($ch, array(
			 CURLOPT_URL => $url,
			 CURLOPT_HEADER => true,
			 CURLOPT_TIMEOUT => $this->_timeout,
			 CURLOPT_CONNECTTIMEOUT => round($this->_timeout / 3),
			 CURLOPT_CUSTOMREQUEST => $this->_method,
			 CURLOPT_RETURNTRANSFER => true,
			 CURLOPT_FOLLOWLOCATION => false,
			 CURLOPT_MAXREDIRS => 1
			  
		));
		
		if ($this->_content) {
			curl_setopt($ch, CURLOPT_POSTFIELDS, $this->_content);
		}
		
		if (stristr($this->_url, 'https')) {
	      		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
		}
		
		$this->_headers['content-length'] = strlen($this->_content);
		
		$headers = array();
		foreach ($this->_headers as $header => $value) {
			$headers[] = "{$header}: $value";
		}
		curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
		
		if ($this->_user) {
			$pass = $this->_pass ? $this->_pass : 'X';
			curl_setopt($ch, CURLOPT_USERPWD, $this->_user . ':' . $pass);
		}		
		
		$response = explode("\r\n",curl_exec($ch));

		$headers = array_filter($response,function($val) {
			$ret = false;
			if (preg_match("!^[0-9A-Za-z\-]+:.*$!",$val)) $ret = true;
			return $ret;
		}); 
		
		$content = array_filter($response,function($val) {
			$ret = true;
			if (preg_match("!^HTTP/(1\.0|1\.1) [0-9]{3} [\w\s]+$!",$val)) $ret = false;
			if (preg_match("!^[0-9A-Za-z\-]+:.*$!",$val)) $ret = false;
			return $ret;
		}); 
		$content = trim(implode("\r\n",$content));

		$heads = array();
		foreach ($headers as $header) {
			$header = explode(':',$header,2);
			if (!isset($header[1])) continue;
			$head = strtolower($header[0]);
			$val = trim($header[1]);
			$heads[$head] = $val; 
		}	
		
		$resp = new HttpMessage();
		$resp->_type = self::RESPONSE;
		$resp->_content = $content ? $content : '';
		$resp->_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$resp->_headers = $heads;
		
		return $resp;
	}
	
	private function findLastStatusHeader($headers) {
		$last = null;
		
		foreach ($headers as $i => $val) {
			if (preg_match("!^HTTP/(1\.0|1\.1) [0-9]{3} [\w\s]+$!",$val)) {
				$last = $i;
			}
		}
		
		return $last;
	}

}