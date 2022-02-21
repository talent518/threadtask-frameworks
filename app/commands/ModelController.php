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
        $model->validate(function(int $n) use($model, $__params__) {
            if ($n) {
                ob_start();
                ob_implicit_flush(false);
    
                $this->formatColor("errors: isPerOne\n", static::FG_BLUE);
                var_dump($model->getErrors());

                $str = ob_get_clean();
            } else {
                $str = null;
            }
            $model->validate(function(int $n) use($model, $__params__, $str) {
                if($n) {
                    ob_start();
                    ob_implicit_flush(false);

                    $this->formatColor("errors: isOnly\n", static::FG_BLUE);
                    var_dump($model->getErrors());

                    $str .= ob_get_clean();
                }
                $model->validate(function(int $n) use($model, $__params__, $str) {
                    ob_start();
                    ob_implicit_flush(false);

                    if($n) {
                        $this->formatColor("errors: isPerOne isOnly\n", static::FG_BLUE);
                        var_dump($model->getErrors());
                    }


                    $model->attributes = $__params__;
                    $this->formatColor("attributes\n", static::FG_BLUE);
                    var_dump($model->attributes);

                    echo $str . ob_get_clean();
                }, true, true);
            }, false, true);
        }, true);

        /////////////////////////////////////

        $model = new FormDemo();
        $model->setScene('realname');
        $model->validate(function($n) use($model, $__params__) {
            ob_start();
            ob_implicit_flush(false);

            $this->formatColor("\nrealname scene validator\n", static::FG_RED);
            if ($n) {
                $this->formatColor("errors\n", static::FG_BLUE);
                var_dump($model->getErrors());
            }
            
            $model->attributes = $__params__;
            $this->formatColor("attributes\n", static::FG_BLUE);
            var_dump($model->attributes);

            $str = ob_get_clean();
    
            $model->validate(function($n) use($model, $str) {
                if($n == 0) return;

                ob_start();
                ob_implicit_flush(false);
                $this->formatColor("errors\n", static::FG_BLUE);
                var_dump($model->getErrors());
                echo $str . ob_get_clean();
            });
        });
	}

}
