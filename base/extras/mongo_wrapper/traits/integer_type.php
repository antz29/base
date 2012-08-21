<?php

namespace Base\Extras\Mongo;

trait IntegerType {

	protected function formatInteger($value) {
		return (int) $value;
	}

	protected function parseInteger($value) {
		return (int) preg_replace('/[^0-9]/','',$value);
	}

}