<?php

namespace Framework\DB\Pagination;

class PagedData {

	/** @var Meta */
	public $meta;

	/** @var array */
	public $data = [];

	public static function create(array $meta, array $data) {
		$self = new self;
		$self->meta = Meta::create($meta);
		$self->data = $data;
		return $self;
	}

}
