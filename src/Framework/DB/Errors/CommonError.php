<?php

namespace Framework\DB\Errors;

use Error;

/**
 * Class DB CommonError
 *
 * @package Framework\DB\Errors
 */
class CommonError extends Error {

	/** @var string */
	protected $sql;

	/**
	 * Error constructor
	 * @param string $message
	 * @param int $code
	 * @param string|null $sql
	 */
	public function __construct(string $message, int $code, string $sql = null) {
		$this->sql = $sql;
		parent::__construct($message, $code);
	}

	/**
	 * @return string|null
	 */
	public function getSql(): ?string {
		return $this->sql;
	}

}
