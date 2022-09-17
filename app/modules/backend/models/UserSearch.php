<?php
namespace app\modules\backend\models;
use app\modules\backend\models\User as Model;
use fwe\db\MySQLConnection;

/**
 * 由fwe\web\GeneratorController生成的代码
 *
 * @method static UserSearch create(array $params = [])
 */
class UserSearch extends \fwe\base\Model {
	protected $_searchKeys = [
		'uid' => '=',
		'username' => 'like',
		'email' => 'like',
		'password' => 'like',
		'salt' => 'like',
		'registerTime' => 'like',
		'loginTime' => 'like',
		'loginTimes' => '=',
	];
	public function init() {
		$this->setScene('search');
	}
	
	public $uid;
	public $username;
	public $email;
	public $password;
	public $salt;
	public $registerTime;
	public $loginTime;
	public $loginTimes;

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
		];
	}

	public function getRules() {
		return [
			['uid, username, email, password, salt, registerTime, loginTime, loginTimes', 'safe'],
		];
	}
	
	/**
	 * @var int $page
	 * @var int $total
	 * @var int $size
	 * @var int $pages
	 */
	public $page = 1, $total, $size = 20, $pages;
	
	/**
	 * @var string $orderBy 字段名
	 * @var bool $isDesc 是否降序
	 */
	public $orderBy, $isDesc;
	
	/**
	 * @var array
	 */
	public $result = [];

	public function search(MySQLConnection $db, callable $success, callable $error) {
		$args = ['and'];
		foreach($this->_searchKeys as $attr => $oper) {
			$val = $this->$attr;
			if($val !== null && $val !== '') {
				$args[] = [$oper, $attr, $val];
			}
		}
		if(count($args) == 1) $args[] = '1 > 0';
		return Model::find()->select('COUNT(1)')->whereArray($args)
		->fetchColumn(
			$db,
			0,
			'searchCount',
			function($count) use($args, $db) {
				$this->total = $count;
				$orderBy = isset($this->_searchKeys[$this->orderBy]) ? $this->orderBy : array_key_first($this->_searchKeys);
				$isDesc = $this->isDesc ? 'DESC' : 'ASC';
				Model::find()->whereArray($args)->orderBy("$orderBy $isDesc")->page($this->page, $this->total, $this->size, $this->pages)->fetchAll($db, 'searchResult', function(array $result) {
					$this->result = $result;
					return $result;
				});
				return $count;
			},
			$error
		)->goAsync(function() use($success) {
			$success($this);
		}, $error);
	}
}
