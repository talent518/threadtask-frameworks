<?php
namespace fwe\curl;

/**
 * @property-read array $properties
 *
 * @property-read string $url
 */
abstract class IRequest {
	
	public $key, $resKey;

	protected $url;

	public function __construct(string $url) {
		$this->url = $url;
	}

	public function __get($name) {
		if($name === 'properties') {
			return get_object_vars($this);
		} else {
			return $this->$name;
		}
	}

	abstract public function setOption(int $option, $value);
	
	abstract public function save2File(string $file, bool $isAppend = false, string $class = '');

	abstract public function make(&$res);
}
