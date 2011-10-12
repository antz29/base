<?php
namespace Base\Extras\Mongo;

use \Base\String;

abstract class MongoModel extends \Base\Model {
	
	private $_coll;
	private $_data = array();
	private $_fields = array();

	public function hasField($name) {
		return isset($this->_fields[$name]);
	}

	public function remove() {
		try {
			return $this->_coll->remove(array("_id" => $this->getId()));
		}
		catch (Exception $e) {
			return false;
		}
	}

	protected function addField($name,$type='string',$default=null) {
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

		$array = stristr($type,'[]') ? true : false;
		$type = str_replace('[]','',$type);

		$this->_fields[$name] = array(
			'type' => $type,
			'array' => $array,
			'default' => $default,
			'args' => isset($args) ? $args : array(),
			'alias' => $alias
		);
	}

	public function getFields() {
		return array_keys($this->_fields);
	}

	public function getFieldDef($name) {
		return isset($this->_fields[$name]) ? $this->_fields[$name] : array('type' => 'undefined');
	}

	protected function parseBoolean($value) {
		switch ($value) {
			case 'yes':
				return true;
				break;
			case 'no':
				return false;
				break;
			default:
				return ($value ? true : false);
				break;
		}
	}

	protected function formatDate($name,$format = null) {
		if (isset($format)) {
			return date($format,$this->get($name));
		}
		return $this->get($name);
	}
	
	protected function setCollection($coll) {;
		$this->_coll = $this->getBase()->getResource('mongo')->selectCollection($coll);
	}

	protected function setIndex($keys,$options) {
		$this->_coll->ensureIndex($keys,$options);
	}
	
	public function generateId($source = null,$length = 10) {
		$source = isset($source) ? $source : microtime().rand(0,10000);
		return strtoupper(substr(sha1($source),0,$length));
	}

	public final function getData(array $fields = null) {
		$out['id'] = $this->getId();
		$fields = isset($fields) ? $fields : $this->getFields();
		foreach ($fields as $field) {
			$out[$field] = $this->getValue($field,$this->getFieldDef($field),array());
		}
		return $out;
	}
	
	public final function setData(array $data) {
		foreach ($data as $key => $value) {
			$this->_data[$key] = $value;
		}
	}
	
	protected function get($name) {
		return isset($this->_data[$name]) ? $this->_data[$name] : null;
	}
	
	protected function set($name,$value) {
		$this->_data[$name] = $value;
		return true;
	}
	
	public function getId() {
		return $this->get('_id');	
	}
	
	public function getCreationDate($format = null) {
		return $this->formatDate('creation_date',$format);
	}
	
	public function loadFromId($id) {
		return $this->find(array('_id' => $id));
	}
	
	public function listAll() {
		return $this->all();
	}	
	
	public function save() {
		$new = false;
		if (!$this->get('_id')) {
			$new = true;
			$this->set('_id', $this->generateId());	

			foreach ($this->_fields as $name => $fdef) {
				if ($this->get($name)) continue;
				if (isset($fdef['default'])) {
					$this->set($name,$fdef['default']);
				}
				if (isset($fdef['array']) && $fdef['array']) {
					$this->set($name,array());
				}
			}
		}
			
		if (!$this->get('creation_date')) {
			$this->set('creation_date',time());
		}
	
		if (!$this->preUpdate($new)) return false;

		try {
			$this->_coll->save($this->_data);
			$this->postUpdate($new);
			return true;
		}
		catch (Exception $e) {
			throw $e;
			return false;
		}
	}
	
	final protected function find(array $criteria) {
		$data = $this->_coll->findOne($criteria);
		if ($data == null) return false;
		$this->_data = $data;
		return true;
	}
	
	final public function search(array $criteria) {
		$data = $this->_coll->find($criteria);
		return new MongoCollection($data, $this);
	}
	
	final public function all() {
		$data = $this->_coll->find();
		return new MongoCollection($data, $this);
	}

	protected function formatModel($value,array $args) {
		$type = array_shift($args);
		$module = array_shift($args);

		$field = array_shift($args);
		$field = $field ? $field : 'id';

		if (!is_array($value)) {
			$value = array($field => $value);
		}
		
		if (!isset($value[$field])) return null;

		$sfld = ($field == 'id') ? '_id' : $field;	
	
		$search = array($sfld => $value[$field]);

		$m = $this->getBase()->getModel($type,$module);

		if (!$m->find($search)) return null;

		return $m;		
	}

 	protected function formatModelData($value,array $args) {
		$type = array_shift($args);
		$module = array_shift($args);

		$fields = $args;
		
		if (!is_array($value)) {
			$value = array('id' => $value);
		}

		if (!isset($value['id'])) return $value;

		$m = $this->getBase()->getModel($type,$module);
		if (!$m->loadFromId($value['id'])) return $value;
		return $value + $m->getData($fields);
	}

	public function parseValue($value,$name,array $def,array $args = array()) {
		$type = $def['type'];
		$parser = 'parse' . $type;
		if (!method_exists($this,$parser)) return $value;
		
		return $this->$parser($value,$args);
	}

