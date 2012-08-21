<?php

namespace Base\Extras\Mongo;

trait ModelDataType {

 	protected function formatModelData($value,array $args) {
		$type = array_shift($args);
		$module = array_shift($args);

		$fields = $args;
		
		if (!is_array($value)) {
			$value = array('_id' => $value);
		}

		if (!isset($value['_id'])) return $value;

		$m = $this->getBase()->getModel($type,$module);
		if (!$m->loadFromId($value['_id'])) return $value;
		return $value + $m->getData($fields);
	}

	protected function parseModelData($value,array $args) {
		return $value;	
	}

}