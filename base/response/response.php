<?php

namespace Base;

class Response {

	static private $_instance;

	private $_status_codes = array(
		100 => 'Continue',
		101 => 'Switching Protocols',
		200 => 'OK',
		201 => 'Created',
		202 => 'Accepted',
		203 => 'Non-Authoritative Information',
		204 => 'No Content',
		205 => 'Reset Content',
		206 => 'Partial Content',
		300 => 'Multiple Choices',
		301 => 'Moved Permanently',
		302 => 'Found',
		303 => 'See Other',
		304 => 'Not Modified',
		305 => 'Use Proxy',
		307 => 'Temporary Redirect',
		400 => 'Bad Request',
		401 => 'Unathorized',
		402 => 'Payment Required',
		403 => 'Forbidden',
		404 => 'Not Found',
		405 => 'Method Not Allowed',
		406 => 'Not Acceptable',
		407 => 'Proxy Authentication Required',
		408 => 'Request Timeout',
		409 => 'Conflict',
		410 => 'Gone',
		411 => 'Length Required',
		412 => 'Precondition Failed',
		413 => 'Request Entity Too Large',
		414 => 'Request-URI Too Long',
		415 => 'Unsupported Media Type',
		416 => 'Requested Range Not Satisfiable',
		417 => 'Expectation Failed',
		500 => 'Internal Server Error',
		501 => 'Not Implemented',
		502 => 'Bad Gateway',
		503 => 'Service Unavailable',
		504 => 'Gateway Timeout',
		505 => 'HTTP Version Not Supported'
	);

	private $_content_encoding = 'utf-8';

	private $_status = 200;
	private $_headers = array('content-type'=>'text/html; charset=utf-8');

	private $_cookies = array();
	private $_body = "";

	/**
	 * getInstance
	 * 
	 * Get a singleton instance of the class
	 *
	 * @return Response
	 */
	static public function getInstance()
	{
		if (!(self::$_instance instanceof self)) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	private function __construct() {}
	private function __clone() {}

	/**
	 * setContent
	 * 
	 * Set the body contents of the response. Will overwrite any existing content.
	 *
	 * @param $content string
	 */
	public function setContent($content)
	{
		$this->_body = $content;
	}

	/**
	 * addContent
	 * 
	 * Append the given $content to the output.
	 *
	 * @param string $content
	 */
	public function addContent($content)
	{
		$this->_body .= $content;
	}

	/**
	 * getContent
	 * 
	 * Return the current body contents of the response.
	 * 
	 * @return string
	 */
	public function getContent()
	{
		return $this->_body;
	}

	/**
	 * clearContent
	 * 
	 * Clear the current body contents.
	 *
	 */
	public function clearContent()
	{
		$this->_body = "";
	}

	/**
	 * setHeader
	 * 
	 * Set a header in the output providing the $header name and $value.
	 *
	 * @param $header string
	 * @param $value string
	 */
	public function setHeader($header,$value)
	{
		$header = strtolower($header);
		if ($header == 'content-type') {
			$value = explode(';',$value);
			if (count($value) == 1) $value[] = 'charset=utf-8';
			$value = implode(';',$value);
		} 
		$this->_headers[$header] = $value;
	}

	/**
	 * delHeader
	 * 
	 * Delete the specified $header from the response.
	 *
	 * @param $header string
	 */
	public function delHeader($header)
	{
		$header = strtolower($header);
		unset($this->_headers[$header]);
	}

	/**
	 * getHeader
	 * 
	 * Get the value of a $header in the response.
	 *
	 * @param $header string
	 * @return string
	 */
	public function getHeader($header)
	{
		$header = strtolower($header);
		return isset($this->_headers[$header]) ? $this->_headers[$header] : null;
	}

	/**
	 * getHeaders
	 * 
	 * Return all the headers in the response as an associative array.
	 *
	 * @return array
	 */
	public function getHeaders()
	{
		return $this->_headers;
	}

	/**
	 * setStatus
	 * 
	 * Set the HTTP status code of the response.
	 *
	 * @param $status int
	 */
	public function setStatus($status)
	{	
		if (!isset($this->_status_codes[$status])) $status = 200;
		$this->_status = (int) $status;
	}

	/**
	 * getStatus
	 * 
	 * Get the HTTP status code of the response.
	 *
	 * @return int
	 */
	public function getStatus()
	{
		return $this->_status;
	}

	/**
	 * sendStatus
	 * 
	 * Send the status to the browser.
	 *
	 */
	public function sendStatus()
	{
		if (!$this->_body && $this->_status == 200) $this->_status = 204;
		header("HTTP/1.1 {$this->_status} {$this->_status_codes[$this->_status]}");
	}

	public function setContentEncoding($enc) 
	{
		$this->_content_encoding = $enc;
	}

	public function getContentEncoding() 
	{
		return $this->_content_encoding;
	}

	/**
	 * sendHeaders
	 * 
	 * Send all headers to the browser.
	 *
	 */
	public function sendHeaders()
	{
		$this->_headers['x-powered-by'] = 'Base Framework';
		foreach ($this->_headers as $header => $value) {
			header("{$header}: {$value}");
		}

		if (count($this->_cookies)) {	
			header('P3P: CP="NOI ADM DEV PSAi COM NAV OUR OTRo STP IND DEM"');
			foreach ($this->_cookies as $name => $cookie) {
				setcookie($name,$cookie['value'],$cookie['expire'],$cookie['path'],$cookie['domain'],$cookie['secure'],$cookie['httponly']);
			}
		}
	}

	/**
	 * sendContent
	 * 
	 * Send the body content to the browser.
	 *
	 */
	public function sendContent()
	{
		echo $this->_body;
	}

	public function sendAll()
	{
		$this->sendStatus();
		$this->sendHeaders();
		$this->sendContent();
	}
	
	public function redirect($target)
	{
		$this->setHeader('location', $target);
		$this->sendAll();
		die();
	}
	
	/**
	 * setCookie
	 * 
	 * Set cookie headers to be sent with the response. Follows the standard PHP setcookie interface, but automatically works out
	 * if secure and httponly are require or not. The default value for $path is '/'.
	 * 
	 * @param $name string 
	 * @param $value string
	 * @param $expire int
	 * @param $path string
	 * @param $domain string
	 */
	public function setCookie($name,$value,$expire=0,$path='/',$domain=null) 
	{
		$https = Enviroment::getInstance()->getServer('HTTPS');
		$secure = ($https && $https !== 'off') ? true : false;

		$httponly = $secure ? true : false;

		if (!$expire || $expire > time()) {	
			Request::getInstance()->setCookie($name,$value);
		}

		$this->_cookies[$name] = array('value' => $value,'expire' => $expire,'path' => $path,'domain' => $domain,'secure' => $secure,'httponly' => $httponly);
	}
}
