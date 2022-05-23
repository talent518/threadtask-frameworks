<?php
namespace fwe\cache;

class Redis extends Cache {
	public $prefixKey = 'cache:';
	public $redisId = 'redis';
	
	public function __construct(string $prefix = '__cache:redis') {
		parent::__construct($prefix);
	}
	
	public function get(string $key, callable $ok, callable $set, int $expire = 0) {
		$set2 = function(callable $ok) use($set, $key, $expire) { // 当缓存中不存在时尝试从Redis中读取
			$redis = redis($this->redisId)->pop()->beginAsync();
			$redis->get($this->prefixKey . $key)->ttl($this->prefixKey . $key)
			->goAsync(function($value, $ttl) use($redis, $ok, $set, $key, $expire) {
				if($value === null || $ttl === -2 || $ttl === 0) { // 当Redis中不存在时重新获取数据
					try {
						$set(function($value) use($redis, $ok, $key, $expire) {
							$redis->beginAsync()->setex($this->prefixKey . $key, $expire, serialize($value))
							->goAsync(function() use($redis, $ok, $value) {
								$redis->push();
								$ok($value);
							}, function() use($redis, $ok, $value) {
								$redis->push();
								$ok($value);
							});
						});
					} catch(\Throwable $e) {
						\Fwe::$app->error($e, 'cache-redis');
						$ok($e->getMessage());
					}
				} else { // $value为非空，并且$ttl为-1或大于0
					$redis->push();
					$ok(unserialize($value), $ttl > 0 ? $ttl : 0);
				}
			}, function($e) use($redis, $ok) {
				$redis->push();
				$ok($e->getMessage());
			});
		};
		
		return parent::get($key, $ok, $set2, $expire);
	}
	
	public function del(string $key, callable $ok) {
		return parent::del(
			$key,
			function(string $status) use($key, $ok) {
				try {
					$redis = redis($this->redisId)->pop()->beginAsync();
					$redis->del($this->prefixKey . $key)
					->goAsync(function() use($redis, $ok, $status) {
						$redis->push();
						$ok($status);
					}, function($e) use($redis, $ok) {
						$redis->push();
						$ok($e->getMessage());
					});
				} catch(\Throwable $e) {
					\Fwe::$app->error($e, 'cache-redis');
					$ok($e->getMessage());
				}
			}
		);
	}
}
