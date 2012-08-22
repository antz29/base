<?php

namespace Base;

class Request
{
	private $_data;
	private $_raw_data;

	private $_method;
	private $_headers;
	private $_cookies;
	private $_query;

	private $_base_uri;
	
	private $_uri;
	private $_uri_segments;
	private $_segment_identifiers = array();
	private $_parsed_segments;

	private $_uri_params;
	private $_parsed_uri_params;
	
	static private $_instance;

	/**
	 * getInstance
	 * 
	 * Get a singleton instance of the class.
	 *
	 * @return Base\Request
	 */
	static public function getInstance()
	{
		if (!(self::$_instance instanceof Request)) {
			self::init();
		}
		return self::$_instance;
	}

	/**
	 * init
	 * 
	 * Initialise the request.
	 *
	 */
	static public function init($base_uri='')
	{
		self::$_instance = new self($base_uri);
	}

	/**
	 * Constructor
	 * 
	 * Instanciate a new smp_Request
	 *
	 */
	private function __construct($base_uri="") {
		$this->_base_uri = $base_uri;
	}

	public function getBaseUri()
	{
		return $this->_base_uri;
	}
	
	/**
	 * getMethod
	 * 
	 * Get the HTTP method
	 *
	 * @return string
	 */
	public function getMethod()
	{
		if (!isset($this->_method)) $this->setMethod($this->getRequestMethod());
		return $this->_method;
	}

	/**
	 * setMethod
	 * 
	 * Set the HTTP method
	 *
	 * @param $method string
	 * @return string
	 */
	public function setMethod($method)
	{
		return $this->_method = $method;
	}

	/**
	 * getQuery
	 * 
	 * Get the parsed query string data as an associative array
	 *
	 * @return array
	 */
	public function getQuery($element = null)
	{
		if (!isset($this->_query)) $this->setQuery($this->getRequestQuery());
		return isset($element) ? (isset($this->_query[$element]) ? $this->_query[$element] : null) : $this->_query;
	}

	/**
	 * setQuery
	 * 
	 * Set the parsed query string data to an associative array
	 *
	 * @param array $query
	 */
	public function setQuery(array $query = array())
	{
		$this->_query = $query;
	}

	/**
	 * getData
	 * 
	 * Get the parsed request data as an associative array
	 *
	 * @return array
	 */
	public function getData($element = null)
	{
		if (!isset($this->_data)) $this->setData($this->getRequestData());
		return isset($element) ? (isset($this->_data[$element]) ? $this->_data[$element] : null) : $this->_data;
	}

	/**
	 * setData
	 * 
	 * Set the parsed request data to an associative array
	 *
	 * @param array $data
	 */
	public function setData(array $data = array())
	{
		$this->_data = $data;
	}

	/**
	 * getRawData
	 * 
	 * Get the raw unparsed data.
	 *
	 * @return string
	 */
	public function getRawData()
	{
		if (!isset($this->_raw_data)) $this->setRawData($this->getRequestData(true));
		return $this->_raw_data;
	}

	/**
	 * setRawData
	 * 
	 * Set the raw unparsed data.
	 *
	 * @param $data string
	 */
	public function setRawData($data)
	{
		$this->_raw_data = $data;
	}

	/**
	 * getUri
	 * 
	 * Get the URI as a string.
	 *
	 * @return string
	 */
	public function getUri($no_base = false)
	{
		if (!isset($this->_uri)) $this->setUri($this->getRequestUri());
		if ($no_base) {
			return $this->_uri;
		}
		return $this->filterBaseUri($this->_uri,$this->_base_uri);
	}

	/**
	 * setUri
	 * 
	 * Set the URI to a string.
	 *
	 * @param $uri string
	 */
	public function setUri($uri)
	{
		$this->_uri_segments = null;
		$this->_parsed_segments = null;
		$this->_parsed_uri_params = null;	
		$this->_uri = $uri;
	}

	/**
	 * getUriSegments
	 * 
	 * Get the URI divided up into segments as an array.
	 * 
	 * @return array
	 */
	public function getUriSegments()
	{
		if (!isset($this->_uri_segments)) $this->_uri_segments = $this->splitUriSegments($this->getUri());
		return $this->_uri_segments;
	}