	private function parseList(array $values,$name,array $def,array $args = array()) {
		$that = $this;
		$values = array_map(function($value) use ($name,$def,$args,$that) {
			return $that->parseValue($value,$name,$def,$args);
		},$values);
		return array_filter($values);
	}

	public function parseInteger($value) {
		return (int) preg_replace('/[^0-9]/','',$value);
	}

	public function formatValue($value, $name,array $def = array(),array $args = array()) {
		$type = $def['type'];

		$name = isset($def['alias']) ? $def['alias'] : $name;

		$formatter = 'format' . $type;
		if (!method_exists($this,$formatter)) return $value;
		
		$args = array_merge($args,$def['args']);
		return $this->$formatter($value,$args);
	}
	
	private function formatList(array $values,$name,array $def = array(),array $args = array()) {
		$that = $this;
		$values = array_map(function($value) use ($name,$def,$args,$that) {
			return $that->formatValue($value,$name,$def,$args);
		},$values);
		return array_filter($values);
	}

	private function lookupId(array $list,$id,&$key = null) {
	 	$list = array_filter($list,function($val) use ($id) {
			if (!isset($val['id'])) return false;
			return ($val['id'] === $id);
		});
		$keys = array_keys($list);
		$key = array_shift($keys);
		
		return isset($list[$key]) ? $list[$key] : null;
	}

	private function getValue($name,$def,$args) {
		$name = isset($def['alias']) ? $def['alias'] : $name;
			
		$value = isset($this->_data[$name]) ? $this->_data[$name] : null;
		
		if (isset($def['array']) && $def['array']) {
			if (!isset($value)) return array();
			if (!is_array($value)) return $value;
			if (!count($value)) return array();
			if (!isset($value[0])) return $value;

			if (isset($args[0])) {
				$value = $this->lookupId($value,$args[0],$ref);
				array_shift($args);
				return isset($value) ? $this->formatValue($value,$name,$def,$args) : null;
			}

			return $this->formatList($value,$name,$def,$args);
		}
		else {
			return $this->formatValue($value,$name,$def,$args);
		}
	}

	private function setValue($value,$name,$def,$args) {
		$name = isset($def['alias']) ? $def['alias'] : $name;
			
		if (isset($def['array']) && $def['array']) {
			if (isset($args[0])) {
				$current = isset($this->_data[$name]) ? $this->_data[$name] : null;
				if (!isset($current)) return false;

				$key = null;
				$this->lookupId($current,$args[0],$key);
				
				if (!isset($key)) return false;
				$id = array_shift($args);
				
				$value['id'] = $id;

				$current[$key] = $this->parseValue($value,$name,$def,$args);
				return $this->set($name,$current);
			}

			if ($value == 'null') $value = array();
			if (!is_array($value)) return false;
			if (!isset($value[0]) && count($value)) return false;

			$that = $this;
			$value = array_map(function($val) use ($that) {
				if (!isset($val['id'])) {
					$val['id'] = $that->generateId();
				}
				return $val;
			},$value);

			return $this->set($name,$this->parseList($value,$name,$def,$args));
		}
		else {
			return $this->set($name,$this->parseValue($value,$name,$def,$args));
		}
		
	}
	
	private function addValue($value,$name,$def,$args) {
		$name = isset($def['alias']) ? $def['alias'] : $name;

		if (!$def['array']) return false;

		$current = isset($this->_data[$name]) ? $this->_data[$name] : array();
		
		$value = $this->parseValue($value,$name,$def,$args);
		$value['id'] = $this->generateId();

		$current[] = $value;

		$set = $this->set($name,$current);

		return  $set ? $value['id'] : false;
	}

	private function deleteValue($name,$def,$args) {
		$name = isset($def['alias']) ? $def['alias'] : $name;
	
		if (!$def['array'] || !isset($args[0])) return false;
		
		$current = isset($this->_data[$name]) ? $this->_data[$name] : null;
		if (!isset($current)) return false;

		$this->lookupId($current,$args[0],$key);
		if (!isset($key)) return false;
		
		unset($current[$key]);
		return $this->set($name,$current);
	}

	public function __call($name,$args) {
		$parse = sscanf($name,'%[a-z]%s');
		$method = $parse[0];
		$name = String::underscore($parse[1]);
		
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
			case 'add':
				$value = array_shift($args);
				return $this->addValue($value,$name,$def,$args);
				break;
			case 'delete':
				return $this->deleteValue($name,$def,$args);
				break;
			case 'load':
				if (!isset($args[0])) throw new \Exception('Nothing to search on.');
				$value = array_shift($args);
				if ($name == 'id') $name = '_id';
				return $this->find(array($name => $value));
				break;
			case 'search':
				if (!isset($args[0])) throw new \Exception('Nothing to search on.');
				$value = array_shift($args);
				if ($name == 'id') $name = '_id';
				return $this->search(array($name => $value));
				break;
			default:
				throw new \Exception("Method not defined. {$method} {$name}");
				break;
				
		}
	}

	public function preUpdate($new = false) { return true; }
	public function postUpdate($new = false) {}
}
