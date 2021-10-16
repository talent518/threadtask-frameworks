<?php
namespace fwe\curl;

class FtpRequest extends IRequest {
	
	public function __construct(string $url) {
		parent::__construct($url);
	}
	
	protected $options = [
		CURLOPT_TRANSFERTEXT => false,
	];
	
	public function setOption(int $option, $value) {
		$this->options[$option] = $value;
	}
	
	protected $responseClass;
	
	public function save2File(string $file, bool $isAppend = false, string $class = FtpResponse::class) {
		if($isAppend) {
			$this->options[CURLOPT_RESUME_FROM] = $size = (@filesize($file) ?: 0);
			if($size === 0) $isAppend = false;
		} else {
			$size = 0;
		}
		$this->responseClass = [
			'class' => $class,
			'file' => $file,
			'size' => $size,
			'isAppend' => $isAppend
		];
		return $this;
	}
	
	public function make(&$res) {
		$ch = curl_init($this->url);
		curl_setopt_array($ch, $this->options);

		$res = \Fwe::createObject($this->responseClass);

		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 0);
		curl_setopt($ch, CURLOPT_WRITEFUNCTION, [$res, 'writeHandler']);
		
		curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, [$res, 'progressHandler']);
		curl_setopt($ch, CURLOPT_NOPROGRESS , false);
		
		return $ch;
	}
}

