<?php
namespace fwe\db;

use fwe\base\Controller;

class Generator {
	const TYPE_CHAR = 'char';
	const TYPE_STRING = 'string';
	const TYPE_TEXT = 'text';
	const TYPE_TINYINT = 'tinyint';
	const TYPE_SMALLINT = 'smallint';
	const TYPE_INTEGER = 'integer';
	const TYPE_BIGINT = 'bigint';
	const TYPE_FLOAT = 'float';
	const TYPE_DOUBLE = 'double';
	const TYPE_DECIMAL = 'decimal';
	const TYPE_DATETIME = 'datetime';
	const TYPE_TIMESTAMP = 'timestamp';
	const TYPE_TIME = 'time';
	const TYPE_DATE = 'date';
	const TYPE_BINARY = 'binary';
	const TYPE_BOOLEAN = 'boolean';
	const TYPE_JSON = 'json';
	
	public $typeMap = [
		'tinyint' => self::TYPE_TINYINT,
		'bit' => self::TYPE_INTEGER,
		'smallint' => self::TYPE_SMALLINT,
		'mediumint' => self::TYPE_INTEGER,
		'int' => self::TYPE_INTEGER,
		'integer' => self::TYPE_INTEGER,
		'bigint' => self::TYPE_BIGINT,
		'float' => self::TYPE_FLOAT,
		'double' => self::TYPE_DOUBLE,
		'real' => self::TYPE_FLOAT,
		'decimal' => self::TYPE_DECIMAL,
		'numeric' => self::TYPE_DECIMAL,
		'tinytext' => self::TYPE_TEXT,
		'mediumtext' => self::TYPE_TEXT,
		'longtext' => self::TYPE_TEXT,
		'longblob' => self::TYPE_BINARY,
		'blob' => self::TYPE_BINARY,
		'text' => self::TYPE_TEXT,
		'varchar' => self::TYPE_STRING,
		'string' => self::TYPE_STRING,
		'char' => self::TYPE_CHAR,
		'datetime' => self::TYPE_DATETIME,
		'year' => self::TYPE_DATE,
		'date' => self::TYPE_DATE,
		'time' => self::TYPE_TIME,
		'timestamp' => self::TYPE_TIMESTAMP,
		'enum' => self::TYPE_STRING,
		'varbinary' => self::TYPE_BINARY,
		'json' => self::TYPE_JSON,
	];
	
	public function allTable(MySQLConnection $db, callable $success, callable $error) {
		$db->asyncQuery('SHOW TABLES', ['key'=>'tables', 'style'=>IEvent::FETCH_COLUMN_ALL])
		->goAsync(function($tables) use($success) {
			$success($tables);
		}, function($data, $e) use($error) {
			$error($data, $e);
		});
	}
	
	public function oneTable(MySQLConnection $db, string $table, callable $success, callable $error) {
		$db->asyncQuery("SHOW FULL FIELDS FROM `$table`", ['key'=>'fields', 'style'=>IEvent::FETCH_ALL])
		->asyncQuery("SHOW INDEX FROM `$table`", ['key'=>'indexes', 'style'=>IEvent::FETCH_ALL])
		->asyncPrepare('SELECT TABLE_COMMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?', [$db->getDatabase(), $table], ['key'=>'comment', 'style'=>IEvent::FETCH_COLUMN])
		->goAsync(function($fields, $indexes, $comment) use($success) {
			$data = [];
			$data['comment'] = $comment;
			$data['fields'] = $data['indexes'] = [];
			
			foreach($fields as $field) {
				$column = [
					'name' => $field['Field'],
					'type' => 'string',
					'allowNull' => $field['Null'] === 'YES',
					'autoIncrement' => stripos($field['Extra'], 'auto_increment') !== false,
					'unsigned' => stripos($field['Type'], 'unsigned') !== false,
					'value' => $field['Default'],
					'comment' => $field['Comment'],
					'size' => 0,
					'scale' => 0,
					'precision' => 0,
					'enumValues' => [],
					'label' => $this->getLabel($field['Field']),
				];
				
				if (preg_match('/^(\w+)(?:\(([^\)]+)\))?/', $field['Type'], $matches)) {
					$type = strtolower($matches[1]);
					if (isset($this->typeMap[$type])) {
						$column['type'] = $this->typeMap[$type];
					}
					if (!empty($matches[2])) {
						if ($type === 'enum') {
							preg_match_all("/'[^']*'/", $matches[2], $values);
							foreach ($values[0] as $i => $value) {
								$values[$i] = trim($value, "'");
							}
							$column['enumValues'] = $values;
						} else {
							$values = explode(',', $matches[2]);
							$column['size'] = $column['precision'] = (int) $values[0];
							if (isset($values[1])) {
								$column['scale'] = (int) $values[1];
							}
							if ($column['size'] === 1 && $type === 'bit') {
								$column['type'] = 'boolean';
							} elseif ($type === 'bit') {
								if ($column['size'] > 32) {
									$column['type'] = 'bigint';
								} elseif ($column['size'] === 32) {
									$column['type'] = 'integer';
								}
							}
						}
					}
				}
				
				$column['phpType'] = $this->getColumnPhpType($column);
				$column['phpValue'] = $this->getColumnPhpValue($column);
				
				$data['fields'][$column['name']] = $column;
			}
			
			foreach($indexes as $index) {
				if(empty($index['Non_unique'])) {
					$data['indexes'][$index['Key_name']][] = $index['Column_name'];
				}
			}
			
			// $json = json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
			// echo "$json\n";
			
			$success($data);
		}, function($data, $e) use($error) {
			$error($data, $e);
		});
	}
	
