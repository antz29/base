<?php namespace Base;

class Tapped
{
	static private $_instance;

	private $_init = false;
	private $_tmp;
	private $_paths = array();
	private $_classes = array();
	private $_types = array();
	private $_cache = false;

	private function __construct()
	{

	}
	
	private function init()
	{
		if (!extension_loaded('apc')) $this->_cache = false;
		$this->_init = true;
	}
	
	private function __clone() {}

	/**
	 * addPath
	 * 
	 * Add a path from which to autoload classes.
	 *
	 * @return Tapped
	 */
	public function addPath($path)
	{	
		$path = realpath($path);
		if (!$path) return $this;

		if (!$this->_init) $this->init();

		$this->_paths[$path] = $path;
		
		$this->_classes = array_merge($this->_classes,$this->getClasses($path));
		
		return $this;
	}

	public function getPaths()
	{
		return array_values($this->_paths);
	}
	
	/**
	 * getClassList
	 * 
	 * Return an array of all available classes.
	 * 
	 * @return array
	 */
	public function getClassList()
	{
		return array_keys($this->_classes);
	}
	
	/**
	 * setCache
	 * 
	 * Set the cache timeout in seconds.
	 *
	 * Tapped will cache the class index for this many seconds. By default this
	 * is one day or 86400 seconds.
	 *
	 * @param $timeout
	 * @return unknown_type
	 */
	public function setCache($timeout)
	{
		$this->_cache = $timeout;
		return $this;
	}

	/**
	 * Cache the given $data
	 *
	 * @param $data mixed
	 * @param $key string
	 * @return boolean
	 */
	private function setToCache($data,$key)
	{
		if (!$this->_cache) return true;
		return apc_store($key,$data,$this->_cache);		
	}

	/**
	 * Retrieve cached data
	 *
	 * @param $key string
	 * @return mixed
	 */
	private function getFromCache($key)
	{
		if (!$this->_cache) return null;
		return apc_fetch($key);
	}