	/**
	 * getUriSegment
	 * 
	 * Return the URI segment specified by the index. First segment is 0.
	 *
	 * @param $index int
	 * @return string
	 */
	public function getUriSegment($index)
	{
		if (!isset($this->_uri_segments)) $this->_uri_segments = $this->splitUriSegments($this->getUri());
		return isset($this->_uri_segments[$index]) ? $this->_uri_segments[$index] : false;
	}

	/**
	 * getParams
	 * 
	 * Return the URI params as an array.
	 *
	 * @return array
	 */
	public function getParams()
	{
		if (!$this->_parsed_segments) $this->parse();
		return $this->_parsed_segments['uri'];
	}

	/**
	 * getParam
	 * 
	 * Return the URI param specified by the index. First param is 0.
	 *
	 * @param $index int
	 * @return string
	 */
	public function getParam($index)
	{
		if (!$this->_parsed_segments) $this->parse();
		return isset($this->_parsed_segments['uri'][$index]) ? $this->_parsed_segments['uri'][$index] : false;
	}

	/**
	 * getParsedParams
	 * 
	 * Return the parsed URI params as an associative array.
	 *
	 * @see parseUriParams
	 *
	 * @return array
	 */
	public function getParsedParams()
	{
		if (!$this->_parsed_uri_params) $this->_parsed_uri_params = $this->parseUriParams();
		return $this->_parsed_uri_params;
	}

	/**
	 * getParsedParam
	 * 
	 * Return the parsed URI param specified by the given key.
	 *
	 * @see parseUriParams
	 *
	 * @param $key string|int
	 * @return string
	 */
	public function getParsedParam($index)
	{
		if (!$this->_parsed_uri_params) $this->_parsed_uri_params = $this->parseUriParams();
		return isset($this->_parsed_uri_params[$index]) ? $this->_parsed_uri_params[$index] : false;
	}

	/**
	 * getCookie
	 * 
	 * Return the value of a cookie in the request.
	 *
	 * @param $name string
	 * @return string
	 */
	public function getCookie($name)
	{
		if (!isset($this->_cookies)) $this->_cookies = $this->getRequestCookies();
		return isset($this->_cookies[$name]) ? $this->_cookies[$name] : null;
	}	
	
	/**
	 * getHeader
	 * 
	 * Return the value of a header in the request.
	 *
	 * @param $header string
	 * @return string
	 */
	public function getHeader($header)
	{
		if (!isset($this->_headers)) $this->_headers = $this->getRequestHeaders();
		return isset($this->_headers[$header]) ? $this->parseHeader($this->_headers[$header]) : null;
	}

	/**
	 * getHeaders
	 * 
	 * Return the request headers as an associative array.
	 *
	 * @return array
	 */
	public function getHeaders()
	{
		if (!isset($this->_headers)) $this->_headers = $this->getRequestHeaders();
		return $this->_headers;
	}

	/**
	 * setHeader
	 * 
	 * Set the $header in the request to $value.
	 *
	 * @param $header string
	 * @param $value string
	 */
	public function setHeader($header,$value)
	{
		$this->_headers[$header] = $value;
	}

	/**
	 * delHeader
	 * 
	 * Delete the $header from the request.
	 *
	 * @param $header string
	 */
	public function delHeader($header)
	{
		unset($this->_headers[$header]);
	}

	/**
	 * setHeaders
	 * 
	 * Set the request headers to the given array.
	 *
	 * @param $headers array
	 */
	public function setHeaders(array $headers)
	{
		$this->_headers = $headers;
	}
	
	/**
	 * setCookies
	 * 
	 * Set the request cookies to the given array.
	 *
	 * @param $cookies array
	 */
	public function setCookies(array $cookies)
	{
		$this->_cookies = $cookies;
	}
	
	/**
	 * setCookie
	 * 
	 * Set the request cookie with the name $name to the given $value.
	 *
	 * @param $cookie array
	 */
	public function setCookie($name,$value)
	{
		$this->_cookies[$name] = $value;
	}

