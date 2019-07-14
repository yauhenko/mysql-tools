<?php

namespace Framework\DB\Errors;

/**
 * DuplicateError Error
 *
 * @package Core\DB\Errors
 */

class DuplicateError extends CommonError {

	/**
	 * @var string
	 */
	protected $entry;

	/**
	 * @var string
	 */
	protected $key;

	/**
	 * DuplicateError constructor.
	 * @param string $message
	 * @param int $code
	 * @param string|null $sql
	 */
	public function __construct(string $message, int $code, string $sql = null) {
		preg_match("/Duplicate entry '(.+)' for key '(.+)'/", $message, $m);
		$this->entry = $m[1];
		$this->key = $m[2];
		parent::__construct($message, $code, $sql);
	}

	/**
	 * @return string
	 */
	public function getEntry(): string {
		return $this->entry;
	}

	/**
	 * @return string
	 */
	public function getKey(): string {
		return $this->key;
	}

}
