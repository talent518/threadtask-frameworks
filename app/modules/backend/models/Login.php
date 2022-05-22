<?php
namespace app\modules\backend\models;

use fwe\base\Model;

/**
 * @method static Login create(array $params = [])
 * @property User $user
 */
class Login extends Model {
	public $username, $email, $password;
	
	protected $user;
	
	/**
	 * @return User
	 */
	public function getUser() {
		return $this->user;
	}

	protected function getLabels() {
		return [
			'username' => '用户名',
			'email' => '邮箱',
			'password' => '密码',
		];
	}

	public function getRules() {
		return [
			['password', 'required'],
			['username', 'required', 'scene' => 'username'],
			['username, password', 'method', 'method' => 'validUser', 'scene' => 'username'],
			['email', 'required', 'scene' => 'email'],
			['email, password', 'method', 'method' => 'validUser', 'scene' => 'email'],
		];
	}
	
	public function validUser(int $ret) {
		if($ret !== 2) {
			return 0;
		}
		
		return function(callable $ok) {
			$db = db()->pop();
			User::find()->whereArgs('=', $this->scene, $this->{$this->scene})->fetchOne($db, 'user')->goAsync(
				function(?User $user) use($db, $ok) {
					$this->user = $user;
					if(!$user || $user->password !== md5(md5($this->password) . $user->salt)) {
						$db->push();
						$this->addError('error', ($this->scene === 'username' ? '用户名或密码错误' : '邮箱或密码错误'));
						$ok(1);
					} else {
						$user->loginTime = date('Y-m-d H:i:s');
						$user->loginTimes ++;
						$user->update($db)->goAsync(
							function() use($db, $ok) {
								$db->push();
								$ok(0);
							},
							function($e) use($db, $ok) {
								$db->push();
								$ok(0, $e->getMessage());
							}
						);
					}
				},
				function($e) use($db, $ok) {
					$db->push();
					$ok(0, $e->getMessage());
				}
			);
		};
	}
}
