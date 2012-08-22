<?php

namespace Base\Extras\Mongo;

trait BooleanType {

	protected function parseBoolean($value) {
		switch ($value) {
			case 'on':
			case 'true':
			case 'yes':
				return true;
				break;
			case 'off':
			case 'false':
			case 'no':
				return false;
				break;
			default:
				return ($value ? true : false);
				break;
		}
	}

	protected function formatBoolean($value) {
		return $value ? true : false;
	}

}