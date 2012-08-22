<?php
namespace Base;

use Base\Config\Node;

class Base {

	private $_config;
	private $_layout;
	private $_controller;
	private $_action;
	private $_resources = array();
	private $_handled = false;
	private $_hooks = array();
	private $_active_module;

	private $_force_route = false;

	final public function __construct() {
		$this->_config = new Config\Config('config.php');
		date_default_timezone_set('Europe/London');
		$partial_path =  isset($this->_config->partials) ? $this->_config->partials : 'partials';
		Template::setPartialPath($partial_path);
		
		if ($this->_config->error_handling) {
			ini_set('display_errors',false);
			set_error_handler(array($this,'errorHandler'));
			set_exception_handler(array($this,'exceptionHandler'));
			register_shutdown_function(array($this,'shutdownHandler'));
		}
	}

	public function route($uri) {
		$this->_force_route = $uri;
	}

	public function getAppClass($ns,$name,$force_module=null) {
		
		if ($force_module) {
			$mod_ns = (string) $this->_config->modules[$force_module]['ns'];
			$class = "{$this->_config->app_namespace}\\{$mod_ns}\\{$ns}\\{$name}";
			if (Tapped::getInstance()->exists($class)) return $class;
		}
		elseif ($this->_active_module) {
			$class = "{$this->_config->app_namespace}\\{$this->_active_module['ns']}\\{$ns}\\{$name}";
			if (Tapped::getInstance()->exists($class)) return $class;
		}

		return "{$this->_config->app_namespace}\\{$ns}\\{$name}";
	}

	public function setLayout($layout) {
		$path = realpath($layout);
		if (!$path) {
			$path = realpath("{$this->_config->templates}/{$layout}.php");
			if (!$path) {
				$path = BASE_ROOT.'template'.DS.'default_templates'.DS.'layout.php';
			}
		}
		
		if (!$this->_layout) {
			$this->_layout = new Template($this);
		}

		$this->_layout->setPath($path);
	}

	public function getModel($name,$module=null) {
		$class = $this->getAppClass('Models',$name,$module);
		if (class_exists($class)) {
			return new $class($this);
		}

		throw new \Exception("Could not load model {$class}");
		return null;
	}

	private function registerHook($hook,$callback) {
		$this->_hooks[$hook][] = $callback;
	}

	private function executeHook($hook) {
		if (!isset($this->_hooks[$hook])) return;
		foreach ($this->_hooks[$hook] as $hook_function) {
			list($object,$method) = $hook_function;
			$object->$method();
		}
	}

	public function getResource($name) {
		if (isset($this->_resources[$name])) return $this->_resources[$name]->retrieve(); 

		$type = isset($config['type']) ? $config['type'] : String::camelize($name,true);

		$res = $this->getAppClass('Resources',$type);

		if (!class_exists($res)) {
			$res = '\\Base\\Resources\\'.$type;
			if (!class_exists($res)) return null;
		}

		$config = $this->_config->resources->$name;

		if ($config == null) $config = new Node(array());

		$this->_resources[$name] = new $res($config,$this);

		if (method_exists($this->_resources[$name],'preResponse')) {
			$this->registerHook('preResponse',array($this->_resources[$name],'preResponse'));
		}

		return $this->_resources[$name]->retrieve();
	}

	/**
	 *
	 * @return Config
	 */
	public function getConfig() {
		return $this->_config;
	}

