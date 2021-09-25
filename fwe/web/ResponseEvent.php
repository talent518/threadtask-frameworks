<?php
namespace fwe\web;

class ResponseEvent {
	public $protocol;
	public $status;
	public $statusText;
	
	protected $isHeadSent = false;

	public $headers = ['Content-Type'=>'text/html; charset=utf-8', 'Task-Name'=>THREAD_TASK_NAME];
	public $isChunked = false;
	public $isEnd = false;
	public $isWebSocket = false;

	/**
	 * @var RequestEvent
	 */
	protected $request;
	
	public function __construct(RequestEvent $request, string $protocol, int $status = 200, string $statusText = 'OK') {
		$this->request = $request;
		$this->protocol = $protocol;
		$this->status = $status;
		$this->statusText = $statusText;
	}
	
	public function setStatus(int $status, ?string $statusText = null) {
		if($this->isHeadSent) return false;

		$this->status = $status;
		if($statusText !== null) {
			$this->statusText = $statusText;
			return true;
		}
		
		switch ($status) {
		    case 100: $this->statusText = 'Continue'; break;
		    case 101: $this->statusText = 'Switching Protocols'; break;
		    case 200: $this->statusText = 'OK'; break;
		    case 201: $this->statusText = 'Created'; break;
		    case 202: $this->statusText = 'Accepted'; break;
		    case 203: $this->statusText = 'Non-Authoritative Information'; break;
		    case 204: $this->statusText = 'No Content'; break;
		    case 205: $this->statusText = 'Reset Content'; break;
		    case 206: $this->statusText = 'Partial Content'; break;
		    case 300: $this->statusText = 'Multiple Choices'; break;
		    case 301: $this->statusText = 'Moved Permanently'; break;
		    case 302: $this->statusText = 'Moved Temporarily'; break;
		    case 303: $this->statusText = 'See Other'; break;
		    case 304: $this->statusText = 'Not Modified'; break;
		    case 305: $this->statusText = 'Use Proxy'; break;
		    case 400: $this->statusText = 'Bad Request'; break;
		    case 401: $this->statusText = 'Unauthorized'; break;
		    case 402: $this->statusText = 'Payment Required'; break;
		    case 403: $this->statusText = 'Forbidden'; break;
		    case 404: $this->statusText = 'Not Found'; break;
		    case 405: $this->statusText = 'Method Not Allowed'; break;
		    case 406: $this->statusText = 'Not Acceptable'; break;
		    case 407: $this->statusText = 'Proxy Authentication Required'; break;
		    case 408: $this->statusText = 'Request Time-out'; break;
		    case 409: $this->statusText = 'Conflict'; break;
		    case 410: $this->statusText = 'Gone'; break;
		    case 411: $this->statusText = 'Length Required'; break;
		    case 412: $this->statusText = 'Precondition Failed'; break;
		    case 413: $this->statusText = 'Request Entity Too Large'; break;
		    case 414: $this->statusText = 'Request-URI Too Large'; break;
		    case 415: $this->statusText = 'Unsupported Media Type'; break;
		    case 500: $this->statusText = 'Internal Server Error'; break;
		    case 501: $this->statusText = 'Not Implemented'; break;
		    case 502: $this->statusText = 'Bad Gateway'; break;
		    case 503: $this->statusText = 'Service Unavailable'; break;
		    case 504: $this->statusText = 'Gateway Time-out'; break;
		    case 505: $this->statusText = 'HTTP Version not supported'; break;
		    default : $this->statusText = 'Unknown'; break;
		}
	}
	
	public function isHeadSent() {
		return $this->isHeadSent;
	}
	
	public function headSend(int $bodyLen = 0): bool {
		if($this->isHeadSent) return true;
		$this->isHeadSent = true;
		
		if($bodyLen >= 0) {
			$this->headers['Content-Length'] = $bodyLen;
		} else {
			$this->headers['Transfer-Encoding'] = 'chunked';
			$this->isChunked = true;
		}
		
		$this->headers['Date'] = gmdate('D, d-M-Y H:i:s T');
		
		ob_start();
		ob_implicit_flush(false);
		echo $this->protocol, ' ', $this->status, ' ', $this->statusText, "\r\n";
		foreach($this->headers as $name=>$value) {
			if(is_array($value)) {
				foreach($value as $val) {
					echo $name, ': ', (string) $val, "\r\n";
				}
			} else {
				echo $name, ': ', $value, "\r\n";
			}
		}
		echo "\r\n";
		$buf = ob_get_clean();
		
		return $this->send($buf);
	}
	
	public function setContentType(string $type) {
		$this->headers['Content-Type'] = $type;
	}
	
