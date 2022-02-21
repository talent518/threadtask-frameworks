<?php
namespace app\commands;

use fwe\console\Controller;
use app\models\forms\Demo as FormDemo;

class ModelController extends Controller {

    /**
     * 表单验证器
     */
    public function actionIndex(array $__params__) {
        $this->formatColor("anonymous scene validator\n", static::FG_RED);
        $model = new FormDemo();
        $model->setScene('anonymous');
        if($model->validate(true)) {
            $this->formatColor("errors: isPerOne\n", static::FG_BLUE);
            var_dump($model->getErrors());
        }
        if($model->validate(false, true)) {
            $this->formatColor("errors: isOnly\n", static::FG_BLUE);
            var_dump($model->getErrors());
        }
        if($model->validate(true, true)) {
            $this->formatColor("errors: isPerOne isOnly\n", static::FG_BLUE);
            var_dump($model->getErrors());
        }

        $model->attributes = $__params__;
        $this->formatColor("attributes\n", static::FG_BLUE);
        var_dump($model->attributes);

        /////////////////////////////////////

        $this->formatColor("\nrealname scene validator\n", static::FG_RED);
        $model = new FormDemo();
        $model->setScene('realname');
        if($model->validate()) {
            $this->formatColor("errors\n", static::FG_BLUE);
            var_dump($model->getErrors());
        }

        $model->attributes = $__params__;
        $this->formatColor("attributes\n", static::FG_BLUE);
        var_dump($model->attributes);

        if($model->validate()) {
            $this->formatColor("errors\n", static::FG_BLUE);
            var_dump($model->getErrors());
        }
	}

}