	public function run($force_uri = null) {
		$this->_force_route = false;

		if (php_sapi_name() == 'cli') return array();

		if ($this->_config->base) {
			Request::init($this->_config->base);
		}

		$request = $this->getRequest();

		if ($force_uri) {
			$request->setUri($force_uri);
		}

		$uri = $request->getUri();

		$this->getResponse()->setHeader('X-Base-Uri',$uri);

		if ($this->_config->routes) {
			foreach ($this->_config->routes->getData() as $route => $to) {
				if ($uri == $route) {
					$this->getResponse()->setHeader('X-Route-Match',$route);
					$this->getResponse()->setHeader('X-Route-Uri',$to);	
					$request->setUri($to);
					break;
				}
				elseif (fnmatch($route,$uri)) {
					if (!stristr($to, '*') && !stristr($to, '{')) {
						$this->getResponse()->setHeader('X-Route-Match',$route);
						$this->getResponse()->setHeader('X-Route-Uri',$to);	
						$request->setUri($to);
						break;
					}

					$this->getResponse()->setHeader('X-Route-Match',$route);
					$this->getResponse()->setHeader('X-Route-To-Pattern',$to);

					$route = str_replace('/*/', '/%[^/]/',$route);
					$route = str_replace('/*', '/%s', $route);
					
					$new_match = stristr($to,'{');

					$out = sscanf($uri, $route);
					$elem = 1;
					foreach ($out as $match) {
						if ($new_match) {
							$to = preg_replace('!\{'. $elem .'\}!', $match, $to, 1);
						}
						else {
							$to = preg_replace('!\*!', $match, $to, 1);
						}
						$elem++;
					}

					$this->getResponse()->setHeader('X-Route-Uri',$to);	
					$request->setUri($to);
					break;
				}
			}
		}

		if ($this->_config->modules) {
			$uri = $request->getUri();
			foreach ($this->_config->modules->getData() as $name => $module) {
				if ($uri == "/{$name}" || fnmatch("/{$name}/*", $uri)) {
					$this->getResponse()->setHeader('X-Active-Module',$name);
					$this->_active_module = $module;
					$this->_active_module['name'] = $name;
					$this->_config->setModule($name);
				}
			}
		}

		$request->clearSegmentIdentifiers();

		if ($this->_active_module) {
			$request->addSegmentIdentifier('module','module');
		}

		$request->addSegmentIdentifier('controller','index');
		$request->addSegmentIdentifier('action','index');

		$uri = $request->getSegmentIdentifiers();

		$this->_controller = $uri['controller'];
		$this->_action = $uri['action'];

		if (function_exists('newrelic_name_transaction'))  {
			if ($this->_active_module) {
				newrelic_name_transaction("{$this->_active_module['name']}/{$this->_controller}/{$this->_action}");
			}
			else {
				newrelic_name_transaction("{$this->_controller}/{$this->_action}");
			}
		}

		$controller = String::camelize($this->_controller,true);
		$action = String::camelize($this->_action . '_action');

		$this->setLayout($this->_config->layout);
		$this->_layout->set('controller',$this->_controller);
		$this->_layout->set('action',$this->_action);

		if ($this->_active_module) {
			$this->_layout->set('module',$this->_active_module['name']);			
		}

		$this->loadController($controller,$action,$uri['uri']);

		if ($this->_force_route) {
			return $this->run($this->_force_route);
		}

		$this->sendResponse(200,$this->_layout->render());
	}

	public function getController() {
		return $this->_controller;
	}

	public function getAction() {
		return $this->_action;
	}

	public function getModule() {
		return $this->_active_module ? $this->_active_module['name'] : null;
	}

	/**
	 * @return Session;
	 */
	public function getSession() {
		return Session::getInstance();
	}

	/**
	 * @return Base\Request;
	 */
	public function getRequest() {
		return Request::getInstance();
	}

	/**
	 * @return Base\Environment;
	 */
	public function getEnvironment() {
		return Environment::getInstance();
	}

	/**
	 * @return Base\Response;
	 */
	public function getResponse() {
		return Response::getInstance();
	}

	private function initController($controller) {
		$controller = $this->getAppClass('Controllers',$controller);
		if (class_exists($controller)) return $controller;

		$base = $this->getAppClass('Controllers','Base');
		if (class_exists($base)) return $base;

		return false;
	}

