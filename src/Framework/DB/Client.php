<?php declare(ticks=1);

namespace Framework\DB;

use DateTime;
use Framework\DB\Errors\{CommonError, ConstraintError, DuplicateError};
use mysqli;

/**
 * MySQL Client
 *
 * @package Core\DB
 */
class Client {

	/**
	 * @var mysqli
	 */
	protected $driver;

	/** @var int Verbose level */
	protected $verbose = 0;

	/** @var int Transaction depth */
	protected $depth = 0;
	/**
	 * @var string
	 */
	protected $host;
	/**
	 * @var string
	 */
	protected $user;
	/**
	 * @var string
	 */
	protected $pass;
	/**
	 * @var string
	 */
	protected $name;
	/**
	 * @var int
	 */
	protected $port;

	public static function parseUri(string $uri): array {
		$uri = preg_replace('/^mysql\:\/\//i', '', $uri);
		$credentials = explode('@', $uri, 2);
		[$user, $pass] = explode(':', $credentials[0]);
		$credentials[1] = explode('/', $credentials[1]);
		$name = $credentials[1][1];
		[$host, $port] = explode(':', $credentials[1][0]);
		return [
			'host' => $host ?: 'localhost',
			'port' => (int)$port ?: 3306,
			'user' => $user ?: 'root',
			'pass' => $pass ?: '',
			'name' => $name ?: '',
		];
	}

	/**
	 * Uri format: mysql://user:pass@host:port/database
	 * @param string $uri
	 * @return Client
	 */
	public static function createFromUri(string $uri): self {
		$uri = self::parseUri($uri);
		return new self($uri['host'], $uri['user'], $uri['pass'], $uri['name'], $uri['port']);
	}

	/**
     * Client constructor
     *
     * @param string $host
     * @param string $user
     * @param string $pass
     * @param string $name
     * @param int $port
     * @throws ConstraintError
     * @throws CommonError
     */
	public function __construct(string $host, string $user, string $pass, string $name, int $port = 3306) {
		$this->host = $host;
		$this->user = $user;
		$this->pass = $pass;
		$this->name = $name;
		$this->port = $port;
		$this->connect();
	}

	public function reconnect(): bool {
		$this->driver->close();
		return $this->connect();
	}

