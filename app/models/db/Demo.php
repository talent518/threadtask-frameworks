<?php
namespace app\models\db;

use fwe\db\MySQLModel;

class Demo extends MySQLModel {
    protected $attributes = [
        'username' => '',
        'password' => '',
    ];

    protected function getAttributeNames() {
        return ['username', 'password'];
    }

    protected function getLabels() {
        return [];
    }

    public function getRules() {
        return [
            ['username, password', 'required'],
        ];
    }
}
