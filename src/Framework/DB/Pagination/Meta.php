<?php

namespace Framework\DB\Pagination;

class Meta {

	public $page = 1;
	public $pages = 0;
	public $limit = 10;
	public $count = 0;

	public static function create(array $meta) {
		$self = new self;
		foreach ($meta as $k => $v) {
			$self->$k = $v;
		}
		return $self;
	}

}
