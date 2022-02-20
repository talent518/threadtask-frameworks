<?php
namespace app\models\forms;

use fwe\base\Model;

class Demo extends Model {

	public $email;
	public $reply;
	public $subject;
	public $message;
	public $unsafe;

	public function getRules() {
		return [
			['email, message', 'required'],
			['subject, unsafe', 'safe', 'scene' => 'anonymous'],
			['subject, reply', 'required', 'scene' => 'realname'],
		];
	}

	public function getLabels() {
		return [
			'email' => '收件邮箱',
			'reply' => '回复邮箱',
			'subject' => '邮件主题',
			'message' => '邮件内容',
		];
	}
}
