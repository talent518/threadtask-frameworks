<?php
namespace app\commands;

use fwe\base\Application;
use fwe\base\ITask;
use fwe\console\Controller;
use fwe\curl\FtpRequest;
use fwe\curl\Request;
use fwe\db\IEvent;
use fwe\db\MySQLConnection;
use fwe\utils\FileHelper;
use fwe\utils\StringHelper;
use fwe\fibers\MySQLFiber;
use fwe\fibers\RedisFiber;

class DefaultController extends Controller {

	/**
	 * Hello World
	 */
	public function actionIndex() {
		echo "Hello World\n";
	}
	
	/**
	 * Hello-World
	 */
	public function actionHelloWorld(string $methodName) {
		echo "Hello-World\nactionID: {$this->actionID}\nmethodName: $methodName\n";
	}
	
	/**
	 * 异步执行SQL示例
	 */
	public function actionQuery(string $table='clazz') {
		$t = microtime(true);
		db()->pop()->asyncQuery("SHOW TABLES", ['style'=>IEvent::FETCH_COLUMN_ALL])->asyncQuery("SHOW GLOBAL VARIABLES LIKE '%timeout%'", ['keyBy'=>'Variable_name', 'valueBy' => 'Value', 'style'=>IEvent::FETCH_ALL])->goAsync(function($tables, $variables) use($t) {
			$t = microtime(true) - $t;
			var_dump(get_defined_vars());
		}, function($data) use($t) {
			$t = microtime(true) - $t;
			var_dump(get_defined_vars());
			return false;
		});
			
		$t2 = microtime(true);
		db()->pop()->asyncQuery("SHOW CREATE TABLE `$table`", ['style'=>IEvent::FETCH_COLUMN,'col'=>1])->asyncQuery("SHOW FULL PROCESSLIST", ['style'=>IEvent::FETCH_ALL])->goAsync(function($sql, $list) use($t2) {
			$t2 = microtime(true) - $t2;
			var_dump(get_defined_vars());
		}, function($data) use($t2) {
			$t2 = microtime(true) - $t2;
			var_dump($data);
			return false;
		});
	}
	
	/**
	 * 异步执行预处理SQL示例
	 * 
	 * @param int $id
	 * @param string $table
	 * @param int $newId
	 * @param string $newName
	 * @param string $newDesc
	 */
	public function actionPrepare(int $id = 0, string $table='clazz', int $newId=5, string $newName='Async Prepare', string $newDesc='Test Insert') {
		$t = microtime(true);
		db()->pop()->asyncPrepare("SELECT * FROM `$table` WHERE cno>?", [$id], ['style'=>IEvent::FETCH_ALL])->asyncPrepare("REPLACE INTO clazz (cno,cname,cdesc)VALUES(?,?,?)", [$newId,$newName,$newDesc])->asyncPrepare("SELECT * FROM clazz WHERE cno>?", [$id], ['keyBy'=>'cname', 'valueBy' => 'cdesc', 'style'=>IEvent::FETCH_ALL])->goAsync(function($select, $replace, $clazz) use($t) {
			$this->formatColor('DATA1: ', self::FG_GREEN);

			$t = microtime(true) - $t;
			var_dump(get_defined_vars());
		}, function(MySQLConnection $db, $data, $e) use($t) {
			$this->formatColor('ERR1: ', self::FG_RED);

			$t = microtime(true) - $t;
			var_dump(compact('data', 't'));

			$db->reset()->asyncQuery("SHOW TABLES", ['style'=>IEvent::FETCH_COLUMN_ALL])->goAsync(function($tables = null) {
				$this->formatColor('DATA2: ', self::FG_GREEN);
				var_dump($tables);
			}, function($data, $e, $db) {
				$this->formatColor('ERR2: ', self::FG_RED);
				var_dump($data);
			}, 3);
			return $db->iUsed === null;
		}, 3);
	}
	
	/**
	 * 单个异步SQL事件的回调操作。
	 * 
	 * @param int $id
	 * @param string $table
	 */
	public function actionCallback(int $id = 1, string $table='%us%') {
		$t = microtime(true);
		db()->pop()->asyncQuery("SHOW TABLES LIKE 'clazz'", ['success'=>function($data, $db) use($id,$table) {
			$this->formatColor('CALL1: ', self::FG_BLUE);
			var_dump($data);
			if($data) {
				$db->asyncPrepare("SELECT * FROM clazz WHERE cno=?", [$id], ['style'=>IEvent::FETCH_ONE]);
				return "OK: $data";
			} else {
				return "ERR: $table";
			}
		}, 'style'=>IEvent::FETCH_COLUMN])->asyncPrepare("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=database() AND TABLE_NAME LIKE ?", [$table], ['success'=>function($data, $db) use($table) {
			$this->formatColor('CALL2: ', self::FG_BLUE);
			var_dump($data);
			if($data) {
				$db->asyncQuery("SELECT * FROM `$data` LIMIT 1", ['style'=>IEvent::FETCH_ONE, 'type'=>IEvent::TYPE_OBJ]);
				return "OK: $data";
			} else {
				return "ERR: $table";
			}
		}, 'style'=>IEvent::FETCH_COLUMN])->goAsync(function($data) use($t) {
			$this->formatColor('DATA: ', self::FG_GREEN);
			var_dump($data, microtime(true)-$t);
		}, function($data) use($t) {
			$this->formatColor('ERR: ', self::FG_RED);
			var_dump($data, microtime(true)-$t);
		});
	}

