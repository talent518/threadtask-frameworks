<?php
namespace fwe\db;

class MySQLQuery {
	protected static $likeEscapes = [
		'%' => '\%',
		'_' => '\_',
		'\\' => '\\\\',
	];
	
	protected static $sqlEscapes = [
		'\'' => '\\\'',
		"\r" => '\r',
		"\n" => '\n',
	];
	
	/**
	 * 自增ID: 用于生成异步查询结果的数据键
	 * 
	 * @var integer
	 */
	protected static $__query = 0;

	/**
	 * 用于继承于MySQLModel类对的查询结果数据行的对象实例化
	 * 
	 * @var ?string
	 */
	protected $modelClass, $keyBy, $valueBy;
	
	protected $prefix;
	protected $select = ['*', []];
	protected $from = [];
	protected $join = [];
	protected $where;
	protected $groupBy;
	protected $having;
	protected $orderBy;
	protected $limit;
	
	/**
	 * 构建后保存的数据
	 * 
	 * @var ?string $sql SQL语句
	 * @var ?array $params SQL预处理所需的动态参数
	 */
	protected $sql, $params;
	
	public function __construct(?string $modelClass = null) {
		$this->modelClass = $modelClass;
	}
	
	public function prefix(string ...$options) {
		$this->prefix = implode(' ', $options) . ' ';
		return $this;
	}
	
	public function select(...$fields) {
		$select = [];
		$params = [];
		foreach($fields as $field) {
			if(isset($field['sql'], $field['params'])) {
				$select[] = (isset($field['alias']) ? "({$field['sql']}) as {$field['alias']}" : $field['sql']);
				foreach($field['params'] as $v) $params[] = $v;
			} else if(is_array($field)) {
				if(strpos($field[0], '.') !== false) {
					$field[0] = str_replace('.', '`.`', $field[0]);
				}
				$select[] = "`{$field[0]}` as {$field[1]}";
			} elseif(is_int($field) || strpos($field, '(')) {
				$select[] = $field;
			} else {
				if(strpos($field, '.') !== false) {
					$field = str_replace('.', '`.`', $field);
				}
				$select[] = "`$field`";
			}
		}
		$this->select = [implode(',', $select), $params];
		return $this;
	}
	
	public function from(string $table, string $alias = 'sq') {
		$this->from[] = "`$table` as $alias";
		return $this;
	}
	
	public function leftJoin(string $table, string $alias, string $on, array $params = []) {
		$this->join[] = [
			"LEFT JOIN `$table` as $alias ON $on",
			$params,
		];
		return $this;
	}
	
	public function rightJoin(string $table, string $alias, string $on, array $params = []) {
		$this->join[] = [
			"RIGHT JOIN `$table` as $alias ON $on",
			$params,
		];
		return $this;
	}
	
	public function innerJoin(string $table, string $alias, string $on, array $params = []) {
		$this->join[] = [
			"INNER JOIN `$table` as $alias ON $on",
			$params,
		];
		return $this;
	}
	
	public function where(string $where, array $params = []) {
		$this->where = [$where, $params];
		return $this;
	}
	
