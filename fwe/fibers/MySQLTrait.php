<?php
namespace fwe\fibers;

use fwe\db\IEvent;
use fwe\db\MySQLQuery;

trait MySQLTrait {

	/**
	 * 自增ID: 用于生成异步查询结果的数据键
	 *
	 * @var integer $__findById
	 * @var integer $__unique
	 * @var integer $__insert
	 * @var integer $__update
	 * @var integer $__delete
	 */
	protected static $__findById = 0, $__unique = 0, $__insert = 0, $__update = 0, $__delete = 0;

	/**
	 * 创建查询构建器: SELECT fields... FROM tables... {LEFT|RIGHT|INNER} JOIN joinTables... ON joinCondition... WHERE conditions... GROUP BY groupFields... HAVING groupConditions... ORDER BY orderFields... LIMIT offset, size
	 *
	 * @param string $alias
	 * @return MySQLQueryImpl
	 */
	public static function find(?string $alias = null) {
		/* @var $query MySQLQueryImpl */
		$query = \Fwe::createObject(MySQLQueryImpl::class, [
			'modelClass' => get_called_class()
		]);
		$table = static::tableName();

		return $query->from($table, $alias ?? $table[0]);
	}

	/**
	 * 根据主键查询
	 *
	 * @param MySQLFiber $db
	 * @param array|integer|string $id
	 * @param callable $success
	 * @param callable $error
	 * @return MySQLFiber
	 */
	public static function findById(MySQLFiber $db, $id) {
		if(is_array($id) && ! array_is_list($id)) {
			$attrs = $id;
		} else {
			$attrs = [];
			$ids = (array) $id;
			foreach(static::priKeys() as $i => $key) {
				$attrs[$key] = $ids[$i] ?? '';
			}
			unset($ids);
		}

		return static::find()->whereArgs('and', $attrs)->fetchOne($db, 'fid-' . (static::$__findById ++));
	}

	public function unique(MySQLFiber $db, array $attributes) {
		$attrs = [];
		foreach($attributes as $attr) {
			$attrs[$attr] = $this->$attr;
		}

		$args = [
			'and',
			$attrs
		];

		if(! $this->isNewRecord) {
			$attrs = [];

			foreach(static::priKeys() as $key) {
				$attrs[$key] = $this->getAttribute($key);
			}

			$args[] = [
				'not',
				[
					'and',
					$attrs
				]
			];
		}

		$query = static::find()->select('count(1)')->whereArray($args)->limit(0, 1);
		
		return $query->exec($db, [
			'style' => IEvent::FETCH_COLUMN,
			'key' => 'uniq-' . (static::$__unique ++)
		]);
	}

	public function save(MySQLFiber $db, bool $isPerOne = false, bool $isOnly = false) {
		if(UtilFiber::validate($this, $isPerOne, $isOnly, $db)) {
			if($this->isNewRecord) {
				return $this->insert($db);
			} else {
				return $this->update($db);
			}
		} else {
			return false;
		}
	}

	public function insert(MySQLFiber $db) {
		return static::insertAll($db, [
			$this->attributes
		]);
	}

	public function update(MySQLFiber $db) {
		$attrs = [];
		foreach(static::priKeys() as $key) {
			$attrs[$key] = $this->$key;
		}

		return static::updateAll($db, $this->attributes, ['and', $attrs]);
	}

	public function delete(MySQLFiber $db) {
		$attrs = [];
		foreach(static::priKeys() as $key) {
			$attrs[$key] = $this->$key;
		}

		return static::deleteAll($db, ['and', $attrs]);
	}

	public static function deleteAll(MySQLFiber $db, array $where) {
		$table = static::tableName();
		$params = [];
		$where = MySQLQuery::makeWhere($where, $params) ?: '0 > 1';
		$sql = "DELETE FROM `$table` WHERE $where";

		return (object) $db->prepare($sql, $params, [
			'key' => 'up-' . (static::$__delete ++),
		]);
	}

	public static function updateAll(MySQLFiber $db, array $data, array $where) {
		$table = static::tableName();
		$params = array_values($data);
		$data = implode('` = ?, `', array_keys($data));
		$where = MySQLQuery::makeWhere($where, $params) ?: '0 > 1';
		$sql = "UPDATE `$table` SET `$data` = ? WHERE $where";

		return (object) $db->prepare($sql, $params, [
			'key' => 'up-' . (static::$__update ++),
		]);
	}

	public static function insertAll(MySQLFiber $db, array $rows) {
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

		return (object) $db->prepare($sql, $params, [
			'key' => 'up-' . (static::$__update ++),
		]);
	}
}

