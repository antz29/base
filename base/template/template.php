<?php
namespace Base;

class Template {

	private $_path;
	private $_data = array();
	private $_parent;
	private $_root;
	private $_base;
	
	static private $_partials;
	
	static public function setPartialPath($path) {
		self::$_partials = realpath($path);
	}
	
	public function __construct(Base $base = null) {
		if ($base == null) return;
		$this->_base = $base;
	}
	
	public function getBase() {
		if (isset($this->_parent)) {
			return $this->_parent->getBase();
		}
		
		return $this->_base; 
	}
	
	public function setPath($file) 
	{			
		if (isset($this->_parent)) {
			$path = realpath($this->_parent->getRoot() . '/' . $file);	
			if ($path) {
				$this->_path = $path;
				return true;
			}
			else {
				$path = realpath($file);
				if ($path) {
					$this->_path = $path;
					return true;
				}
			}
		}
		else {
			$this->_path = realpath($file);
			if (!$this->_path) return false;
			
			$this->_root = dirname($this->_path);
			return true;
		}
		return false;
	}
	
	public function getPath()
	{
		return $this->_path;
	}
	
	private function getRoot()
	{
		return $this->_root;
	}
	
	public function dollarG($path) {
			if (!stristr($path, 'C:')) {
				return $this->get($path);
			}

			$path = explode(':',$path);
			$path = array_pop($path);
			$path = explode('.',$path);
			
			$elem = array_shift($path);
			$node = $this->getConfig()->get($elem);
			
			while (count($path)) {
				$elem = array_shift($path);
				if ($node == null) return null;
				$node = $node->get($elem);
			}
			
			return $node;
	}

	public function dollarS($name,$value) {
		return $this->set($name,$value);
	}

	public function dollarSP($name,$value) {
		return $this->getParent()->set($name,$value);
	}
	
	public function dollarR($name) {
		$r = $this->getBase()->getResource($name);
		if (!$r->isExposed()) return null;
		return $r;
	}
	
	public function getPlugin($name) {
		$class = $this->getBase()->getAppClass('TemplatePlugins', $name);
		if (!class_exists($class)) return null;
		return new $class($this);
	}

	public function render() 
	{	
		$that = $this;

		$G = function($path) use ($that) {
			return $that->dollarG($path);
		};
		
		$P = function($path,$default=null) use ($that) {
			echo $that->dollarG($path);
		};

		$S = function($name,$value) use ($that) {
			$that->dollarS($name,$value);
		};
		
		$SP = function($name,$value) use ($that) {
			$that->dollarSP($name,$value);
		};
		
		$PT = function($name, $args = array()) use ($that) {
			if ($args instanceof \Iterator) {
				$class = null;
				foreach ($args as $arg) {
					if (!isset($class)) {
						$spl = explode('\\',get_class($arg));
						$class = array_pop($spl);
					}
					echo $that->load(':' . $name, array($class => $arg));		
				}
			}
			else {
				echo $that->load(':' . $name, $args);
			}
		};
	
		$D = function($name) use ($that) {
			return $that->getBase()->getRequest()->getData($name);
		};
	
		$R = function($name) use ($that) {
			return $that->dollarR($name);
		};
		
		$URL = function($uri,$scheme='http') use ($that) {
			if (substr($uri,0,1) != '/') $uri = '/'.$uri; 
			return "{$scheme}://{$_SERVER['HTTP_HOST']}{$uri}";
		};
		
		$PG = function($name, $args = array()) use ($that) {
			return $that->getPlugin($name)->exec($args);
		};
		
		ob_start();
		if (file_exists($this->_path)) include($this->_path);
		return ob_get_clean();
	}
	
	public function setParent(Template $template)
	{
		$this->_parent = $template;
	}
	
	public function getParent()
	{
		return $this->_parent;
	}
	
	public function getData()
	{
		return $this->_data;
	}
	
	public function setData(array $data)
	{
		$this->_data = $data;
	}
	
	public function load($template,array $data) 
	{
		$t = new Template();
		$t->setParent($this);
		
		if (substr($template,0,1) == ':') {	
			$p = self::$_partials .'/'. substr($template,1) . '.php';
			$t->setPath($p);
		}
		else {
			$t->setPath($template);
		}
		
		foreach ($data as $k => $v) {
			if (is_array($v)) {
				$t->_data[$k] = $v;
			}
			else {
				$t->_data[$k] = array($v);
			}
		}
		
		return $t->render();
	}
	
	public function set($name, $value) 
	{
		$this->_data[$name] = array($value);
	}
	
	public function add($name, $value) 
	{
		if (!isset($this->_data[$name])) {
			$this->_data[$name] = array();
		}
		$this->_data[$name] += $value;
	}
	
	public function get($name) {
		if (isset($this->_data[$name])) {
			if (count($this->_data[$name]) > 1) {
				return $this->_data[$name];
			}
			else if (isset($this->_data[$name][0])) {
				return $this->_data[$name][0];
			}
		}
		
		if ($this->_parent != null) {
			return $this->_parent->get($name);
		}
		else {
			return null;
		}
	}
	
	public function getValue($name) 
	{
		return isset($this->_data[$name][0]) ?  $this->_data[$name][0] : null;
	}
	
	public function getList($name) 
	{
		return isset($this->_data[$name]) ?  $this->_data[$name] : null;
	}
	
	public function __get($name) {
		return $this->get($name);
	}
	
	public function __set($name,$value) {
		return $this->set($name, $value);
	}

	public function getConfig() {
		if ($this->_parent != null) return $this->_parent->getConfig();
		return $this->_base->getConfig();
	}
}
