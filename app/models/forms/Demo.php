<?php
namespace app\models\forms;

use fwe\base\Model;
use fwe\curl\Request;

/**
 * @method static Demo create(array $params = [])
 */
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
	public $url;
	
	public function getRules() {
		$method = function(bool $isPerOne, bool $isOnly, array $attributes) {
			$vars = compact('isPerOne', 'isOnly', 'attributes');
			return function (callable $ok) use ($vars) {
				echo "Closure\n";
				var_dump($vars);
				curl()->make(new Request($this->url, 'HEAD'), function ($res) use ($ok) {
					if ($res->errno === 0 && $res->status === 200) {
						$ok(0);
					} else {
						$this->addError('url', "url: {$this->url}, errno: {$res->errno}, error: {$res->error}, status: {$res->status}");
						$ok(1);
					}
				});
			};
		};
		return [
			['email, message', 'required'],
			['subject, unsafe', 'safe', 'scene' => 'anonymous'],
			['subject, reply', 'required', 'scene' => 'realname'],
			['email', 'email'],
			['reply', 'fullemail'],
			['password', 'safe'],
			['repasswd', 'compare', 'compareAttribute' => 'password'],
			['repasswd2', 'compare', 'compareValue' => function(){return $this->password;}],
			['unsafe', 'compare', 'compareValue' => '0'],
			['age', 'required'],
			['age', 'compare', 'operator' => '>=', 'compareValue' => 1, 'isNumeric' => true],
			['url', 'url'],
			['url', 'method', 'method' => $method],
			['url', 'method', 'method' => 'validUrl'],
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
	
	public function validUrl(bool $isPerOne, bool $isOnly, array $attributes) {
		$vars = compact('isPerOne', 'isOnly', 'attributes');
		
		return function(callable $ok) use ($vars) {
			echo "Method\n";
			var_dump($vars);
			$ok(0);
		};
	}
}
