<?php
namespace fwe\db;

abstract class MySQLModel extends Model {
	abstract public static function tableName();

	public static function find(?string $alias = null) {
		/* @var $query MySQLQuery */
		$query = \Fwe::createObject(MySQLQuery::class);
		$table = static::tableName();

		return $query->from($table, $alias ?? $table[0]);
	}
}