	public function setCookie(string $name, ?string $value, int $expires = 0, string $path = '', string $domain = '', bool $secure = false, bool $httponly = false, string $samesite = '') {
		$this->setRawCookie($name, $value === null ? null : urlencode($value), $expires, $path, $domain, $secure, $httponly, $samesite);
	}
	
	public function setRawCookie(string $name, ?string $value, int $expires = 0, string $path = '', string $domain = '', bool $secure = false, bool $httponly = false, string $samesite = '') {
		if($value === null || $value === '') {
			$expire = gmdate('D, d-M-Y H:i:s T', 1);
			$cookie = "$name=deleted; expires=$expire; Max-Age=0";
		} else {
			$cookie = "$name=$value";
			if($expires > 0) {
				$cookie .= '; expires=' . gmdate('D, d-M-Y H:i:s T', $expires);
				$diff = $expires - time();
				if($diff < 0) $diff = 0;
				$cookie .= "; Max-Age=$diff";
			}
		}
		if($path !== '') $cookie .= "; path=$path";
		if($domain !== '') $cookie .= "; domain=$domain";
		if($secure) $cookie .= "; secure";
		if($httponly) $cookie .= "; HttpOnly";
		if($samesite !== '') $cookie .= "; SameSite=$samesite";
		$this->headers['Set-Cookie'][] = $cookie;
	}
	
	public function write(string $data): bool {
		if(!$this->headSend(-1)) return false;
		
		$n = strlen($data);
		if($n === 0) return true;
		
		if($this->isChunked) {
			$data = sprintf("%x\r\n%s\r\n", $n, $data);
		}
		
		return $this->send($data);
	}
	
	private $endEx;
	public function end(?string $data = null): bool {
		if($this->isEnd) {
			return true;
		}

		$this->endEx = new \Exception('Response end(): ' . $this->request->getKey(), 0, $this->endEx);
		
		$n = strlen($data);
		if(!$this->headSend($n)) {
			$this->isEnd = true;
			return false;
		}
		
		if($this->isChunked) {
			if($n) {
				$data = sprintf("%x\r\n%s\r\n0\r\n\r\n", $n, $data);
			} else {
				$data = "0\r\n\r\n";
			}
			$ret = $this->send($data);
			$this->isEnd = true;
			return $ret;
		} elseif($n) {
			$ret = $this->send($data);
			$this->isEnd = true;
			return $ret;
		} else return $this->isEnd = true;
	}
	
	protected function send(string $data): bool {
		if($this->isEnd) {
			$e = new \Exception('Response send(): ' . $this->request->getKey(), 0, $this->endEx);
			echo "$e\n";
			return true;
		}
		return $this->request->send($data);
	}
	
	/**
	 * @see RequestEvent::writeHandler()
	 */
	public function read() {
		if($this->fp) {
			if(feof($this->fp) || (!$this->size && !$this->rsize)) {
				$e = new \Exception('Send file is complete');
				echo "$e\n";

				$this->isEnd = true;
				fclose($this->fp);
				$this->fp = null;
				return false;
			}
			if($this->ranges) {
				$range = $this->ranges[$this->range];
				if(($buf = @fread($this->fp, min(16384, $this->rsize))) === false) {
					$this->isEnd = true;
					$this->request->isKeepAlive = false;
					fclose($this->fp);
					$this->fp = null;
					return false;
				}
				$n = strlen($buf);
				$this->size -= $n;
				$this->rsize -= $n;
				if(!$this->rsize) {
					$this->range++;
					if($this->range < count($this->ranges)) {
						$range = $this->ranges[$this->range];
						$buf .= "\r\n" . $range[2];
						$this->size -= strlen($range[2]);
						$this->rsize = $range[1] - $range[0] + 1;
						@fseek($this->fp, $range[0], SEEK_SET);
					} else {
						$this->isEnd = true;
						$this->size -= strlen($this->boundaryEnd);
						$buf .= "\r\n" . $this->boundaryEnd;
					}
				}
			} elseif(($buf = @fread($this->fp, min(16384, $this->size))) === false) {
				$n = strlen($buf);
				$this->size -= $n;
				$this->isEnd = true;
				$this->request->isKeepAlive = false;
				fclose($this->fp);
				$this->fp = null;
				return false;
			} else {
				$this->size -= strlen($buf);
				$this->isEnd = !$this->size;
			}
			return $buf;
		} else {
			return false;
		}
	}
	
