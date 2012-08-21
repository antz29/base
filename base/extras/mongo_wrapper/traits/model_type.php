<?php

namespace Base\Extras\Mongo;

trait ModelType {

	protected function formatModel($value,array $args) {
		$type = array_shift($args);
		$module = array_shift($args);

		$field = array_shift($args);
		$field = $field ? $field : '_id';

		if (!is_array($value)) {
			$value = array($field => $value);
		}
		
		if (!isset($value[$field])) return null;

		$sfld = $field;	
	
		$search = array($sfld => $value[$field]);

		$m = $this->getBase()->getModel($type,$module);

		if (!$m->find($search)) return null;

		return $m;
	}

	protected function parseModel($value,array $args) {
		return $value;	
	}

}