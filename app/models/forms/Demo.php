<?php
namespace app\models\forms;

use fwe\base\Model;

class Demo extends Model {

	public $email;
	public $reply;
	public $subject;
	public $message;
	public $unsafe;
	public $password;
	public $repasswd;
	public $repasswd2;
	public $age;

	public function getRules() {
		return [
			['email, message', 'required'],
			['subject, unsafe', 'safe', 'scene' => 'anonymous'],
			['subject, reply', 'required', 'scene' => 'realname'],
			['password', 'safe'],
			['repasswd', 'compare', 'compareAttribute' => 'password'],
			['repasswd2', 'compare', 'compareValue' => function(){return $this->password;}],
			['unsafe', 'compare', 'compareValue' => '0'],
			['age', 'required'],
			['age', 'compare', 'operator' => '>=', 'compareValue' => 1, 'isNumeric' => true],
		];
	}

	protected function getLabels() {
		return [
			'email' => '收件邮箱',
			'reply' => '回复邮箱',
			'subject' => '邮件主题',
			'message' => '邮件内容',
			'unsafe' => '不安全',
			'password' => '密码',
			'repasswd' => '确认密码',
			'repasswd2' => '再次确认密码',
			'age' => '年龄',
		];
	}
}
