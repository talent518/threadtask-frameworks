<?php
namespace app\models\db;

use fwe\db\MySQLModel;

/**
 * @method static Demo create(array $params = [])
 */
class Demo extends MySQLModel {
	public static function tableName() {
		return 'user';
	}
	
	public static function priKeys() {
		return ['uid'];
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
