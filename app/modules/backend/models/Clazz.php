<?php

namespace app\modules\backend\models;

/**
 * 由fwe\web\GeneratorController生成的代码
 *
 * @method static Clazz create(array $params = [])
 *
 * @property integer $cno 分类ID
 * @property string $cname 分类名
 * @property string $cdesc 描述
 */
class Clazz extends \fwe\db\MySQLModel {
	public static function tableName() {
		return 'clazz';
	}
	
	public static function priKeys() {
		return ['cno'];
	}
	
	public static function searchKeys() {
		return [
			'cno' => '=',
			'cname' => 'like',
			'cdesc' => 'like',
		];
	}
	
	protected $attributes = [
		'cno' => null,
		'cname' => null,
		'cdesc' => null,
	];
	
	protected function getAttributeNames() {
		return ['cno', 'cname', 'cdesc'];
	}
	
	protected function getLabels() {
		return [
			'cno' => '分类ID',
			'cname' => '分类名',
			'cdesc' => '描述',
		];
	}
	
	public function getRules() {
		return [
			['cname', 'required'],
			['cno', 'string', 'max' => 10],
			['cname', 'string', 'max' => 100],
			['cdesc', 'string', 'max' => 300],
			['cno', 'integer'],
			['cname', 'unique'], // cname
		];
	}
	
	public function setScene(?string $scene) {
		parent::setScene($scene);
		
		$this->safeAttributes['cname'] = $this->safeAttributes['cdesc'] = 1;
	}
}
