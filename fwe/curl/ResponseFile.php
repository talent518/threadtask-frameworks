<?php
namespace fwe\curl;

use fwe\utils\StringHelper;

/**
 * @property-read string $file
 * @property-read boolean $isAppend
 * @property-read integer $size
 * @property-read integer $percent
 * @property-read integer $pgTime
 * @property-read integer $pgSize
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
	
	/**
	 * @var integer
	 */
	protected $size, $fileSize;
	
	public function __construct(string $file, bool $isAppend, int $size = 0) {
		parent::__construct();

		$this->file = $file;
		$this->isAppend = $isAppend;
		$this->fp = @fopen($file, $isAppend ? 'a' : 'w');
		$this->size = $size;
		$this->fileSize = $size;

		$this->pgTime = microtime(true);
	}
	
	public function writeHandler($ch, $data) {
		if(!$this->fp) return 0;
		
		$ret = fwrite($this->fp, $data);
		$this->fileSize += $ret;

		return $ret;
	}

	protected $percent;
	protected $pgTime;
	protected $pgSize = 0;
	public function progressHandler($ch, int $dlTotal, int $dlBytes, int $upTotal, int $upBytes) {
		parent::progressHandler($ch, $dlTotal, $dlBytes, $upTotal, $upBytes);

		if($dlTotal <= 0) return;
		
		$p = round(($dlBytes + $this->size) * 100 / ($dlTotal + $this->size), 1);
		if($p !== $this->percent) {
			$t = microtime(true) - $this->pgTime;
			if($t < 1.0 && $dlTotal != $dlBytes) return;
			
			$bytes = ($dlBytes - $this->pgSize) / $t;
			$this->pgTime += $t;
			$this->pgSize = $dlBytes;
			$this->percent = $p;
			printf("\033[2K%s %s/%s %.1f%% %s/s\r", basename($this->file), StringHelper::formatBytes($dlBytes), StringHelper::formatBytes($dlTotal), $p, StringHelper::formatBytes($bytes));
		}
	}
	
	public function __destruct() {
		if($this->fp) fclose($this->fp);
		$this->fp = null;
	}
}