	/**
	 * 查询表字段和索引列表信息
	 *
	 * @return void
	 */
	public function actionTable(string $table) {
		if(!preg_match('/^[0-9a-zA-Z_-]+$/', $table)) {
			trigger_error("`{$table}` 表名不合法", E_USER_ERROR);
		}

		db()->pop()
		->asyncQuery("SHOW FIELDS FROM `$table`", ['key'=>'fields', 'style'=>IEvent::FETCH_ALL])
		->asyncQuery("SHOW INDEX FROM `$table`", ['key'=>'indexes', 'style'=>IEvent::FETCH_ALL])
		->goAsync(function($fields, $indexes) {
			$json = json_encode(get_defined_vars(), JSON_PRETTY_PRINT);
			echo "SUCCESS: {$json}\n";
		}, function(array $data) {
			$json = json_encode($data, JSON_PRETTY_PRINT);
			echo "ERROR: {$json}\n";
		});
	}
	
	/**
	 * 根据$isAsync参数是否进行异步Redis请求
	 * 
	 * @param bool $isAsync
	 */
	public function actionRedis(bool $isAsync = false) {
		if($isAsync) {
			$redis = redis()->pop();
			$redis->beginAsync()->setAsyncKey('keys')->keys('*')->setAsyncKey('cmdInfo')->commandInfo("keys", "info")->get('inc')->incrby('inc', 1)->goAsync(function($data, $keys, $cmdInfo, $get, $incr) use($redis) {
				$this->formatColor('CALL: ', self::FG_BLUE);
				var_dump(get_defined_vars());
				$redis->push();
			}, function($data) use($redis) {
				$this->formatColor('ERR: ', self::FG_BLUE);
				var_dump($data);
				$redis->push();
			});
		} else {
			$db = redis()->pop();
			var_dump($db->keys('*'), $db->commandInfo("keys", "info"));
			$db->push($db);
		}
	}
	
	/**
	 * Redis订阅
	 */
	public function actionPsubscribe(float $timeout = -1) {
		$db = redis()->pop();
		var_dump($db->psubscribe("__keyspace@{$db->database}__:*", "__keyevent@{$db->database}__:*"));
		$db->bindReadEvent(function($arg) {
			$t = microtime(true);
			echo "$t\n";
			var_dump($arg);
		}, null, $timeout);
		return false;
	}

	/**
	 * 异步curl请求: GET
	 */
	public function actionCurl(string $url = 'https://www.baidu.com/', int $count = 5) {
		$t = microtime(true);
		$data = [];
		for($i=0; $i<$count; $i++) {
			$req = new Request("$url#$i");
			$req->addHeader('index', $i);
			curl()->make($req, function($res, $req) use(&$data, $count, $t) {
				$i = $req->resKey;
				$res = $res->properties;
				$req = $req->properties;
				$data[$i] = compact('req', 'res');
				if(count($data) == $count) {
					var_export($data);
					$t = microtime(true) - $t;
					echo "run time: $t\n";
				}
			});
		}
		echo "curl:end\n";
		return false;
	}
	
	/**
	 * 异步curl文件下载
	 */
	public function actionDownload(string $url, string $file, bool $isAppend = false) {
		if(preg_match('/^https?:\/\//i', $url)) {
			$req = new Request($url);
			$req->addHeader('File', $file);
		} elseif(preg_match('/^ftps?:\/\//i', $url)) {
			$req = new FtpRequest($url);
		} else {
			exit("not support protocol: $url\n");
		}
		$req->save2File($file, $isAppend);
		curl()->make($req, function($res, $req) {
			$res = $res->properties;
			$req = $req->properties;
			echo "\n";
			var_export(compact('req', 'res'));
		});
	}
	