	public function setBaseUri($base) 
	{
		if ($this->_base_uri != $base) {
			$this->_parsed_uri_params = false;
			$this->_parsed_segments = false;
		}
		$this->_base_uri = $base;
	}
	
	private function filterBaseUri($uri,$base,$return_array=false) 
	{		
		$base = is_array($base) ? $base : array_merge(array_filter(explode('/',trim($base))));
		$uri = is_array($uri) ? $uri : array_merge(array_filter(explode('/',trim($uri))));
		
		while(count($base) && count($uri)) {
			$b = array_shift($base);
			if (!count($base) && $b == '*') break;
			if (fnmatch($b,$uri[0])) {
				array_shift($uri);
			}
		}
		
		if (!$return_array) return '/'.implode('/',$uri);
				
		return $uri;
	}
	
	private function getRequestUri()
	{
		if (php_sapi_name() == 'cli') {
			$_SERVER['REQUEST_URI'] = '/';
		}

		$uri = explode('?',$_SERVER['REQUEST_URI']);
		$uri = array_shift($uri);
		$uri = $uri ? $uri : '/';
		$uri = array_merge(array_filter(explode('/',trim($uri))));
		
		if (!isset($uri[0])) return '/';
		
		if ($this->_base_uri) {
			$base = array_merge(array_filter(explode('/',trim($this->_base_uri))));
			while(count($base)) {
				if ($uri[0] == array_shift($base)) {
					array_shift($uri);
				}
			}
		}
		if (isset($uri[0]) && $uri[0] == 'index.php') array_shift($uri);
		return '/'.implode('/',$uri);
	}

	/**
	 * getRequestMethod
	 * 
	 * Return the request method of the request.
	 *
	 * @return string
	 */
	private function getRequestMethod()
	{
		if (php_sapi_name() == 'cli') {
			$_SERVER['REQUEST_METHOD'] = 'CLI';
		}

		return strtolower($_SERVER['REQUEST_METHOD']);
	}

	/**
	 * getRequestData
	 * 
	 * Return the input data, either parsed or raw by passing in boolean true. This method abstracts the complexity of
	 * getting input data for methods other than POST or GET.
	 *
	 * @return string|array
	 */
	private function getRequestData($raw=false)
	{
		if (php_sapi_name() == 'cli') {
			return file_get_contents("php://input");
		}

		if ($raw) return file_get_contents("php://input");
		$out = array();
		switch ($this->getRequestMethod()) {
			case 'get':
				$out = $_GET;
				break;
			case 'post':
				$out = $_POST;
			default:
				$input = file_get_contents("php://input");
				parse_str($input,$data);
				$out = $data;
				break;
		}

		$out = array_map(function($val) { return ($val == '_empty_list') ? array() : $val; },$out);

		return $out;
	}

	/**
	 * getRequestQuery
	 * 
	 * Return the query string, either parsed or raw by passing in boolean true.
	 *
	 * @return string|array
	 */
	private function getRequestQuery($raw=false)
	{
		if (php_sapi_name() == 'cli') {
			return "";
		}

		$query = parse_url($_SERVER['REQUEST_URI']);
		$query = isset($query['query']) ? $query['query'] : "";
			
		if ($raw) return $query;

		parse_str($query,$query);
		return $query;
	}

	/**
	 * array_map method used by getRequestHeaders
	 *
	 * @see getRequestHeaders
	 */
	private function mapHeaderValues($key)
	{
		return isset($_SERVER[$key]) ? $_SERVER[$key] : '';
	}

	/**
	 * array_map method used by getRequestHeaders
	 *
	 * @see getRequestHeaders
	 */
	private function mapHeaderNames($name)
	{
		return str_replace('_','-',strtolower(substr($name,5)));
	}

	/**
	 * array_map method used by getRequestHeaders
	 *
	 * @see getRequestHeaders
	 */
	private function mapHeaderLower($name)
	{
		return strtolower($name);
	}

	/**
	 * array_map method used by getRequestHeaders
	 *
	 * @see getRequestHeaders
	 */
	private function filterHeaders($val)
	{
		if (substr($val,0,5) == 'HTTP_') return true;
		return false;
	}

