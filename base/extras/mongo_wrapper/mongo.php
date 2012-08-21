<?php
namespace Base\Extras\Mongo;

use \Base\String;

abstract class MongoModel extends \Base\Model {
	
	use \Base\Extras\Hooks\HookSupport;

	private $_coll;
	private $_data = array();
	private $_fields = array();
	private $_proxy = false;
	private $_proxy_cache = false;
	private $_proxied_fields = [];

	// MODELS API

	final protected function getProxiedFields() 
	{
		return array_keys($this->_proxied_fields);
	}

	final protected function addProxiedFields(array $fields)
	{
		foreach ($fields as $field) {
			$this->_proxied_fields[$field] = $field;
		}
	}

	final protected function setProxy(\Closure $func) 
	{
		$this->_proxy = $func->bindTo($this);
	}

	final protected function copyProxiedDataToModel(MongoModel $target,$extra = [])
	{
		$data = $this->getData($this->getProxiedFields());
		unset($data['_id']);
		foreach ($extra as $k => $v) {
			$data[$k] = $v;
		}
		$target->setData($data);
	}

	final protected function addField($name,$type='string',$default=null) {
		$alias = null;

		if (stristr($name,':')) {
			$spl = explode(':',$name);
			$name = $spl[0];
			$alias = $spl[1];
		}

		if (stristr($type,':')) {
			$spl = explode(':',$type);
			$type = $spl[0];
			$args = explode(',',$spl[1]);
		}

		$type = str_replace('[]','',$type);

		$this->_fields[$name] = array(
			'type' => $type,
			'default' => $default,
			'args' => isset($args) ? $args : array(),
			'alias' => $alias
		);
	}

	final protected function setCollection($coll) {;
		$this->_coll = $this->getBase()->getResource('mongo')->selectCollection($coll);
	}

	final protected function setIndex($keys,array $options = []) {
		$this->_coll->ensureIndex($keys,$options);
	}
	
	final protected function get($name) {
		return isset($this->_data[$name]) ? $this->_data[$name] : null;
	}
	
	final protected function set($name,$value) {
		$this->_data[$name] = $value;
		return true;
	}
	
	final protected function find(array $criteria) {
		$data = $this->_coll->findOne($criteria);
		if ($data == null) return false;
		$this->_data = $data;
		return true;
	}

	// PUBLIC INTERFACE

	final public function hasField($name) {
		return isset($this->_fields[$name]);
	}

	final public function getCollection()
	{
		return $this->_coll;
	}

	final public function search(array $criteria) 
	{
		$args = [];
		$args[0] = &$criteria;

		$out = null;

		$args[1] =  &$out;

		$this->callHook('pre_search',$args);

		if (isset($out)) return $out;

		$data = $this->_coll->find($args[0]);
		$out = new MongoCollection($data, $this);

		$args[1] = &$out;

		$this->callHook('post_search',$args);

		return $args[1];
	}
	
	final public function all() {
		$data = $this->_coll->find();
		return new MongoCollection($data, $this);
	}

	final public function parseValue($value,$name,array $def,array $args = array()) {
		$type = $def['type'];
		$parser = 'parse' . $type;

		if (!method_exists($this,$parser)) return $value;
		return $this->$parser($value,$args);
	}

	final public function formatValue($value, $name,array $def = array(),array $args = array()) {
		$type = $def['type'];

		$name = isset($def['alias']) ? $def['alias'] : $name;

		$formatter = 'format' . $type;
		if (!method_exists($this,$formatter)) return $value;
		
		$args = array_merge($args,$def['args']);
		return $this->$formatter($value,$args);
	}

