<?php

namespace Base\Extras\Mongo;

trait BooleanType {

	protected function formatDate($value,$format = null) {
		$date = new DateTime("@P{$value->sec}");
		$format = $format ? $format : DateTime::W3C;
		return $date->format($format);
	}

	protected function parseDate($value,$format = null) {
		if (isset($format)) {
			$date = DateTime::createFromFormat($format, $value)->getTimestamp();
		}
		elseif (!is_int($value)) {
			$date = strtotime($value);
		}

		return new \MongoDate($date);
	}

}