	/**
	 * Return and normalise the the request headers.
	 *
	 * @return array
	 */
	private function getRequestHeaders()
	{
		if (php_sapi_name() == 'cli') {
			return array();
		}

		if (function_exists('getallheaders')) { 
			$headers = getallheaders();
			$names = array_map(array($this,'mapHeaderLower'),array_keys($headers));
			return array_combine($names,array_values($headers));
		}
		else {
			$headers = array_keys($_SERVER);
			$headers = array_filter($headers,array($this,'filterHeaders'));
			$names = array_map(array($this,'mapHeaderNames'),$headers);
			$values = array_map(array($this,'mapHeaderValues'),$headers);
			return array_combine($names,$values);
		}
	}
	
	/**
	 * Return and normalise the the request headers.
	 *
	 * @return array
	 */
	private function getRequestCookies()
	{
		if (php_sapi_name() == 'cli') {
			return array();
		}

		return $_COOKIE;
	}

	/**
	 * array_map method used by parseHeader.
	 *
	 * @see parseHeader
	 */
	private function cleanHeader($header)
	{
		$spl = explode(';',$header);
		return trim($spl[0]);
	}

	/**
	 * Parse a header that contains multiple comma separated values values.
	 *
	 * @param $header string
	 * @return string|array
	 */
	private function parseHeader($header)
	{
		if (stristr($header,',')) {
			$header = explode(',',$header);
			return array_map(array($this,'cleanHeader'),$header);
		} else {
			return $this->cleanHeader($header);
		}
	}

	/**
	 * addSegmentIdentifier
	 * 
	 * Add a segment identifier for use by 'parse' later.
	 * 
	 * @see parse
	 * @param $name string
	 */
	public function addSegmentIdentifier($name,$default = null)
	{
		$this->_parsed_segments = null;
		$this->_parsed_uri_params = null;
		
		if (!isset($default)) {
			$this->_segment_identifiers[] = $name;	
		}
		else {
			$this->_segment_identifiers[$name] = $default;
		}
	}

	public function clearSegmentIdentifiers()
	{
		$this->_segment_identifiers = array();
		$this->_parsed_segments = null;
		$this->_parsed_uri_params = null;
	}
	
	public function removeSegmentIdentifier($name)
	{
		unset($this->_segment_identifiers[$name]);
		$this->_parsed_segments = null;
		$this->_parsed_uri_params = null;
	}	
	
	public function getSegmentIdentifier($name)
	{
		if (!$this->_parsed_segments) $this->parse();
		
		return isset($this->_parsed_segments[$name]) ? $this->_parsed_segments[$name] : null; 
	}
	
	public function getSegmentIdentifiers()
	{
		if (!$this->_parsed_segments) $this->parse();
		
		return $this->_parsed_segments; 
	}
	
	/**
	 * parse
	 * 
	 * Parse the uri dividing it up into the previously defined segment identifiers. If you added the segments 'controller' and 'action',
	 * with the uri /foo/bar/param1/param2 you will get back an associative array array('controller'=>'foo','action'=>'bar','uri'=>array('param1','param2')).
	 *
	 * @param $segments array
	 * @return void
	 */
	private function parse()
	{
		$this->_parsed_segments = $this->parseSegments($this->_segment_identifiers);	
	}
	
	/**
	 * Match the uri against a glob pattern. Returns boolean true on a match.
	 *
	 * Examples:
	 *
	 * /something/* - matches /something/foo and /something/foo/bar
	 * /something/foo? - matches /something/foo1 and /something/foo2
	 *
	 * @param $filter string
	 * @return boolean
	 */
	public function uriMatch($filter)
	{
		if($filter == '*') {
			return true;
		}
		elseif ($filter == $this->getUri()) {
			return true;
		}
		else {
			if (DS == '\\' && version_compare(PHP_VERSION,'5.3.0','<=')) {
				$filter = preg_quote($filter,'!');
				$filter = str_replace('\*','.+',$filter);
				$filter = str_replace('\?','.{1}',$filter);
				$filter = "!{$filter}!U";
				return preg_match($filter,$this->getUri());
			}
			else {
				return fnmatch($filter,$this->getUri());
			}
		}
	}