	private function runController($controller,$action,$template,$args) {
		$c = new $controller($this,$this->_layout,$template);
		if (!($c instanceof Controller)) return $this->error(500,'Invalid controller type'); 

		$this->getResponse()->setHeader('X-Controller',$controller);
		
		if (method_exists($c,'preAction')) {
			$c->preAction($action,$args);
		}

		$request = Request::getInstance();

		$method_action = $action . ucfirst($request->getMethod());
			
		$has_method_action = method_exists($c,$method_action);

		$this->getResponse()->setHeader('X-Method-Action',$method_action);

		$this->getResponse()->setHeader('X-Has-Method-Action',($has_method_action ? 'yes' : 'no'));

		if ($has_method_action) {
			$this->getResponse()->setHeader('X-Action',$method_action);
			$data = $c->$method_action($args);
		}
		elseif (!$has_method_action && $this->_config->force_method_actions) {
			$this->error(405,'Method not allowed');
		}
		else {
			if (method_exists($c,$action)) {
				$this->getResponse()->setHeader('X-Action',$action);
				$data = $c->$action($args);
			}
		}

		if (method_exists($c,'postAction')) {
			$c->postAction($action);
		}
	}

	private function loadController($controller,$action,$args) {

		$request = Request::getInstance();

		$template = new Template();
		$template->setParent($this->_layout);
		
		if ($this->_active_module) {
			$path = "{$this->_active_module['name']}/{$this->_controller}/{$this->_action}.php";
		}
		else {
			$path = "{$this->_controller}/{$this->_action}.php";
		}

		$template->setPath($path);

		if ($controller = $this->initController($controller)) {
			$this->runController($controller, $action, $template,$args);
			if ($this->_force_route) return;
		}
		else {
			if (!$template->getPath()) return $this->error(404,'Page not found.');
		}

		$this->_layout->set('content',$template->render());
	}

	public function error($code,$message,$description = null,$no_template = false) {
		$type = $this->getResponse()->getHeader('content-type');
		$type = explode(';',$type);

		if ($type[0] == 'application/json') {
			$out = array(
				'status' => 'error',
				'message' => $message,
				'description' => $description
			);

			return $this->sendResponse($code,json_encode($out));
		}

		$template = new Template();
		$template->setParent($this->_layout);
		$path = "error_{$code}.php";

		if (!$template->setPath($path)) $path = "error.php";

		if ($no_template || !$template->setPath($path)) {
			$this->getResponse()->setContent("<title>Error {$code}: {$message}</title><h1>Error {$code}</h1><p>{$message}</p><p>{$description}</p>");
			return $this->sendResponse($code);
		}

		$template->set('code',$code);
		$template->set('message',$message);
		$template->set('description',$description);

		$this->_layout->set('error',true);
		$this->_layout->set('title',"Error {$message}");
		$this->_layout->set('content',$template->render());

		return $this->sendResponse($code,$this->_layout->render());
	}

	public function sendResponse($status = null,$content = null) {
		$r = Response::getInstance();
		if (isset($status)) $r->setStatus($status);
		if (isset($content)) $r->setContent($content);
		$this->executeHook('preResponse');
		$r->sendStatus();
		$r->sendHeaders();
		$r->sendContent();
		die();
	}

	public function exceptionHandler($e) {
		$this->errorHandler(0,$e->getMessage(),$e->getFile(),$e->getLine());
	}

	public function errorHandler($code,$error,$file,$line) {
		if ($code != 0 && $code != E_ERROR) return; 
		$this->_handled = true;
		
		$no_template = false;
		if ($file == $this->_layout->getPath()) $no_template = true;

		$description = $this->_config->dev_mode ? "{$error} on line {$line} in file {$file}." : 'Really sorry, but something went pop! :(';
		$this->error(500,'Server error',$description,$no_template);
	}

	public function shutdownHandler() {
		if ($this->_handled) return;
		$error = error_get_last();
		if (!$error) return;
		$this->errorHandler($error['type'],'Fatal error: '. $error['message'],$error['file'],$error['line']);
	}
}
