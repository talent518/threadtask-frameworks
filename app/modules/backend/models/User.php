<?php

namespace app\modules\backend\models;

/**
 * 主用户表
 *
 * 由fwe\web\GeneratorController生成的代码
 *
 * @method static User create(array $params = [])
 *
 * @property integer $uid 用户ID
 * @property string $username 用户名
 * @property string $email 邮箱
 * @property string $password 密码
 * @property string $salt 安全码
 * @property string $registerTime 注册时间
 * @property string $loginTime 最后登录时间
 * @property integer $loginTimes 登录次数
 * 
 * @property string $newpass 新密码
 */
class User extends \fwe\db\MySQLModel {
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
			'salt' => 'like',
			'registerTime' => 'like',
			'loginTime' => 'like',
			'loginTimes' => '=',
		];
	}
	
	protected $attributes = [
		'uid' => null,
		'username' => null,
		'email' => null,
		'password' => null,
		'salt' => null,
		'registerTime' => null,
		'loginTime' => null,
		'loginTimes' => 0,
	];
	
	protected function getAttributeNames() {
		return ['uid', 'username', 'email', 'password', 'salt', 'registerTime', 'loginTime', 'loginTimes', 'newpass'];
	}
	
	protected function getLabels() {
		return [
			'uid' => '用户ID',
			'username' => '用户名',
			'email' => '邮箱',
			'password' => '密码',
			'salt' => '安全码',
			'registerTime' => '注册时间',
			'loginTime' => '最后登录时间',
			'loginTimes' => '登录次数',
			
			'newpass' => '设置密码',
		];
	}
	
	public function getRules() {
		return [
			['username, email, password, salt, registerTime, loginTimes', 'required'],
			['uid, loginTimes', 'string', 'max' => 11],
			['username', 'string', 'max' => 20],
			['email', 'string', 'max' => 100],
			['password', 'string', 'max' => 32],
			['salt', 'string', 'max' => 8],
			['uid, loginTimes', 'integer'],
			['registerTime, loginTime', 'datetime'],
			['username', 'unique'], // username
			['email', 'unique'], // email
			
			['newpass', 'required', 'scene' => 'create'],
			['newpass', 'safe'],
		];
	}
	
	public function setRegisterTime($value) {
		$this->attributes['registerTime'] = ($value === '' ? null : $value);
	}
	
	public function setLoginTime($value) {
		$this->attributes['loginTime'] = ($value === '' ? null : $value);
	}
	
	protected $newpass;
	
	public function getNewpass() {
		return $this->newpass;
	}
	
	public function setNewpass($value) {
		$this->newpass = $value;
		if($value !== null && $value !== '') {
			$this->salt = (string) random_int(10000000, 99999999);
			$this->password = md5(md5($value) . $this->salt);
			$this->registerTime = date('Y-m-d H:i:s');
		}
	}
}
