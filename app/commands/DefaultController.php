<?php
namespace app\commands;

use fwe\base\ITask;
use fwe\console\Controller;
use fwe\curl\FtpRequest;
use fwe\curl\Request;
use fwe\db\IEvent;
use fwe\db\MySQLConnection;
use fwe\utils\StringHelper;

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
		db()->pop()->asyncPrepare("SELECT * FROM clazz WHERE cno>?", [$id], ['style'=>IEvent::FETCH_ALL])->asyncPrepare("REPLACE INTO clazz (cno,cname,cdesc)VALUES(?,?,?)", [$newId,$newName,$newDesc])->asyncPrepare("SELECT * FROM clazz WHERE cno>?", [$id], ['keyBy'=>'cname', 'valueBy' => 'cdesc', 'style'=>IEvent::FETCH_ALL])->goAsync(function($select, $replace, $clazz) use($t) {
			$this->formatColor('DATA1: ', self::FG_GREEN);

			$t = microtime(true) - $t;
			var_dump(get_defined_vars());
		}, function(MySQLConnection $db, $data, $e) use($t) {
			$this->formatColor('ERR1: ', self::FG_RED);

			$t = microtime(true) - $t;
			var_dump(compact('data', 't'));

			if($db->iUsed === null) $db = $db->pool->pop();
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
		db()->pop()->asyncQuery("SHOW TABLES LIKE 'clazz'", ['callback'=>function($data, $db) use($id,$table) {
			$this->formatColor('CALL1: ', self::FG_BLUE);
			var_dump($data);
			if($data) {
				$db->asyncPrepare("SELECT * FROM clazz WHERE cno=?", [$id], ['style'=>IEvent::FETCH_ONE]);
				return "OK: $data";
			} else {
				return "ERR: $table";
			}
		}, 'style'=>IEvent::FETCH_COLUMN])->asyncPrepare("SELECT TABLE_NAME FROM information_schema.TABLES WHERE TABLE_SCHEMA=database() AND TABLE_NAME LIKE ?", [$table], ['callback'=>function($data, $db) use($table) {
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
	 * 根据$isAsync参数是否进行异步Redis请求
	 * 
	 * @param bool $isAsync
	 */
	public function actionRedis(bool $isAsync = false) {
		if($isAsync) {
			redis()->pop()->beginAsync()->setAsyncKey('keys')->keys('*')->setAsyncKey('cmdInfo')->commandInfo("keys", "info")->get('inc')->incrby('inc', 1)->goAsync(function($data, $keys, $cmdInfo, $get, $incr) {
				$this->formatColor('CALL: ', self::FG_BLUE);
				var_dump(get_defined_vars());
			}, function($data) {
				$this->formatColor('ERR: ', self::FG_BLUE);
				var_dump($data);
			});
		} else {
			$db = redis()->pop();
			var_dump($db->keys('*'), $db->commandInfo("keys", "info"));
			$db->pool->push($db);
		}
	}
	
	/**
	 * Redis订阅
	 */
	public function actionPsubscribe() {
		$db = redis()->pop();
		var_dump($db->psubscribe("__keyspace@{$db->database}__:*", "__keyevent@{$db->database}__:*"));
		$db->bindReadEvent(function($arg) {
			$t = microtime(true);
			echo "$t\n";
			var_dump($arg);
		});
		return false;
	}
	
	const CURL_COUNT = 5;

	/**
	 * 异步curl请求: GET
	 */
	public function actionCurl() {
		$data = [];
		for($i=0; $i<self::CURL_COUNT; $i++) {
			$req = new Request('https://www.baidu.com/#'.$i);
			$req->addHeader('index', $i);
			curl()->make($req, function($res, $req) use(&$data) {
				$res = $res->properties;
				$req = $req->properties;
				$data[] = compact('req', 'res');
				if(count($data) == self::CURL_COUNT) {
					var_export($data);
				}
			});
		}
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
				$this->mkdir($this->path);
			}

			private function mkdir(string $path) {
				return is_dir($path) || mkdir($path, 0755, true);
			}

			public function run($arg) {
				printf("\033[2KRuns: %d\r", $this->run);

				if(is_array($arg)) {
					list($url, $file, $isFile) = $arg;
					$req = new Request($url);
					if($isFile) {
						$req->save2File($this->path . '/' . $file, true);
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
						$this->mkdir($this->path . '/' . $file);
						$this->push([$url, $file, false]);
					} else {
						$this->push([$url, $file, true]);
					}
				}
			}
			
			public function gnuDown($res, $req) {
				printf("\033[2KRuns: %d, Tries: %d, URL: %s, Size: %s, Status: %d, errno: %d, error: %s\n", $this->run, $req->args, $req->url, StringHelper::formatBytes($res->fileSize), $res->status, $res->errno, $res->error);

				if(($res->errno || $res->status === 416) && (++ $req->args) <= $this->tries) {
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

}