	/**
	 * Return an array of classes found at a given path.
	 *
	 * @param $path
	 * @return array
	 */
	private function getClasses($path)
	{
		$cache = md5($path.__FILE__);
		$paths = $this->getFromCache($cache);
		if (!is_array($paths))
		{
			$it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($path));
			$paths = array();
			foreach ($it as $file)
			{
				if (substr(basename($file),-3) != 'php') continue;

				$classes = $this->parseFile((string) $file);
				foreach ($classes as $type => $classes)
				{
					foreach ($classes as $class)
					{
						if (!isset($paths[$class]))
						{
							$paths[$class]['type'] = $type;
							$paths[$class]['path'] = (string) $file;
						}
					}
				}
			}
			$this->setToCache($paths, $cache);
		}
		return $paths;
	}

	/**
	 * Parse a file and return any defined classes or interfaces.
	 *
	 * @param $file
	 * @return array
	 */
	private function parseFile($file)
	{
		$tokens = token_get_all(file_get_contents($file));
		$namespace = ""; 
		$classes = array();
		foreach ($tokens as $i => $token)
		{
			if ($token[0] == T_CLASS || $token[0] == T_INTERFACE || $token[0] == T_TRAIT)
			{
				$i += 2;
				$types = array(T_CLASS => 'class',T_INTERFACE => 'interface', T_TRAIT => 'trait');
				$type = isset($types[$token[0]]) ? $types[$token[0]] : 'unknown';
				$class = $tokens[$i][1];
				
				if ($namespace) {
					$class = $namespace . '\\' . $class;
				}
				
				if ($class == 'Base\\Tapped') continue;

				$classes[$type][$class] = $class;
			}
			elseif ($token[0] == T_NAMESPACE) {
				$i += 2;
				$namespace = "";
				while($tokens[$i] != ';') {

					$namespace .= $tokens[$i][1];
					$i++;
				}
				if (substr($namespace,0,1) == '\\') $namespace = substr($namespace,1);
			}
		}
		return $classes;
	}

	/**
	 * getInstance
	 * 
	 * Return the Tapped instance
	 *
	 * @return Tapped
	 */
	static public function getInstance()
	{
		if (!(self::$_instance instanceof Tapped))
		{
			self::$_instance = new Tapped();
		}
		return self::$_instance;
	}

	/**
	 * registerAutoloader
	 * 
	 * Register the 'load' method with spl_autoload_register to handle loading classes.
	 *
	 */
	public function registerAutoloader()
	{
		spl_autoload_register(array($this,'load'));
	}

	/**
	 * unregisterAutoloader
	 * 
	 * Unregister the 'load' method with spl_autoload_unregister.
	 *
	 */
	public function unregisterAutoloader()
	{
		spl_autoload_unregister(array($this,'load'));
	}

	/**
	 * load
	 * 
	 * Load the source file for the specified class.
	 *
	 * @param string $class
	 * @return bool	Will return true on success, false on failure.
	 */
	public function load($class)
	{
		if (!isset($this->_classes[$class]['path'])) return false;

		$file = $this->_classes[$class]['path'];
				
		return include $file;
	}
	
	/**
	 * exists
	 * 
	 * Return true if a class or interface is available to autoload if required.
	 *
	 * @param $class string
	 * @return boolean
	 */
	public function exists($class)
	{
		return isset($this->_classes[$class]);
	}

	/**
	 * getType
	 * 
	 * Return if a given name is a class or interface, or false if the class does not exist.
	 *
	 * @param $class string
	 * @return string
	 */
	public function getType($class)
	{
		return isset($this->_classes[$class]['type']) ? $this->_classes[$class]['type'] : $this->getTypeFromClass($class);
	}

	/**
	 * getPath
	 * 
	 * Return the current full path of the given class.
	 *
	 * @param $class
	 * @return string
	 */
	public function getPath($class)
	{
		return isset($this->_classes[$class]['path']) ? $this->_classes[$class]['path'] : false;
	}

	/**
	 *  Returns if the given name is a class or interface.
	 *
	 * @param $class
	 * @return string
	 */
	private function getTypeFromClass($class)
	{
		if (!isset($this->_classes[$class])) return false;

		if (isset($this->_classes[$class]['type'])) return $this->_classes[$class]['type'];

		if (interface_exists($class))
		{
			return $this->_classes[$class]['type'] = 'interface';
		}
		elseif (class_exists($class)) {
			return $this->_classes[$class]['type'] = 'class';
		} else {
			return false;
		}
	}
	
	public function isInterface($name)
	{
		return (isset($this->_classes[$name]) && $this->_classes[$name]['type'] == 'interface');
	}
	
	public function isClass($name)
	{
		return (isset($this->_classes[$name]) && $this->_classes[$name]['type'] == 'class');
	}
	
	public function mock($class,$deep = false)
	{
		$path = $this->getPath($class);
		$tokens = token_get_all(file_get_contents($path));
		$in_class = false;

		if ($deep) {
			foreach (Tapped::getInstance()->getParents($class) as $parent) {
				if (!class_exists($parent,false)) $this->mock($parent);			
			}
		}
		
		$cnt = count($tokens);
		for ($i=0;$i < $cnt;$i++) {
			if (!$in_class) {
				if ($tokens[$i][0] != T_CLASS) continue;	
				$i++;
				
				if ($tokens[$i][0] != T_WHITESPACE) continue;
				$i++;
				
				if ($tokens[$i][0] != T_STRING) continue;
				if ($tokens[$i][1] != $class) continue;
				
				$i++;
				$deps = "";
				for ($i=$i;$i<$cnt;$i++) {
					if ($tokens[$i] == '{') break;
					$deps .= $tokens[$i][1];
				}
				$deps = trim($deps);
				
				$eval = "class {$class} {$deps} { \n";
				
				$in_class = true;
			}
			
			if ($tokens[$i][0] != T_FUNCTION) continue;
			$i++;
			
			if ($tokens[$i][0] != T_WHITESPACE) continue;
			$i++;
			
			if ($tokens[$i][0] != T_STRING) continue;
			
			$method = $tokens[$i][1];		
			$meval = "";

			$abs = false;
			for ($s = ($i - 10);$s < $i;$s++) {
				
				switch ($tokens[$s][0]) {
					case T_ABSTRACT:
						$abs = true;
						$meval .= ' abstract';
						break;
					case T_PUBLIC:
						$meval .= ' public';
						break;
					case T_PRIVATE:
						continue(3);
						break;
					case T_PROTECTED:
						$meval .= ' protected';
						$prt = true;
						break;
					case T_STATIC:
						$meval .= ' static';
						$sta = true;
						break;
				}
			}
			
			if ($abs) {
				$eval .= "{$meval} function {$method}();\n";
				$eval = "abstract {$eval}";
			}
			else {
				$eval .= "{$meval} function {$method}() {}\n";
			}
		}
		
		$eval .= "}";	

		eval($eval);
	}
}