	public function connect(): bool {
		$this->driver = new mysqli($this->host, $this->user, $this->pass, $this->name, $this->port);
		if($this->driver->connect_error) {
			throw new CommonError($this->driver->connect_error, $this->driver->connect_errno);
		}
		$this->driver->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);
		$this->query('SET names utf8mb4');
		$this->query('SET sql_mode=""');
		return true;
	}

    /**
     * Find many
     *
     * @param string $sql
     * @param array $data
     * @return array
     * @throws ConstraintError
     * @throws CommonError
     */
    public function find(string $sql, array $data = []): array {
	    return $this->query($sql, $data);
    }

    /**
     * Find One
     *
     * @param string $sql
     * @param array $data
     * @return null|array
     * @throws ConstraintError
     * @throws CommonError
     */
	public function findOne(string $sql, array $data = []): ?array {
		$items = $this->query($sql, $data);
		return $items ? $items[0] : null;
	}

    /**
     * Find One By
     *
     * @param string $table
     * @param string $key
     * @param $value
     * @param array $fields
     * @return null|array
     * @throws ConstraintError
     * @throws CommonError
     */
	public function findOneBy(string $table, string $key, $value, array $fields = ['*']): ?array {
		return $this->findOne('SELECT {&fields} FROM {&table} WHERE {&key} = {$value}', [
			'fields' => $fields, 'table' => $table, 'key' => $key, 'value' => $value
		]);
	}

	/**
	 * Enum items
	 *
	 * @param string $sql
	 * @param array $data
	 * @return array
	 */
	public function enum(string $sql, array $data = []): array {
		$result = $this->query($sql, $data);
		if(!$result) return [];
		$return = [];
		$cols = array_keys($result[0]);
		$keys = count($cols) > 1;
		foreach($result as $r) {
			if($keys) {
				$return[$r[$cols[0]]] = $r[$cols[1]];
			} else {
				$return[] = $r[$cols[0]];
			}
		}
		return $return;
	}

    /**
     * Insert
     *
     * @param string $table
     * @param array $data
     * @param bool $ignore
     * @return int|null
     * @throws ConstraintError
     * @throws CommonError
     */
	public function insert(string $table, array $data, bool $ignore = false): ?int {
		$res = $this->query('INSERT {#ignore} INTO {&table} ({&keys}) VALUES {$vals} ', [
			'table' => $table,
			'keys' => array_keys($data),
			'vals' => array_values($data),
			'ignore' => $ignore ? 'IGNORE' : ''
		]);
		return $res['insert_id'] ?: null;
	}

    /**
     * Update
     *
     * @param string $table
     * @param array $data
     * @param string $key
     * @param $value
     * @param bool $ignore
     * @return bool
     * @throws ConstraintError
     * @throws CommonError
     */
	public function update(string $table, array $data, string $key, $value, bool $ignore = false): bool {
		if(!count($data)) return false;
		$where = $this->prepare('{&key} = {$value}', [
			'key' => $key,
			'value' => $value
		]);
		return $this->updateWhere($table, $data, $where, $ignore);
	}

	/**
	 * Update by custom $where
	 *
	 * @param string $table
	 * @param array $data
	 * @param string $where
	 * @param bool $ignore
	 * @return bool
	 */
	public function updateWhere(string $table, array $data, string $where, bool $ignore = false): bool {
		if(!count($data)) return false;
		$sql = 'UPDATE {#ignore} {&table} SET ';
		$pairs = [];
		foreach ($data as $k => $v) $pairs[] = $this->escapeId($k) . ' = ' . $this->escape($v);
		$sql .= implode(', ', $pairs) . ' WHERE ' . $where;
		$res = $this->query($sql, [
			'table' => $table,
			'ignore' => $ignore ? 'IGNORE' : ''
		]);
		return (bool)$res['affected_rows'];
	}

	/**
	 * Smart save
	 *
	 * @param string $table
	 * @param array $data
	 * @return bool
	 */
	public function save(string $table, array $data): bool {
		$keys = array_keys($data);
		$vals = array_values($data);
		$pairs = [];
		foreach ($data as $k => $v) {
			$pairs[] = $this->escapeId($k) . ' = ' . $this->escape($v);
		}
		$this->query('INSERT INTO {&table} ({&keys}) VALUES {$vals} ON DUPLICATE KEY UPDATE ' . implode(', ', $pairs), [
			'table' => $table,
			'keys' => $keys,
			'vals' => $vals,
		]);
		return true;
	}

    /**
     * Delete
     *
     * @param string $table
     * @param string $key
     * @param $value
     * @return bool
     * @throws ConstraintError
     * @throws CommonError
     */
	public function delete(string $table, string $key, $value): bool {
		$res = $this->query('DELETE FROM {&table} WHERE {&key} = {$value}', [
			'table' => $table, 'key' => $key, 'value' => $value
		]);
		return (bool)$res['affected_rows'];
	}

	/**
	 * Prepare SQL
     *
	 * @param string $sql
	 * @param array $values
	 * @return string
	 */
	public function prepare(string $sql, array $values = []): string {
		foreach ($values as $k => $v) {
			if(is_numeric($k)) {
				$sql = preg_replace('/\?/', $this->escape($v), $sql, 1);
			} else {
				$sql = str_replace('{$' . $k . '}', $this->escape($v), $sql);
				$sql = str_replace('{&' . $k . '}', $this->escapeId($v), $sql);
				if(is_string($v)) $sql = str_replace('{#' . $k . '}', $v, $sql);
			}
		}
		return $sql;
	}

	/**
	 * Escape value
     *
	 * @param $value
	 * @return string
	 */
	public function escape($value): string {
		if(is_integer($value) || is_float($value)) return (string)$value;
		elseif(is_bool($value)) return $value ? 'TRUE' : 'FALSE';
		elseif(is_null($value)) return 'NULL';
		elseif($value instanceof DateTime) return '"' . $value->format('Y-m-d H:i:s') . '"';
		elseif(is_array($value)) {
			$result = [];
			foreach ($value as $v) $result[] = $this->escape($v);
			return '(' . implode(', ', $result) . ')';
		} elseif(is_object($value)) {
			if($value->id) return $this->driver->real_escape_string($value->id);
			else return '"' . $this->driver->real_escape_string(json_encode($value)) . '"';
		} else {
			return '"' . $this->driver->real_escape_string($value) . '"';
		}
	}

	/**
	 * Escape field or table
     *
	 * @param array|string $key
	 * @return string
	 */
	public function escapeId($key): string {
		if(is_array($key)) {
			$result = [];
			foreach ($key as $k) $result[] = '`' . $this->driver->real_escape_string($k) . '`';
			$result = implode(', ', $result);
		} else {
			$result =  '`' . $this->driver->real_escape_string($key) . '`';
		}
		$result = str_replace(['.', '`*`'], ['`.`', '*'], $result);
		$result = str_ireplace(' AS ', '` AS `', $result);
		$result = str_replace(['``'], ['`'], $result);
		return $result;
	}

	/**
	 * Get mysqli instance
     *
	 * @return mysqli
	 */
	public function getDriver(): mysqli {
		return $this->driver;
	}

	/**
	 * Set verbose level (0-2)
     *
	 * @param int $verbose
	 */
	public function setVerbose(int $verbose): void {
		$this->verbose = $verbose;
	}

	/**
	 * Start transaction
	 */
	public function begin(): void {
		if($this->depth === 0) $this->query('START TRANSACTION');
		$this->depth++;
	}

	/**
	 * Commit transaction
	 */
	public function commit(): void {
		if($this->depth === 1) $this->query('COMMIT');
		$this->depth--;
	}

	/**
	 * Rollback transaction
	 */
	public function rollback(): void {
		if($this->depth === 1) $this->query('ROLLBACK');
		$this->depth--;
	}

	/**
	 * Query SQL
	 *
	 * @param string $sql
	 * @param array $data
	 * @param callable|null $handler
	 * @return array
	 *
	 */
    public function query(string $sql, array $data = [], callable $handler = null) {
        $sql = $this->prepare($sql, $data);
        if($this->verbose > 0) echo str_repeat('-', 30), PHP_EOL;
        if($this->verbose >= 1) echo $sql, PHP_EOL;
	    $res = $this->driver->query($sql);
	    if($res === false) {
		    /** @var string $error */
		    $error = $this->driver->error;
		    /** @var int $errno */
		    $errno = $this->driver->errno;
		    if($errno === 1451 || $errno === 1452) throw new ConstraintError($error, $errno, $sql);
		    if($errno === 1062) throw new DuplicateError($error, $errno, $sql);
		    throw new CommonError($error, $errno, $sql);
	    }
        if($handler) return $handler($res, $this, $this->driver);
        if($res === true) {
            $result = [
                'insert_id' => $this->driver->insert_id,
                'affected_rows' => $this->driver->affected_rows
            ];
            if($this->verbose >= 2) echo print_r($result, true), PHP_EOL;
            return $result;
        } else {
            $items = $res->fetch_all(MYSQLI_ASSOC);
            if($this->verbose >= 2) echo print_r($items, true), PHP_EOL;
            return $items;
        }

    }

    public function getTables(): array {
    	return $this->enum('SHOW TABLES');
    }

    public function showCreateTable(string $table): ?string {
    	$r = $this->enum('SHOW CREATE TABLE ' . $this->escapeId($table));
    	return $r[$table];
    }

}