	public function __call($name,$args) {
		$dname = $name;
		$parse = sscanf($name,'%[a-z]%s');
		$method = $parse[0];
		$name = String::underscore($parse[1]);
		
		if ($name == 'id') $name = '_id';

		$def = $this->getFieldDef($name);

		if (!isset($def)) throw new \Exception("Method not defined. {$method} {$name}");
		
		switch ($method) {
			case 'set':
				$value = array_shift($args);
				return $this->setValue($value,$name,$def,$args);
			case 'get':
				return $this->getValue($name,$def,$args);
				break;
			case 'is':
			case 'has':
				return $this->getValue($name,$def,$args) ? true : false;
				break;
			case 'load':
				if (!isset($args[0])) throw new \Exception('Nothing to search on.');
				$value = array_shift($args);
				if (is_string($value) && $name == '_id') $value = new \MongoId($value);
				return $this->find(array($name => $value));
				break;
			case 'search':
				if (!isset($args[0])) throw new \Exception('Nothing to search on.');
				$value = array_shift($args);
				return $this->search(array($name => $value));
				break;
			default:
				throw new \Exception("Method not defined. Method:{$dname}");
				break;
		}
	}

	final public function save($no_hooks=false,$no_proxy=false) {
		
		$proxy = $no_proxy ? false : $this->getProxy();

		$new = false;
		if (!isset($this->_data['_id'])) {
			$new = true;

			foreach ($this->_fields as $name => $fdef) {
				if ($this->get($name)) continue;
				if (isset($fdef['default'])) {
					$this->set($name,$fdef['default']);	
				}
			}
		}

		foreach ($this->_data as $field => $value) {
			if (isset($this->_proxied_fields[$field]) && $proxy) unset($this->_data[$field]);
		}

		if (!$no_hooks && !$this->preUpdate($new)) return false;

		try {
			$this->_coll->save($this->_data);
			if (!$no_hooks) $this->postUpdate($new);

			if ($proxy) $proxy->save($no_hooks);

			return true;
		}
		catch (Exception $e) {
			throw $e;
			return false;
		}
	}

	final public function getData(array $fields = null) {
		$out['_id'] = $this->getId();
		$fields = isset($fields) ? $fields : $this->getFields();
		foreach ($fields as $field) {
			$out[$field] = $this->getValue($field,$this->getFieldDef($field),array());
		}
		return $out;
	}

	final public function clearData()
	{
		$this->_data = [];
	}
	
	final public function setData(array $data) {
		foreach ($data as $key => $value) {
			$this->_data[$key] = $value;
		}
	}

	final public function remove() {
		try {
			return $this->_coll->remove(array("_id" => $this->getId()));
		}
		catch (Exception $e) {
			return false;
		}
	}

	final public function getId() {
		return $this->_data['_id'];
	}

	public function getFields() {
		return array_keys($this->_fields);
	}

	public function getFieldDef($name) {
		return isset($this->_fields[$name]) ? $this->_fields[$name] : array('type' => 'undefined');
	}

	// HOOKS

	public function preUpdate($new = false) { return true; }
	public function postUpdate($new = false) {}

	// INTERNAL

	private function getValue($name,$def,$args) {
		if (isset($this->_proxied_fields[$name]) && $this->getProxy()) return $this->getProxy()->getValue($name,$def,$args);
		$name = isset($def['alias']) ? $def['alias'] : $name;
		$value = isset($this->_data[$name]) ? $this->_data[$name] : null;
		
		return $this->formatValue($value,$name,$def,$args);
	}

	private function setValue($value,$name,$def,$args) {
		if (isset($this->_proxied_fields[$name]) && $this->getProxy()) return $this->getProxy()->setValue($value,$name,$def,$args);
		$name = isset($def['alias']) ? $def['alias'] : $name;
		return $this->set($name,$this->parseValue($value,$name,$def,$args));
	}

	private function getProxy()
	{
		if ($this->_proxy_cache) return $this->_proxy_cache;
		if (!$this->_proxy) return false;
		$func = $this->_proxy;
		$this->_proxy_cache = $func($this->_data);
		return $this->_proxy_cache;
	}
}
