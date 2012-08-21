<?php
namespace Base\Extras\Mongo;

class MongoCollection implements \Iterator,\Countable {

	private $_instance;
	private $_data;

	public function __construct(\MongoCursor $data, MongoModel $model) 
	{
		$this->_data = $data;
		$this->_instance = clone $model;
	}

	public function count() 
	{
		return $this->_data->count(true);
	}	

	public function sort($sort) 
	{
		$this->_data->sort($sort);
		return $this;
	}

	public function limit($limit) 
	{
		$this->_data->limit($limit);
		return $this;
	}	

	public function page($per_page,$num=1) 
	{
		$skip = ($num - 1) * $per_page;
		$this->_data->skip($skip);
		$this->_data->limit($per_page);
		return $this;
	}

	public function getMongoCursor() 
	{
		return $this->_data;
	}

	public function valid() 
	{
		return $this->_data->valid();
	}

	public function current() 
	{
		$row = $this->_data->current();
		$this->_instance->clearData();
		$this->_instance->setData($row);
		return $this->_instance; 
	}

	public function key() 
	{
		return $this->_data->key();
	}	

	public function next() 
	{
		return $this->_data->next();
	}

	public function rewind() 
	{
		return $this->_data->rewind();
	}

	public function first()
	{
		if (!$this->_data->hasNext()) return null;
		$this->_data->next();
		return $this->current();
	}

	public function getData(array $fields = null)
	{
		$out = [];
		foreach ($this as $field) {
			$out[] = $field->getData($fields);
		}
		return $out;
	}

}
