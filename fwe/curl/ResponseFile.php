<?php
namespace fwe\curl;

/**
 * @property-read string $file
 * @property-read boolean $isAppend
 */
class ResponseFile extends Response {
	/**
	 * @var string
	 */
	protected $file;
	
	/**
	 * @var bool
	 */
	protected $isAppend;
	
	/**
	 * @var resource
	 */
	protected $fp;
	
	public function __construct(string $protocol, string $file, bool $isAppend) {
		parent::__construct($protocol);
		$this->file = $file;
		$this->isAppend = $isAppend;
		$this->fp = @fopen($file, $isAppend ? 'a' : 'w');
	}
	
	public function writeHandler($ch, $data) {
		return $this->fp ? fwrite($this->fp, $data) : 0;
	}
	
	public function __destruct() {
		if($this->fp) fclose($this->fp);
		$this->fp = null;
	}
}

