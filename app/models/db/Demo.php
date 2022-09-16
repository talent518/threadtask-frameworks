<?php
namespace app\models\db;

use fwe\db\MySQLModel;
use fwe\db\MySQLTrait;

/**
 * @method static Demo create(array $params = [])
 */
class Demo extends MySQLModel {
	use MySQLTrait;
	
	public static function tableName() {
		return 'user';
	}
	
	public static function priKeys() {
		return ['uid'];
	}
	
	public static function searchKeys() {
		return [
			'uid' => '=',
			'username' => 'like',
			'email' => 'like',
			'password' => 'like',
			'salt' => '=',
			'registerTime' => 'like',
			'loginTime' => 'like',
		];
	}
	
	protected $attributes = [
		'uid' => 0,
		'username' => '',
		'email' => '',
		'password' => '',
		'salt' => '123456',
		'registerTime' => '',
		'loginTime' => '',
	];
	
	protected function getAttributeNames() {
		return ['uid', 'username', 'password', 'registerTime', 'loginTime'];
	}
	
	protected function getLabels() {
		return [
			'uid' => '用户ID',
			'username' => '用户名',
			'password' => '密码',
		];
	}
	
	public function getRules() {
		return [
			['username, password, email', 'required'],
			['username', 'unique'],
			['email', 'email'],
			['email', 'unique'],
			['error', 'unique'],
		];
	}
	
	public $error;
}