	public static $transliteration = [
        'À' => 'A', 'Á' => 'A', 'Â' => 'A', 'Ã' => 'A', 'Ä' => 'A', 'Å' => 'A', 'Æ' => 'AE', 'Ç' => 'C',
        'È' => 'E', 'É' => 'E', 'Ê' => 'E', 'Ë' => 'E', 'Ì' => 'I', 'Í' => 'I', 'Î' => 'I', 'Ï' => 'I',
        'Ð' => 'D', 'Ñ' => 'N', 'Ò' => 'O', 'Ó' => 'O', 'Ô' => 'O', 'Õ' => 'O', 'Ö' => 'O', 'Ő' => 'O',
        'Ø' => 'O', 'Ù' => 'U', 'Ú' => 'U', 'Û' => 'U', 'Ü' => 'U', 'Ű' => 'U', 'Ý' => 'Y', 'Þ' => 'TH',
        'ß' => 'ss',
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'ae', 'ç' => 'c',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
        'ð' => 'd', 'ñ' => 'n', 'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ő' => 'o',
        'ø' => 'o', 'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ű' => 'u', 'ý' => 'y', 'þ' => 'th',
        'ÿ' => 'y',
    ];
	public function setContentDisposition(string $attachmentName, bool $isInline) {
		$disposition = ($isInline ? 'inline' : 'attachment');
        $fallbackName = str_replace(
            ['%', '/', '\\', '"'],
            ['_', '_', '_', '\\"'],
            strtr($attachmentName, static::$transliteration)
        );
        $utfName = rawurlencode(str_replace(['%', '/', '\\'], '', $attachmentName));

        $dispositionHeader = "{$disposition}; filename=\"{$fallbackName}\"";
        if ($utfName !== $fallbackName) {
            $dispositionHeader .= "; filename*=utf-8''{$utfName}";
        }
		$this->headers['Content-Disposition'] = $dispositionHeader;
		$this->headers['Content-Type'] = 'application/octet-stream';
	}
	
	protected $fp, $size = 0, $ranges = [], $range = 0, $rsize = 0, $boundaryEnd;
	public function sendFile($path) {
		if(is_file($path) && ($fp = @fopen($path, 'r')) !== false) {
			if(!isset($this->headers['Content-Disposition'])) {
				$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
				if(isset(static::$MIME_TYPES[$ext])) $this->setContentType(static::$MIME_TYPES[$ext]); else unset($this->headers['Content-Type']);
			}
			$stat = @fstat($fp);
			if($stat === false) {
				fclose($fp);
				
				goto end404;
			}
			if(!$stat['size']) {
				$this->end();
				return true;
			}
			$this->headers['Last-Modified'] = gmdate('D, d-M-Y H:i:s T', $stat['mtime']);
			$this->headers['ETag'] = sprintf('%xT-%xO', $stat['mtime'], $stat['size']);
			$this->headers['Accept-Ranges'] = 'bytes';
			$this->headers['Expires'] = gmdate('D, d-M-Y H:i:s T', time() + 3600);
			$this->headers['Cache-Control'] = ['must-revalidate', 'public', 'max-age=3600'];

			if(isset($this->request->headers['If-None-Match'])) {
				if($this->request->headers['If-None-Match'] === $this->headers['ETag']) {
					$this->headers['If-None-Match'] = 'false';
					$this->status = 304;
					$this->statusText = 'Not Modified';
					@fclose($fp);
					$this->end();
					return false;
				} else {
					$this->headers['If-None-Match'] = 'true';
				}
			}

			if(isset($this->request->headers['If-Modified-Since'])) {
				if($this->request->headers['If-Modified-Since'] === $this->headers['Last-Modified']) {
					$this->headers['If-Modified-Since'] = 'true';
					$this->status = 304;
					$this->statusText = 'Not Modified';
					@fclose($fp);
					$this->end();
					return false;
				} else {
					$this->headers['If-Modified-Since'] = 'false';
				}
			}

			if(isset($this->request->headers['If-Unmodified-Since'])) {
				if(strcmp($this->request->headers['If-Unmodified-Since'], $this->headers['Last-Modified']) < 0) {
					$this->headers['If-Unmodified-Since'] = 'false';
					$this->status = 412;
					$this->statusText = 'Precondition failed';
					@fclose($fp);
					$this->end();
					return false;
				} else {
					$this->headers['If-Unmodified-Since'] = 'true';
				}
			}

			if(isset($this->request->headers['If-Range'])) {
				if(isset($this->request->headers['Range']) && $this->request->headers['If-Range'] === $this->headers['Last-Modified']) {
					goto range;
				}
			} elseif(isset($this->request->headers['Range'])) {
				range:
				$ranges = [];
				$range = $this->request->headers['Range'];
				$n = preg_match_all('/(bytes=)?([0-9]+)\-([0-9]*),?\s*/i', $this->request->headers['Range'], $matches);

				if($n && implode('', $matches[0]) === $range) {
					for($i=0; $i<$n; $i++) {
						$range = $matches[3][$i];
						$start = $matches[2][$i];
						$end = ($range === '' ? $stat['size']-1 : $range);
						$ranges[] = [$start, $end];
						if($start < 0 || $start > $stat['size'] || $end < 0 || $end > $stat['size']) {
							$this->status = 416;
							$this->statusText = 'Requested Range Not Satisfiable';
							@fclose($fp);
							$this->end();
							return false;
						}
					}
					if($n > 1) {
						$boundary = bin2hex(random_bytes(8));
						$boundaryEnd = "--$boundary--\r\n";
						$contentType = $this->headers['Content-Type'];
						$this->status = 206;
						$this->statusText = 'Partial Content';
						$this->headers['Content-Type'] = 'multipart/byteranges; boundary=' . $boundary;
						$size = strlen($boundaryEnd);
						foreach($ranges as &$range) {
							$range[2] = "--$boundary\r\nContent-Type: {$contentType}\r\nContent-Range: bytes {$range[0]}-{$range[1]}/{$stat['size']}\r\n\r\n";
							$size += strlen($range[2]) + $range[1] - $range[0] + 3;
						}
						unset($range);
						$this->headSend($size);
						$this->size = $size;
						$this->ranges = $ranges;
						$this->fp = $fp;
						$this->boundaryEnd = $boundaryEnd;
						$this->send($ranges[0][2]);
						$this->rsize = $ranges[0][1] - $ranges[0][0] + 1;
						$this->size -= strlen($ranges[0][2]);
						@fseek($fp, $ranges[0][0], SEEK_SET);
						return true;
					} else {
						$size = $ranges[0][1] - $ranges[0][0] + 1;
						if($size === $stat['size']) goto all;
						$this->status = 206;
						$this->statusText = 'Partial Content';
						$this->headers['Content-Range'] = "bytes {$ranges[0][0]}-$ranges[0][1]/{$stat['size']}";
						$this->headSend($size);
						$this->fp = $fp;
						$this->size = $size;
						fseek($fp, $ranges[0][0], SEEK_SET);
						return true;
					}
				}
			}

		all:
			$this->headSend($stat['size']);
			$this->size = $stat['size'];
			$this->fp = $fp;
			return true;
		} else {
		end404:
			$this->status = 404;
			$this->statusText = 'Not Found';
			
			$this->end('<h1>Not Found</h1>');
			return false;
		}
	}

