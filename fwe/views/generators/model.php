<?php
echo "<?php\n";

/**
 * @var $this \fwe\base\Controller 控制器对象
 * @var $class string 模块类全名
 * @var $base string 模块父类全名
 * @var $namespace string 数据模块命名空间
 * @var $className string 数据模块类名
 * @var $table string 表名
 * @var $comment string 表注释
 * @var $fields array 数据表字段列表
 * @var $indexes array 数据表唯一索引列表
 * @var $isComment bool 是否使用表字段注释作为属性标签
 * @var $isFiber bool 是否使用\Fiber类: PHP 8.1
 */

$classes = preg_split('/[^a-zA-Z0-9]+/', $class, -1, PREG_SPLIT_NO_EMPTY);
$className = array_pop($classes);
$namespace = implode('\\', $classes);

$bases = preg_split('/[^a-zA-Z0-9]+/', $base, -1, PREG_SPLIT_NO_EMPTY);
$baseClass = implode('\\', $bases);

$requireds = $sizes = $formats = [];
$emptyStrs = [];
foreach($fields as &$field) {
	if(!$field['allowNull'] && !$field['autoIncrement']) {
		$requireds[] = $field['name'];
	}
	if($field['size']) {
		$sizes[$field['type']==='char' ? 0 : 1][$field['size']][] = $field['name'];
	}
	switch($field['type']) {
		case 'date':
			$formats['date'][] = $field['name'];
			$emptyStrs[] = $field['name'];
			break;
		case 'time':
			$formats['time'][] = $field['name'];
			$emptyStrs[] = $field['name'];
			break;
		case 'datetime':
		case 'timestamp':
			$formats['datetime'][] = $field['name'];
			$emptyStrs[] = $field['name'];
			break;
		default:
			break;
	}
	switch($field['phpType']) {
		case 'integer':
		case 'double':
			$formats[$field['phpType']][] = $field['name'];
			$field['search'] = '=';
			break;
		case 'boolean':
			$field['enumValues'] = [0,1];
			$field['search'] = '=';
			break;
		default:
			$field['search'] = 'like';
			break;
	}
	if(!empty($field['enumValues'])) {
		$field['search'] = '=';
	}
}
unset($field);

?>

namespace <?=$namespace?>;

use fwe\<?php if($isFiber):?>fibers<?php else:?>db<?php endif;?>\MySQLTrait;

/**
<?php if($comment):?>
 * <?="$comment\n"?>
 *
<?php endif;?>
 * 由<?=get_class($this)?>生成的代码
 *
 * @method static <?=$className?> create(array $params = [])
 *
<?php foreach($fields as $field):
$ws = $field['comment'] ? ' ' : '';
?>
 * @property <?=$field['phpType']?> $<?="{$field['name']}{$ws}{$field['comment']}\n"?>
<?php endforeach;?>
 */
class <?=$className?> extends \<?=$baseClass?> {
	use MySQLTrait;
	
	public static function tableName() {
		return '<?=$table?>';
	}
	
	public static function priKeys() {
		return [<?php if(!empty($indexes['PRIMARY'])):?>'<?=implode("', '", $indexes['PRIMARY'])?>'<?php endif;?>];
	}
	
	public static function searchKeys() {
		return [
<?php foreach($fields as $field):?>
			'<?=$field['name']?>' => '<?=$field['search']?>',
<?php endforeach;?>
		];
	}
	
	protected $attributes = [
<?php foreach($fields as $field):?>
		'<?=$field['name']?>' => <?=$field['phpValue']?>,
<?php endforeach;?>
	];
	
	protected function getAttributeNames() {
		return ['<?=implode("', '", array_keys($fields))?>'];
	}
	
	protected function getLabels() {
		return [
<?php foreach($fields as $field):?>
			'<?=$field['name']?>' => '<?=$isComment?($field['comment']?:$field['label']):$field['label']?>',
<?php endforeach;?>
		];
	}
	
	public function getRules() {
		return [
<?php if($requireds):?>
			['<?=implode(', ', $requireds)?>', 'required'],
<?php endif;?>
<?php foreach($sizes as $flag => $size):foreach($size as $len=>$field):?>
			['<?=implode(', ', $field)?>', 'string', '<?=$flag?'max':'len'?>' => <?=$len?>],
<?php endforeach;endforeach;?>
<?php foreach($formats as $format => $keys):?>
			['<?=implode(', ', $keys)?>', '<?=$format?>'],
<?php endforeach;?>
<?php foreach($fields as $field):if(!empty($field['enumValues'])):?>
			['<?=$field['name']?>', 'in', 'range' => ['<?=implode("', '", $field['enumValues'])?>']],
<?php endif;endforeach;?>
<?php foreach($indexes as $key => $index):
foreach($index as $i=>$name):
	if($fields[$name]['autoIncrement']) unset($index[$i]);
endforeach;
if($index):
?>
			['<?=implode(', ', $index)?>', 'unique'], // <?="{$key}\n"?>
<?php endif;endforeach;?>
		];
	}
<?php foreach($emptyStrs as $attr):echo "\t\n";?>
	public function set<?=ucfirst($attr)?>($value) {
		$this->attributes['<?=$attr?>'] = ($value === '' ? null : $value);
	}
<?php endforeach;?>
}
