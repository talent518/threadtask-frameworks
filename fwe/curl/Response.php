<?php
namespace fwe\curl;

/**
 * @property-read string $protocol
 * @property-read integer $status
 * @property-read string $statusText
 * @property-read array $headers
 * @property-read string $data
 * 
 * @property-read integer $dlTotal
 * @property-read integer $dlBytes
 * @property-read integer $upTotal
 * @property-read integer $upBytes
 */
class Response extends IResponse {
	protected $protocol;
	protected $status;
	protected $statusText;
	protected $headers = [];
	protected $data;
	
	/**
	 * @param resource $ch
	 * @param string $header
	 */
	public function headerHandler($ch, $header) {
		if($this->protocol && !preg_match('/^HTTP\/\d(\.\d)?\s+\d+\s+/i', $header)) {
			if(strchr($header, ':') === false) return strlen($header);
			
			list($name, $value) = preg_split('/\:\s*/', $header, 2);

			$value = trim($value);
	
			if(isset($this->headers[$name])) {
				if(is_array($this->headers[$name])) {
					$this->headers[$name][] = $value;
				} else {
					$this->headers[$name] = [$this->headers[$name], $value];
				}
			} else {
				$this->headers[$name] = $value;
			}
		} else {
			list($this->protocol, $status, $statusText) = preg_split('/\s+/', $header, 3);
			$this->status = (int) $status;
			$this->statusText = trim($statusText);
			$this->headers = [];
		}

		return strlen($header);
	}
	
	public function writeHandler($ch, $data) {
		$this->data .= $data;

		return strlen($data);
	}
	
	protected $dlTotal, $dlBytes, $upTotal, $upBytes;
	public function progressHandler($ch, int $dlTotal, int $dlBytes, int $upTotal, int $upBytes) {
		$this->dlTotal = $dlTotal;
		$this->dlBytes = $dlBytes;
		$this->upTotal = $upTotal;
		$this->upBytes = $upBytes;
	}
	
	public function completed() {
		if($this->status < 200 || $this->status >= 400 ) \Fwe::$app->verbose("URL: {$this->url}, status: {$this->status}, data: {$this->data}", 'curl');
	}
}
