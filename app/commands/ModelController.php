<?php
namespace app\commands;

use app\models\db\Demo as MySQLDemo;
use app\models\forms\Demo as FormDemo;
use fwe\console\Controller;

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

	/**
	 * MySQL表单验证，以及表记录的增、删、查、改
	 *
	 * @param array $__params__
	 * @return void
	 */
	public function actionMysql(array $__params__) {
		$model = new MySQLDemo();
		$model->scene = 'new';
		$model->attributes = $__params__;
		var_dump($model->attributes);
		$model->validate(function($n) use($model) {
			if($n) {
				$this->formatColor("errors\n", static::FG_BLUE);
				var_dump($model->getErrors());
			}
		});
		
		echo "============================\n";
		
		$page = random_int(1, 20);
		$total = random_int(1, 1000);
		$size = random_int(1, 50);

		var_dump(compact('page', 'total', 'size'));
		
		echo "----------------------------\n";
		
		$query = MySQLDemo::find()->prefix('DISTINCT')
		->select('*', 'count(u.uid) users')
		->whereArgs('AND', ['=', 'uid', $model->uid], ['=', 'username', $model->username])
		->leftJoin('user', 'u', 'd.uid=u.uid')
		->group('d.uid', 'd.username')
		->having('users > ?', [0])
		->page($page, $total, $size, $pages);
		
		var_dump(compact('page', 'total', 'size', 'pages'), $query->build(), $query->makeSQL());
		
		echo "============================\n";
		
		$query = MySQLDemo::find()
		->select('uid')
		->whereArgs('and', ['between', 'a', 1, 5], ['or', ['=', 'b', 1], ['>', 'c', 5], ['and', ['>=', 'd', 1], ['<=', 'd', 10]], ['!=', 'e', 1]], ['=', 'isdel', 0], ['=', 'f', null], ['!=', 'g', null])
		->group('d.uid')
		->havingArgs('or', ['>', 'count(1)', 0], ['>', 'avg(d.score)', 0]);
		var_dump($result=$query->build(), $query->makeSQL());

		echo "============================\n";
		
		$query = MySQLDemo::find()
		->whereArgs('and', ['in', 'uid', $result], ['=', 'uid', $result], ['not', $result], ['like', 'd', ['a', 'b', 'c\'d', "'\r'\n\"", '中国'], false, true]);
		var_dump($query->build(), $query->makeSQL());
	}

}
