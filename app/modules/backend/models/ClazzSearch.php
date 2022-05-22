<?php
namespace app\modules\backend\models;
use app\modules\backend\models\Clazz as Model;
use fwe\db\MySQLConnection;

/**
 * 由fwe\web\GeneratorController生成的代码
 *
 * @method static ClazzSearch create(array $params = [])
 */
class ClazzSearch extends \fwe\base\Model {
	protected $_searchKeys = [
		'cno' => '=',
		'cname' => 'like',
		'cdesc' => 'like',
	];
	public function init() {
		$this->setScene('search');
	}
	
	public $cno;
	public $cname;
	public $cdesc;

	protected function getLabels() {
		return [
			'cno' => '分类ID',
			'cname' => '分类名',
			'cdesc' => '描述',
		];
	}

	public function getRules() {
		return [
			['cno, cname, cdesc', 'safe'],
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