	public static $MIME_TYPES = [
		'txt' => 'text/plain',
		'htm' => 'text/html',
		'html' => 'text/html',
		'php' => 'text/plain; charset=utf-8',
		'css' => 'text/css',
		'js' => 'text/javascript',
		'json' => 'application/json',
		'xml' => 'application/xml',
		'swf' => 'application/x-shockwave-flash',
		'flv' => 'video/x-flv',
		'woff' => 'font/woff',
		'woff2' => 'font/woff2',
		
		// images
		'png' => 'image/png',
		'jpe' => 'image/jpeg',
		'jpeg' => 'image/jpeg',
		'jpg' => 'image/jpeg',
		'gif' => 'image/gif',
		'bmp' => 'image/bmp',
		'ico' => 'image/x-icon',
		'tiff' => 'image/tiff',
		'tif' => 'image/tiff',
		'svg' => 'image/svg+xml',
		'svgz' => 'image/svg+xml',
		
		// archives
		'zip' => 'application/zip',
		'rar' => 'application/x-rar-compressed',
		'exe' => 'application/x-msdownload',
		'msi' => 'application/x-msdownload',
		'cab' => 'application/vnd.ms-cab-compressed',
		'xz' => 'application/x-xz-compressed-tar',
		'gz' => 'application/x-compressed-tar',
		'bz2' => 'application/x-bzip-compressed-tar',
		'jar' => 'application/x-java-archive',
		'tgz' => 'application/x-compressed-tar',
		
		// audio/video
		'mp3' => 'audio/mpeg',
		'qt' => 'video/quicktime',
		'mov' => 'video/quicktime',
		
		// adobe
		'pdf' => 'application/pdf',
		'psd' => 'image/vnd.adobe.photoshop',
		'ai' => 'application/postscript',
		'eps' => 'application/postscript',
		'ps' => 'application/postscript',
		
		// ms office
		'doc' => 'application/msword',
		'rtf' => 'application/rtf',
		'xls' => 'application/vnd.ms-excel',
		'ppt' => 'application/vnd.ms-powerpoint',
		
		// open office
		'odt' => 'application/vnd.oasis.opendocument.text',
		'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
	];
}
