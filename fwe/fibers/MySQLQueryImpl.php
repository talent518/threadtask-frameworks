<?php
namespace fwe\fibers;

use fwe\db\MySQLQuery;
use fwe\db\IEvent;

class MySQLQueryImpl extends MySQLQuery {

	public function exec(MySQLFiber $db, array $options = []) {
		$this->build();

		if(! isset($options['key'])) {
			$options['key'] = 'q-' . (static::$__query ++);
		}

		if(count($this->params)) {
			return $db->prepare($this->sql, $this->params, $options);
		} else {
			return $db->query($this->sql, $options);
		}
	}

	public function fetchOne(MySQLFiber $db, ?string $key = null) {
		$row = $this->limit(0, 1)->exec($db, [
			'key' => $key,
			'style' => IEvent::FETCH_ONE
		]);

		$class = $this->modelClass;
		return ($class && $row !== null ? $class::populate($row) : $row);
	}

	public function fetchAll(MySQLFiber $db, ?string $key = null) {
		$rows = $this->exec($db, [
			'key' => $key,
			'keyBy' => $this->keyBy,
			'valueBy' => $this->valueBy,
			'style' => IEvent::FETCH_ALL
		]);

		$class = $this->modelClass;
		if($class && $this->valueBy === null) {
			$rets = [];

			foreach($rows as $i => $row) {
				$rets[$i] = $class::populate($row);
			}

			return $rets;
		} else {
			return $rows;
		}
	}

	public function fetchColumn(MySQLFiber $db, int $col, ?string $key = null) {
		return $this->limit(0, 1)->exec($db, [
			'key' => $key,
			'col' => $col,
			'style' => IEvent::FETCH_COLUMN
		]);
	}

	public function fetchColumnAll(MySQLFiber $db, ?string $key = null) {
		return $this->exec($db, [
			'key' => $key,
			'style' => IEvent::FETCH_COLUMN_ALL
		]);
	}
}