	/**
	 * Parse the uri dividing it up into the provided $segements. If you provide the array('controller','action'), with the uri /foo/bar you will
	 * get back an associative array array('controller'=>'foo','action'=>'bar').
	 *
	 * @param $segments array
	 * @return array
	 */
	private function parseSegments(array $segments)
	{

		$uri = $this->getUriSegments();

		$uri = $this->filterBaseUri($uri,$this->_base_uri,true);
				
		if (isset($uri[0])) {
			$uri = array_reverse($uri);
			if (stristr($uri[0],'?')) {
				$spl = explode('?',$uri[0]);
				$uri[0] = $spl[0];
			}
			$uri = array_reverse($uri);
		}
				
		$out = array();
		foreach ($segments as $seg => $default)
		{
			if (is_numeric($seg)) {
				$out[$default] = null;	
			}
			else {
				$out[$seg] = $default;
			}
		}
				
		if (!count($uri)) {
			$out['uri'] = array();
			return $out;
		}
		
		if (!count($out)) {
			$out['uri'] = $uri;
			return $out;
		}		
		
		reset($out);
				
		while ($seg = array_shift($uri))
		{
			$target = key($out);

			$out[$target] = $seg;
			
			if (next($out) === false) {
				$out['uri'] = $uri;

				return $out;
				break;
			}
		}

		$out['uri'] = $uri;		
		return $out;
	}

	/**
	 * Split the given $uri into segments.
	 *
	 * @param $uri string
	 * @return array
	 */
	private function splitUriSegments($uri)
	{
		return array_merge(array_filter(explode('/',$uri)));
	}

	/**
	 * Parse
	 *
	 * @param $uri
	 * @return unknown_type
	 */
	private function parseUriParams($uri=null)
	{
		if (!$this->_parsed_segments) $this->parse();
		
		if (!isset($uri)) {
			$uri = $this->_parsed_segments['uri'];
		}
		else {
			if (!is_array($uri)) {
				$uri = $this->splitUriSegments($uri);
			}
		}
		
		$params = array();
		$key = false;
		
		while($seg = array_shift($uri))
		{
			if (substr($seg,0,1) == ':') {
				$params[] = substr($seg,1);
			}
			elseif (substr($seg,0,1) == '+') {
				$params[substr($seg,1)] = true;
			}
			elseif (substr($seg,0,1) == '-') {
				$params[substr($seg,1)] = false;
			}			
			elseif (!$key && !count($uri)) {
				$params[] = $seg;
			}
			elseif (!$key) {
				$key = $seg;
			}
			else {
				if (!isset($params[$key])) {
					$params[$key] = $seg;
				} else {
					if (!is_array($params[$key])) {
						$old = $params[$key];
						$params[$key] = array();
						$params[$key][] = $old;
					}

					if (is_array($params[$key])) {
						$params[$key][] = $seg;
					}
				}

				$key = false;
			}
		}

		$params  = array_map(array($this,'parseUriParam'),$params);

		return $params;
	}

	private function parseUriParam($param)
	{
		if (is_array($param)) {
			return array_map(array($this,'parseUriParam'),$param);
		}

		return $this->parseParamValue($param);
	}

	private function trimAndDecode($val)
	{
		return trim(urldecode($val));
	}

	private function parseParamValue($val)
	{
		$val = urldecode($val);
		
		if (stristr($val,',')) {
			$spl = array_filter(explode(',',$val));
			$val = array_map(array($this,'parseParamValue'),$spl);
			foreach ($val as $k => $v) {
				if (is_array($v) && count($v) == 1 && !isset($v[0])) {
					$val[key($v)] = current($v);
					unset($val[$k]);
				}
			}
			return $val;
		}
		
		if (stristr($val,':')) {
			$spl = explode(':',$val);
			$key = array_shift($spl);
			$spl = implode(':',$spl);
			if (stristr($spl,'|')) {
				$spl = array_filter(explode('|',$spl));
			}
			return array($key => $spl);
		}

		return $val;
	}

}
