<?php

namespace Base;

class String {

	/**
	 * camelize
	 * 
	 * Camelize the provided underscored $word. Optionally specifying the boolean
	 * $upper to specify if the first character should be capitalised.
	 *
	 * @param $word string
	 * @param $upper boolean
	 * @return string
	 */
	static public function camelize($word,$upper=false)
 	{
		$word = strtolower($word);

		$preg = array(
			'/(.)(^|_|-)+(.)/e' => "strtolower('\\1').strtoupper('\\3')"
		);

		$word = preg_replace(array_keys($preg), array_values($preg), $word);
		if ($upper) return ucfirst($word);
		return $word;
	}

	/**
	 * underscore
	 * 
	 * Underscore the provided camelized word.
	 *
	 * @param $word string
	 * @return string
	 */
	static public function underscore($word)
 	{
 		$let = strtolower(substr($word,0,1));
 		$word = $let.substr($word,1);
		$word = preg_replace('/([A-Z])/', '_\\1', $word);
		return strtolower($word);
	}

	/**
	 * clean
	 * 
	 * Clean the provided $string of all characters not specified in the $allowed range.
	 * The range is a preg range and defaults to 'a-z:\+A-Z0-9_\-'
	 *
	 * @param $string string
	 * @param $allowed string
	 * @return string
	 */
	static public function clean($string,$allowed='a-z:\+A-Z0-9_\-') {
		return preg_replace('/[^'.$allowed.']/','',$string);
	}

}

