<?php
namespace fwe\cache;

class Redis extends Cache {
	public $prefixKey = 'cache:';
	public $redisId = 'redis';
	
	public function __construct(string $prefix = '__cache:redis') {
		parent::__construct($prefix);
	}
	
	public function get(string $key, callable $ok, callable $set, int $expire = 0) {
		$set2 = function(callable $ok) use($set, $key, $expire) {
			try {
				$redis = redis($this->redisId)->pop()->beginAsync();
				$redis->get($this->prefixKey . $key)
				->goAsync(function($value) use($redis, $ok, $set, $key, $expire) {
					if($value === null) {
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
					} else {
						$redis->push();
						$ok(unserialize($value));
					}
				}, function($e) use($redis, $ok, $set) {
					$redis->push();
					$set($ok);
				});
			} catch(\Throwable $e) {
				\Fwe::$app->error($e, 'cache-redis');
				$set($ok);
			}
		};
		
		return parent::get($key, $ok, $set2, $expire);
	}
}