	public static function makeWhere(array $args, array &$params) {
		if(isset($args['sql'], $args['params'])) {
			foreach($args['params'] as $v) $params[] = $v;
			return $args['sql'];
		}

		$oper = array_shift($args);
		$op = strtoupper(preg_replace('/\s+/', ' ', trim($oper)));

		switch($op) {
			case 'AND':
			case 'OR':
				$sqls = [];
				foreach($args as $arg) {
					if((is_array($arg) && !isset($arg[0])) || is_object($arg)) {
						foreach($arg as $key=>$value) {
							$sql = static::makeWhere(['=', "`$key`", $value], $params);
							if($sql !== null) {
								$sqls[] = $sql;
							}
						}
					} else {
						$sql = static::makeWhere($arg, $params);
						if($sql !== null) {
							$sqls[] = $sql;
						}
					}
				}
				if($sqls) {
					$sql = implode(" $op ", $sqls);
					$sql = "($sql)";
				} else {
					$sql = null;
				}
				break;
			case '!':
			case 'NOT':
				$sql = static::makeWhere($args[0], $params);
				if($sql !== null) {
					$sql = "$op ($sql)";
				}
				break;
			case 'IN':
			case 'NOT IN':
				$field = array_shift($args);
				$value = array_shift($args);
				if(isset($value['sql'], $value['params'])) {
					$sql = "{$field} $op ({$value['sql']})";
					foreach($value['params'] as $v) $params[] = $v;
				} else {
					$sqls = [];
					array_unshift($args, $value);
					foreach($args as $arg) {
						if($arg !== null) {
							$sqls[] = '?';
							$params[] = $arg;
						}
					}
					if($sqls) {
						$sql = implode(', ', $sqls);
						if($sql === '?') {
							$op = ($op === 'IN' ? '=' : '!=');
							$sql = "$field $op ?";
						} else {
							$sql = "$field $op ($sql)";
						}
					} else {
						$sql = null;
					}
				}
				break;
			case '=':
			case '!=':
			case '<>':
			case '>':
			case '>=':
			case '<':
			case '<=':
				list($field, $value) = $args;
				if($value === null) {
					if($op === '=') {
						$sql = "{$field} IS NULL";
					} else {
						$sql = "{$field} IS NOT NULL";
					}
				} elseif(isset($value['sql'], $value['params'])) {
					$sql = "{$field} $op ({$value['sql']})";
					foreach($value['params'] as $v) $params[] = $v;
				} else {
					$sql = "{$field} $op ?";
					$params[] = $value;
				}
				break;
			case 'BETWEEN':
				list($field, $value0, $value1) = $args;
				$sql = "({$field} BETWEEN ? AND ?)";
				$params[] = $value0;
				$params[] = $value1;
				break;
			case '|': // 位或
			case '&': // 位与
			case '^': // 位异或
			case '<<': // 位左移
			case '>>': // 位右移
				@list($field, $value0, $op1, $value1) = $args;
				if($op1 === null) {
					$sql = "({$field} $op ?)";
					$params[] = $value0;
				} else {
					$sql = "({$field} $op ?) $op1 ?";
					$params[] = $value0;
					$params[] = $value1;
				}
				break;
			case '~':
				@list($field, $op, $value) = $args;
				if($op === null) {
					$sql = "(~{$field})";
				} else {
					$sql = "(~{$field}) $op ?";
					$params[] = $value;
				}
				break;
			case 'LIKE':
			case 'NOT LIKE':
				@list($field, $value, $left, $right, $and) = $args;
				if($left === null) $left = '%'; else $left = ($left ? '%' : '');
				if($right === null) $right = '%'; else $right = ($right ? '%' : '');
				$op2 = ($and ? 'AND' : 'OR');
				$sqls = [];
				foreach((array) $value as $v) {
					$sqls[] = "{$field} $op ?";
					$params[] = $left . strtr($v, static::$likeEscapes) . $right;
				}
				$sql = implode(" $op2 ", $sqls);
				break;
			default:
				$sql = (string) $oper;
				break;
		}

		return $sql;
	}
	
	public function whereArray(array $args) {
		$params = [];
		$sql = static::makeWhere($args, $params);
		if($sql) {
			$this->where = [$sql, $params];
		}
		return $this;
	}
	
	public function whereArgs(...$args) {
		$params = [];
		$sql = static::makeWhere($args, $params);
		if($sql) {
			$this->where = [$sql, $params];
		}
		return $this;
	}
	
	public function groupBy(...$fields) {
		$this->groupBy = implode(', ', $fields);
		return $this;
	}
	
	public function having(string $having, array $params = []) {
		$this->having = [$having, $params];
		return $this;
	}
	
	public function havingArray(array $args) {
		$params = [];
		$sql = static::makeWhere($args, $params);
		if($sql) {
			$this->having = [$sql, $params];
		}
		return $this;
	}
	
	public function havingArgs(...$args) {
		$params = [];
		$sql = static::makeWhere($args, $params);
		if($sql) {
			$this->having = [$sql, $params];
		}
		return $this;
	}
	
	public function orderBy(...$args) {
		$this->orderBy = implode(', ', $args);
		return $this;
	}
	
	public function limit(int $offset, int $size) {
		$this->limit = [$offset, $size];
		return $this;
	}
	
	public function page(int &$page, int &$total, int &$size, &$pages = 0) {
		if($total <= 0 || $size <= 0) return $this;

		if($page <= 0) $page = 1;

		$pages = ceil($total / $size);
		if($page > $pages) $page = $pages;
		
		$this->limit = [($page - 1) * $size, $size];
		
		return $this;
	}
	