	/**
	 * 并行下载https://ftp.gnu.org/pub/gnu/下的所有文件
	 */
	public function actionGnu(string $url = 'https://ftp.gnu.org/pub/gnu/', string $path = '@app/runtime/gnu', int $max = 5, int $tries = 5) {
		$task = new class($max, $tries, $path) extends ITask {
			private $tries;
			private $path;
			public function __construct(int $max, int $tries, string $path) {
				parent::__construct($max);

				$this->tries = $tries;
				$this->path = \Fwe::getAlias($path);
				FileHelper::mkdir($this->path);
			}

			public function run($arg) {
				printf("\033[2KRuns: %d\r", $this->run);

				if(is_array($arg)) {
					list($url, $file, $isFile) = $arg;
					$req = new Request($url);
					if($isFile) {
						$req->save2File($this->path . '/' . $file);
						$req->args = 0;
						$req->setOption(CURLOPT_TIMEOUT, 0);
						$method = 'gnuDown';
					} else {
						$req->args = $file;
						$method = 'gnuList';
					}
				} else {
					$req = new Request($arg);
					$method = 'gnuList';
				}
				curl()->make($req, [$this, $method]);
			}

			public function gnuList($res, $req) {
				if(is_array($req->args)) {
					list($prefix, $tries) = $req->args;
				} else {
					$prefix = $req->args;
					$tries = 0;
				}

				printf("\033[2KRuns: %d, Tries: %d, URL: %s, Status: %d, errno: %d, error: %s\n", $this->run, $tries, $req->url, $res->status, $res->errno, $res->error);
				
				if($res->errno && (++ $tries) <= $this->tries) {
					$req->args = [$prefix, $tries];
					curl()->make($req, [$this, 'gnuList']);
					return;
				}
				
				$this->shift();

				$doc = new \DOMDocument();
				$doc->loadHTML($res->data);
				$links = $doc->getElementsByTagName('a');
				foreach($links as $link) {
					if(!$link->hasAttributes()) continue;
					$attr = $link->attributes->getNamedItem('href');
					if(!$attr) continue;
					$href = $attr->value;
					if(strpos($href, '..') !== false || preg_match('/^(\?|\/|#|https?:\/\/|ftp:\/\/)/', $href)) continue;

					$url = $req->url . $href;
					$file = $prefix . urldecode($href);
					if(substr($href, -1) === '/') {
						FileHelper::mkdir($this->path . '/' . $file);
						$this->push([$url, $file, false]);
					} else {
						$this->push([$url, $file, true]);
					}
				}
			}
			
			public function gnuDown($res, $req) {
				printf("\033[2KRuns: %d, Tries: %d, URL: %s, Size: %s, Status: %d, errno: %d, error: %s\n", $this->run, $req->args, $req->url, StringHelper::formatBytes($res->fileSize), $res->status, $res->errno, $res->error);

				if(($res->errno || $res->status >= 400) && (++ $req->args) <= $this->tries) {
					if($res->status === 416) {
						$req->save2File($res->file);
					}
					curl()->make($req, [$this, 'gnuDown']);
				} else {
					$this->shift();
				}
			}
		};
		$task->push($url);
		return false;
	}
	
	/**
	 * 测试日志相关函数
	 */
	public function actionLog() {
		\Fwe::$app->log('test log', Application::LOG_INFO);
		\Fwe::$app->error('test log');
		\Fwe::$app->warn('test log');
		\Fwe::$app->info('test log');
		\Fwe::$app->debug('test log');
		\Fwe::$app->verbose('test log');
		
		$n = \Fwe::$app->logCount();
		echo "logCount: $n\n";
	}
	
	private $_events = [];
	
	/**
	 * 异步读取文件：会报 “Operation not permitted”错误。
	 */
	public function actionFile(string $path) {
		$fp = fopen($path, 'r+');
		if(!$fp) return;
		stream_set_blocking($fp, false);
		stream_set_read_buffer($fp, 0);
		
		$event = null;
		$this->_events[] = &$event;
		$event = new \EventBufferEvent(\Fwe::$base, $fp, 0, function($bev, $arg) {
			$len = strlen($bev->read(16 * 1024));
			echo "read: $len\n";
		}, null, function($bev, $event, $arg) use($fp) {
			if($event & (\EventBufferEvent::EOF | \EventBufferEvent::TIMEOUT | \EventBufferEvent::ERROR)) {
				fclose($fp);
				$bev->free();
				unset($this->_events[$arg]);
				\Fwe::$app->events --;
				
				echo "ended: $arg $events\n";
			}
		}, array_key_last($this->_events));
		
		$event->enable(\Event::READ);
		\Fwe::$app->events ++;
		
		return false;
	}
	
	/**
	 * 使用Fiber进行异步通信：PHP 8.1
	 */
	public function actionFiber() {
		(new \Fiber(function() {
			$db = MySQLFiber::pop();
			$data[] = $db->beginTransaction();
			try {
				$data[] = $db->query('SHOW TABLES');
				$data[] = $db->prepare('SELECT * FROM user WHERE username = ?', ['admin']);
				$data[] = $db->prepare('UPDATE user SET loginTime = NOW(), loginTimes = loginTimes + 1 WHERE username = ?', ['admin']);
				$data[] = $db->prepare('SELECT * FROM user WHERE username = ?', ['admin']);
				$data[] = $db->commit();
			} catch(\Throwable $e) {
				$data[] = $db->rollback();
				\Fwe::$app->error($e, 'fiber');
			}
			var_dump($data);
		}))->start();
		
		(new \Fiber(function() {
			$db = RedisFiber::pop();
			$data[] = $db->keys('*');
			$data[] = $db->get('fiber');
			$data[] = $db->set('fiber', random_int(0, 1000));
			$data[] = $db->get('fiber');
			var_dump($data);
		}))->start();
	}

}
