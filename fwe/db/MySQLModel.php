<?php
namespace fwe\db;

abstract class MySQLModel extends Model {
	abstract public static function tableName();
	abstract public static function priKeys();
	abstract public static function searchKeys();
}