	public function generate(Controller $controller, string $view, string $target, array $params, bool $isOver = false) {
		$params['generator'] = $this;
		$file = $controller->getViewFile($view);
		$cont = $controller->renderFile($file, $params);
		$targetFile = \Fwe::getAlias($target);
		
		clearstatcache(true, $targetFile);
		
		if(!$isOver && is_file($targetFile)) {
			$target = trim(preg_replace('/[^a-zA-Z0-9\.]+/', '-', $target), '-');
			$tmpFile = \Fwe::getAlias("@app/runtime/$target");
			return [file_put_contents($tmpFile, $cont) !== false, $targetFile, $tmpFile];
		} else {
			$targetPath = dirname($targetFile);
			if(!is_dir($targetPath)) mkdir($targetPath, 0755, true);
			return [file_put_contents($targetFile, $cont) !== false, $targetFile, $targetFile];
		}
	}
	
	protected function getColumnPhpType(array $column) {
		static $typeMap = [
			// abstract type => php type
			self::TYPE_TINYINT => 'integer',
			self::TYPE_SMALLINT => 'integer',
			self::TYPE_INTEGER => 'integer',
			self::TYPE_BIGINT => 'integer',
			self::TYPE_BOOLEAN => 'boolean',
			self::TYPE_FLOAT => 'double',
			self::TYPE_DOUBLE => 'double',
			self::TYPE_BINARY => 'resource',
			self::TYPE_JSON => 'array',
		];
		if (isset($typeMap[$column['type']])) {
			if ($column['type'] === 'bigint') {
				return PHP_INT_SIZE === 8 && !$column['unsigned'] ? 'integer' : 'string';
			} elseif ($column['type'] === 'integer') {
				return PHP_INT_SIZE === 4 && $column['unsigned'] ? 'string' : 'integer';
			}
			
			return $typeMap[$column['type']];
		}
		
		return 'string';
	}
	
	protected function getLabel(string $field) {
		return ucwords(preg_replace_callback('/([A-Z][a-z0-9]+)/', function($matches) {
			return " {$matches[0]}";
		}, $field));
	}
	
	protected function getColumnPhpValue(array $column) {
		if($column['value'] === null) {
			return 'null';
		} else {
			switch($column['phpType']) {
				case 'double':
				case 'integer':
					return $column['value'];
				case 'boolean':
					return $column['value'] ? 1 : 0;
				case 'array':
					return var_export(json_decode($column['value']), true);
				default:
					return strcasecmp($column['value'], 'current_timestamp()') ? var_export($column['value'], true) : date("'Y-m-d H:i:s'");
			}
		}
	}
	
	public function genKeyForModel(string $class, string $var, bool $isView = true) {
		$priKeys = $class::priKeys();
		$n = count($priKeys);
		if($n) {
			if($n > 1) {
				$rets = [];
				foreach($priKeys as $key) {
					$rets[] = $isView ? "$key=<?=\$$var->$key?>" : "$key={\$$var->$key}";
				}
				return implode('&', $rets);
			} else {
				$key = reset($priKeys);
				return $isView ? "id=<?=\$$var->$key?>" : "id={\$$var->$key}";
			}
		} else {
			return 'unknown';
		}
	}
}