	public function build(bool $isForce = false) {
		if(!$isForce && $this->sql !== null && $this->params !== null) {
			return [
				'sql' => $this->sql,
				'params' => $this->params,
			];
		}
		
		$sql = "SELECT {$this->prefix}{$this->select[0]} FROM ";
		$sql .= implode(', ', $this->from);
		
		$params = $this->select[1];
		
		foreach($this->join as $join) {
			$sql .= " {$join[0]}";
			foreach($join[1] as $v) $params[] = $v;
		}
		
		if($this->where) {
			$sql .= " WHERE {$this->where[0]}";
			foreach($this->where[1] as $v) $params[] = $v;
		}
		
		if($this->groupBy) {
			$sql .= " GROUP BY {$this->groupBy}";
			
			if($this->having) {
				$sql .= " HAVING {$this->having[0]}";
				foreach($this->having[1] as $v) $params[] = $v;
			}
		}
		
		if($this->orderBy) {
			$sql .= " ORDER BY {$this->orderBy}";
		}
		
		if($this->limit) {
			$sql .= " LIMIT ?, ?";
			foreach($this->limit as $v) $params[] = $v;
		}
		
		$this->sql = $sql;
		$this->params = $params;
		
		return compact('sql', 'params');
	}
	
	public function makeSQL() {
		$this->build();

		return static::formatSQL($this->sql, $this->params);
	}
	
	public static function formatSQL(string $sql, array $params) {
		$i = 0;
		while(($pos = strpos($sql, '?')) !== false) {
			$str = $params[$i++];
			if(is_array($str) || is_object($str)) $str = json_encode($str, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
			if(is_string($str)) {
				$str = strtr($str, static::$sqlEscapes);
				$str = "'$str'";
			} elseif(is_bool($str)) {
				$str = ($str ? 1 : 0);
			} elseif(is_null($str)) {
				$str = 'NULL';
			}
			$sql = substr_replace($sql, $str, $pos, 1);
		}
		
		return $sql;
	}
	
	public function exec(MySQLConnection $db, array $options = [], ?callable $success = null, ?callable $error = null) {
		$this->build();
		
		if($success) $options['success'] = $success;
		if($error) $options['error'] = $error;
		if(!isset($options['key'])) $options['key'] = 'q-' . (static::$__query++);
		
		return $db->asyncPrepare($this->sql, $this->params, $options);
	}
	
	public function fetchOne(MySQLConnection $db, ?string $key = null, ?callable $success = null, ?callable $error = null) {
		return $this->limit(0, 1)->exec(
			$db,
			[
				'key' => $key,
				'style' => IEvent::FETCH_ONE
			],
			function($row) use($success) {
				$class = $this->modelClass;
				$row = ($class && $row !== null ? $class::populate($row) : $row);
				
				return $success ? $success($row) : $row;
			},
			function(\Throwable $e) use($error) {
				if($error) {
					$error($e);
				} else {
					throw $e;
				}
			}
		);
	}
	
	public function fetchAll(MySQLConnection $db, ?string $key = null, ?callable $success = null, ?callable $error = null) {
		return $this->exec(
			$db,
			[
				'key' => $key,
				'keyBy' => $this->keyBy,
				'valueBy' => $this->valueBy,
				'style' => IEvent::FETCH_ALL
			],
			function($rows) use($success) {
				$class = $this->modelClass;
				if($class && $this->valueBy === null) {
					$rets = [];
					
					foreach($rows as $i => $row) {
						$rets[$i] = $class::populate($row);
					}
					
					return $success ? $success($rets) : $rets;
				} else {
					return $rows;
				}
			},
			function(\Throwable $e) use($error) {
				if($error) {
					$error($e);
				} else {
					throw $e;
				}
			}
		);
	}
	
	public function fetchColumn(MySQLConnection $db, int $col, ?string $key = null, ?callable $success = null, ?callable $error = null) {
		return $this->limit(0, 1)->exec(
			$db,
			[
				'key' => $key,
				'col' => $col,
				'style' => IEvent::FETCH_COLUMN
			],
			function($col) use($success) {
				return $success ? $success($col) : $col;
			},
			function(\Throwable $e) use($error) {
				if($error) {
					$error($e);
				} else {
					throw $e;
				}
			}
		);
	}
	
	public function fetchColumnAll(MySQLConnection $db, ?string $key = null, ?callable $success = null, ?callable $error = null) {
		return $this->exec(
			$db,
			[
				'key' => $key,
				'style' => IEvent::FETCH_COLUMN_ALL
			],
			function($cols) use($success) {
				return $success ? $success($cols) : $cols;
			},
			function(\Throwable $e) use($error) {
				if($error) {
					$error($e);
				} else {
					throw $e;
				}
			}
		);
	}
}
