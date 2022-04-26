<?php
namespace app\commands;

use app\models\db\Demo as MySQLDemo;
use app\models\forms\Demo as FormDemo;
use fwe\console\Controller;

class ModelController extends Controller {
	
	private function dump(...$vars) {
		echo "*********** DUMP ***********\n";
		foreach($vars as $var) {
			$var = json_encode($var, JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
			echo "$var\n";
		}
	}

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
				$this->dump($model->getErrors());

				$str = ob_get_clean();
			} else {
				$str = null;
			}
			$model->validate(function(int $n) use($model, $__params__, $str) {
				if($n) {
					ob_start();
					ob_implicit_flush(false);

					$this->formatColor("errors: isOnly\n", static::FG_BLUE);
					$this->dump($model->getErrors());

					$str .= ob_get_clean();
				}
				$model->validate(function(int $n) use($model, $__params__, $str) {
					ob_start();
					ob_implicit_flush(false);

					if($n) {
						$this->formatColor("errors: isPerOne isOnly\n", static::FG_BLUE);
						$this->dump($model->getErrors());
					}

					$model->attributes = $__params__;
					$this->formatColor("attributes\n", static::FG_BLUE);
					$this->dump($model->attributes);

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
				$this->dump($model->getErrors());
			}
			
			$model->attributes = $__params__;
			$this->formatColor("attributes\n", static::FG_BLUE);
			$this->dump($model->attributes);

			$str = ob_get_clean();
	
			$model->validate(function($n) use($model, $str) {
				if($n == 0) return;

				ob_start();
				ob_implicit_flush(false);
				$this->formatColor("errors\n", static::FG_BLUE);
				$this->dump($model->getErrors());
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
	public function actionMysql(array $__params__, ?int $uid = null, string $username = '', string $password = '', string $email = '', string $error = '') {
		$db = db()->pop();

		$model = new MySQLDemo();
		$model->scene = 'new';
		$model->attributes = compact('uid', 'username', 'password', 'email', 'error');
		$this->dump($model->attributes);
		$model->registerTime = $model->loginTime = date('Y-m-d H:i:s');
		$model->loginTime = 0;
		$model->save(
			$db,
			function(\stdClass $status) use($model) {
			echo "=========== SAVE ===========\n";
			
			$this->dump($status);
			},
			function(\stdClass $status) use($model) {
				echo "=========== SAVE ===========\n";
				
				if($model->hasErrors()) {
					$this->formatColor("ERRORS", static::FG_RED);
					echo ":\n";
					$this->dump($model->getErrors());
				}
				
				$this->dump($status);
			}
		);
		
		echo "============================\n";
		
		$page = random_int(1, 20);
		$total = random_int(1, 1000);
		$size = random_int(1, 50);

		$this->dump(compact('page', 'total', 'size'));
		
		echo "----------------------------\n";
		
		$query = MySQLDemo::find()->prefix('DISTINCT')
		->select('*', 'count(u.uid) users')
		->whereArgs('AND', ['=', 'uid', $model->uid], ['=', 'username', $model->username])
		->leftJoin('user', 'u', 'd.uid=u.uid')
		->groupBy('d.uid', 'd.username')
		->having('users > ?', [0])
		->page($page, $total, $size, $pages);
		
		$this->dump(compact('page', 'total', 'size', 'pages'), $query->build(), $query->makeSQL());
		
		echo "============================\n";
		
		$query = MySQLDemo::find()
		->select('uid')
		->whereArgs('and', ['between', 'a', 1, 5], ['or', ['=', 'b', 1], ['>', 'c', 5], ['and', ['>=', 'd', 1], ['<=', 'd', 10]], ['!=', 'e', 1]], ['=', 'isdel', 0], ['=', 'f', null], ['!=', 'g', null])
		->groupBy('d.uid')
		->havingArgs('or', ['>', 'count(1)', 0], ['>', 'avg(d.score)', 0]);
		$this->dump($result=$query->build(), $query->makeSQL());

		echo "============================\n";
		
		$query = MySQLDemo::find()
		->whereArgs('and', ['in', 'uid', $result], ['=', 'uid', $result], ['not', $result], ['like', 'd', ['a', 'b', 'c\'d', "'\r'\n\"", '中国'], false, true]);
		$this->dump($query->build(), $query->makeSQL());

		MySQLDemo::find()->whereArgs('=', 'uid', $uid)->fetchOne(
			$db,
			null,
			function($rows) {
				echo "======== FETCH ONE =========\n";
				
				$this->dump($rows);
			},
			function($e) {
				echo "======== FETCH ONE =========\n";
				
				echo "$e\n";
			}
		);
		
		MySQLDemo::find()->limit(0, 10)->fetchAll(
			$db,
			null,
			function($rows) {
				echo "======== FETCH ALL =========\n";
				
				$this->dump($rows);
			},
			function($e) {
				echo "======== FETCH ALL =========\n";
				
				echo "$e\n";
			}
		);
		
		MySQLDemo::findById(
			$db,
			$uid,
			function(?MySQLDemo $row) use($db, $error) {
				echo "======== FIND BY ID ========\n";
				
				$this->dump($row);
				
				if($row) {
					$row->error = $error;
					$row->loginTime = date('Y-m-d H:i:s');
					$row->save(
						$db,
						function(\stdClass $status) use($row) {
							echo "=========== SAVE ===========\n";
	
							$this->dump($status);
						},
						function(\stdClass $status) use($row) {
							echo "=========== SAVE ===========\n";
							
							if($row->hasErrors()) {
								$this->formatColor("ERRORS", static::FG_RED);
								echo ":\n";
								$this->dump($row->getErrors());
							}
							
							$this->dump($status);
						}
					);
				}
			},
			function($e) {
				echo "======== FIND BY ID ========\n";
				
				echo "$e\n";
			}
		);
		
		$db->goAsync(function($data) {
			$this->formatColor("OK:\n", static::FG_GREEN);
			var_dump($data);
		}, function($data, $error) {
			$this->formatColor("ERROR:\n", static::FG_GREEN);
			echo "\e";
		});
	}

}
