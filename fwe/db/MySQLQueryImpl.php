<?php
namespace fwe\db;

class MySQLQueryImpl extends MySQLQuery {
	public function exec(MySQLConnection $db, array $options = [], ?callable $success = null, ?callable $error = null) {
		$this->build();

		if($success)
			$options['success'] = $success;
		if($error)
			$options['error'] = $error;
		if(! isset($options['key']))
			$options['key'] = 'q-' . (static::$__query ++);

		return $db->asyncPrepare($this->sql, $this->params, $options);
	}

	public function fetchOne(MySQLConnection $db, ?string $key = null, ?callable $success = null, ?callable $error = null) {
		return $this->limit(0, 1)->exec($db, [
			'key' => $key,
			'style' => IEvent::FETCH_ONE
		], function ($row) use ($success) {
			$class = $this->modelClass;
			$row = ($class && $row !== null ? $class::populate($row) : $row);

			return $success ? $success($row) : $row;
		}, function (\Throwable $e) use ($error) {
			if($error) {
				$error($e);
			} else {
				throw $e;
			}
		});
	}

	public function fetchAll(MySQLConnection $db, ?string $key = null, ?callable $success = null, ?callable $error = null) {
		return $this->exec($db, [
			'key' => $key,
			'keyBy' => $this->keyBy,
			'valueBy' => $this->valueBy,
			'style' => IEvent::FETCH_ALL
		], function ($rows) use ($success) {
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
		}, function (\Throwable $e) use ($error) {
			if($error) {
				$error($e);
			} else {
				throw $e;
			}
		});
	}

	public function fetchColumn(MySQLConnection $db, int $col, ?string $key = null, ?callable $success = null, ?callable $error = null) {
		return $this->limit(0, 1)->exec($db, [
			'key' => $key,
			'col' => $col,
			'style' => IEvent::FETCH_COLUMN
		], function ($col) use ($success) {
			return $success ? $success($col) : $col;
		}, function (\Throwable $e) use ($error) {
			if($error) {
				$error($e);
			} else {
				throw $e;
			}
		});
	}

	public function fetchColumnAll(MySQLConnection $db, ?string $key = null, ?callable $success = null, ?callable $error = null) {
		return $this->exec($db, [
			'key' => $key,
			'style' => IEvent::FETCH_COLUMN_ALL
		], function ($cols) use ($success) {
			return $success ? $success($cols) : $cols;
		}, function (\Throwable $e) use ($error) {
			if($error) {
				$error($e);
			} else {
				throw $e;
			}
		});
	}
}

