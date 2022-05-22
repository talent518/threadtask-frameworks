<?php
echo "<?php\n";

/**
 * @var \fwe\base\Controller $this 控制器对象
 * @var $model string 数据模块类全名
 * @var $namespace string 控制器命名空间
 * @var $className string 控制器类名
 * @var $_ mixed 忽略
 */

$modelObj = $model::create();
$searchKeys = $model::searchKeys();
?>
namespace <?=$namespace?>;
use <?=$model?> as Model;
use fwe\db\MySQLConnection;

/**
 * 由<?=get_class($this)?>生成的代码
 *
 * @method static <?=$className?> create(array $params = [])
 */
class <?=$className?> extends \fwe\base\Model {
	protected $_searchKeys = [
<?php foreach($searchKeys as $attr => $oper):?>
		'<?=$attr?>' => '<?=$oper?>',
<?php endforeach;?>
	];
	public function init() {
		$this->setScene('search');
	}
	
<?php foreach($searchKeys as $key => $_):?>
	public $<?=$key?>;
<?php endforeach?>

	protected function getLabels() {
		return [
<?php foreach($searchKeys as $attr => $_):?>
			'<?=$attr?>' => '<?=$modelObj->getLabel($attr)?>',
<?php endforeach;?>
		];
	}

	public function getRules() {
		return [
			['<?=implode(', ', array_keys($searchKeys))?>', 'safe'],
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
