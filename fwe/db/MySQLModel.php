<?php
namespace fwe\db;

abstract class MySQLModel extends Model {
	/**
	 * 自增ID: 用于生成异步查询结果的数据键
	 *
	 * @var integer $__findById
	 * @var integer $__unique
	 */
	protected static $__findById = 0, $__unique = 0, $__insert = 0, $__update = 0, $__delete = 0;
	
	abstract public static function tableName();
	abstract public static function priKeys();
	
	/**
	 * 创建查询构建器: SELECT fields... FROM tables... {LEFT|RIGHT|INNER} JOIN joinTables... ON joinCondition... WHERE conditions... GROUP BY groupFields... HAVING groupConditions... ORDER BY orderFields... LIMIT offset, size
	 * 
	 * @param string $alias
	 * @return \fwe\db\MySQLQuery
	 */
	public static function find(?string $alias = null) {
		/* @var $query MySQLQuery */
		$query = \Fwe::createObject(MySQLQuery::class, ['modelClass' => get_called_class()]);
		$table = static::tableName();

		return $query->from($table, $alias ?? $table[0]);
	}
	
	/**
	 * 根据主键查询
	 * 
	 * @param MySQLConnection $db
	 * @param array|integer|string $id
	 * @param callable $success
	 * @param callable $error
	 * @return \fwe\db\MySQLConnection
	 */
	public static function findById(MySQLConnection $db, $id, ?callable $success = null, ?callable $error = null) {
		if(is_array($id) && is_assoc($id)) {
			$attrs = $id;
		} else {
			$attrs = [];
			$ids = (array) $id;
			foreach(static::priKeys() as $i => $key) {
				$attrs[$key] = $ids[$i];
			}
			unset($ids);
		}
		
		return static::find()
		->whereArgs('and', $attrs)
		->fetchOne($db, 'fid-' . (static::$__findById++), $success, $error);
	}
	
	public function unique(array $attributes, MySQLConnection $db, callable $ok) {
		$attrs = [];
		foreach($attributes as $attr) {
			$attrs[$attr] = $this->$attr;
		}

		$args = ['and', $attrs];
		
		if(!$this->isNewRecord) {
			$attrs = [];
			
			foreach(static::priKeys() as $key) {
				$attrs[$key] = $this->getAttribute($key);
			}
			
			$args[] = ['not', ['and', $attrs]];
		}
		
		static::find()->select('count(1)')->whereArray($args)->limit(0, 1)
		->exec(
			$db,
			[
				'style' => IEvent::FETCH_COLUMN,
				'key' => 'uniq-' . (static::$__unique++)
			],
			function(int $exists) use($ok) {
				$ok($exists ? 1 : 0);
				return $exists;
			},
			function(\Throwable $e) use($ok) {
				$ok(-1, $e->getMessage());
	
				return (string) $e;
			}
		);
	}
	
	public function save(MySQLConnection $db, callable $success, callable $error) {
		$this->setScene($this->isNewRecord ? 'update' : 'update');
		$this->validate(function(int $n, ?string $errstr = null) use($db, $success, $error) {
			if($errstr || $n) {
				$status = new \stdClass();
				$status->errno = 1;
				$status->error = $errstr;
				$status->data = $n;
				$error($status);
			} elseif($this->isNewRecord) {
				$this->insert(
					$db,
					function(array $data) use($success) {
						$success((object) $data);
					},
					function($err) use($error) {
						$status = new \stdClass();
						$status->errno = 2;
						$status->error = (string) $err;
						$status->data = 'insert';
						$error($status);
					}
				);
			} else {
				$this->update(
					$db,
					function(array $data) use($success) {
						$success((object) $data);
					},
					function($err) use($error) {
						$status = new \stdClass();
						$status->errno = 3;
						$status->error = (string) $err;
						$status->data = 'update';
						$error($status);
					}
				);
			}
		}, false, false, $db);
	}
	
	public function insert(MySQLConnection $db, ?callable $success = null, ?callable $error = null) {
		static::insertAll($db, [$this->attributes], $success, $error);
	}
	
	public function update(MySQLConnection $db, ?callable $success = null, ?callable $error = null) {
		$attrs = [];
		foreach(static::priKeys() as $key) {
			$attrs[$key] = $this->$key;
		}
		
		static::updateAll($db, $this->attributes, ['and', $attrs], $success, $error);
	}
	
	public function delete(MySQLConnection $db, ?callable $success = null, ?callable $error = null) {
		$attrs = [];
		foreach(static::priKeys() as $key) {
			$attrs[$key] = $this->$key;
		}
		
		static::deleteAll($db, ['and', $attrs], $success, $error);
	}
	
	public static function deleteAll(MySQLConnection $db, array $where, ?callable $success = null, ?callable $error = null) {
		$table = static::tableName();
		$params = [];
		$where = MySQLQuery::makeWhere($where, $params) ?: '0 > 1';
		$sql = "DELETE FROM `$table` WHERE $where";
		
		$db->asyncPrepare(
			$sql,
			$params,
			[
				'key' => 'up-' . (static::$__delete++),
				'success' => $success,
				'error' => $error
			]
		);
	}
	
	public static function updateAll(MySQLConnection $db, array $data, array $where, ?callable $success = null, ?callable $error = null) {
		$table = static::tableName();
		$params = array_values($data);
		$data = implode('` = ?, `', array_keys($data));
		$where = MySQLQuery::makeWhere($where, $params) ?: '0 > 1';
		$sql = "UPDATE `$table` SET `$data` = ? WHERE $where";
		
		$db->asyncPrepare(
			$sql,
			$params,
			[
				'key' => 'up-' . (static::$__update++),
				'success' => $success,
				'error' => $error
			]
		);
	}
	
	public static function insertAll(MySQLConnection $db, array $rows, ?callable $success = null, ?callable $error = null) {
		$table = static::tableName();
		$keys = array_keys(reset($rows));
		$fields = implode('`, `', $keys);
		$params = [];
		$data = '';
		$first = true;
		foreach($rows as $row) {
			if($first) {
				$first = false;
			} else {
				$data .= '), (';
			}
			foreach($keys as $i => $key) {
				$params[] = $row[$key];
				$data .= $i ? ', ?' : '?';
			}
		}
		$sql = "INSERT INTO `$table` (`$fields`) VALUES ($data)";
		
		$db->asyncPrepare(
			$sql,
			$params,
			[
				'key' => 'up-' . (static::$__update++),
				'success' => $success,
				'error' => $error
			]
		);
	}
}
