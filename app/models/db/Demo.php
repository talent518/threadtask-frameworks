<?php
namespace app\models\db;

use fwe\db\MySQLModel;

class Demo extends MySQLModel {
	public static function tableName() {
		return 'demo';
	}

    protected $attributes = [
    	'uid' => 0,
        'username' => '',
        'password' => '',
    ];

    protected function getAttributeNames() {
        return ['uid', 'username', 'password'];
    }

    protected function getLabels() {
        return [];
    }

    public function getRules() {
        return [
        	['uid', 'safe'],
            ['username, password', 'required'],
        ];
    }
}
