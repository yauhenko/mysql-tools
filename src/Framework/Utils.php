<?php

namespace Framework;

class Utils {

	public static function filterArray(array &$array, array $fields) {
		foreach($array as $k => $v) {
			if(!in_array($k, $fields)) unset($array[$k]);
		}
	}
}
