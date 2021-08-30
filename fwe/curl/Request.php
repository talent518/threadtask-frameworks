<?php
namespace fwe\curl;

/**
 * @property-read array $properties
 *
 * @property-read string $method
 * @property-read string $url
 * @property-read array $headers
 * @property-read integer $type
 * @property-read string|array $data
 * @property-read array $form
 * @property-read string $file
 * @property-read integer $fileSize
 * @property-read array $options
 * @property-read integer $format
 */
class Request {
	const TYPE_NONE = 0;
	const TYPE_DATA = 1;
	const TYPE_FORM = 2;
	const TYPE_FILE = 3;
	
	const FORMAT_URL = 0;
	const FORMAT_JSON = 1;
	const FORMAT_XML = 2;
	
	public $key, $resKey;
	
	protected $method;
	protected $url;
	protected $headers;

	protected $type = self::TYPE_NONE;
	
	public function __construct(string $url, string $method = 'GET', array $headers = []) {
		$this->url = $url;
		$this->method = $method;
		$this->headers = $headers;
	}
	
	public function __get($name) {
		if($name === 'properties') {
			return get_object_vars($this);
		} else {
			return $this->$name;
		}
	}
	
	public function addHeader(string $name, string $value) {
		if(isset($this->headers[$name])) {
			if(is_array($this->headers[$name])) {
				$this->headers[$name][] = $value;
			} else {
				$this->headers[$name] = [$this->headers[$name], $value];
			}
		} else {
			$this->headers[$name] = $value;
		}
		
		return $this;
	}
	
	public function addHeaders(array $headers) {
		foreach($headers as $name => $value) {
			$this->addHeader($name, $value);
		}
	}
	
	protected $data;
	public function setData($data) {
		$this->type = self::TYPE_DATA;
		$this->data = $data;
		$this->form = null;
		
		return $this;
	}
	
	public function addData($name, $value) {
		$this->data[$name] = $value;
		
		return $this;
	}
	
	protected $form = [];
	public function setForm(array $form, bool $isAppend = false) {
		$this->type = self::TYPE_FORM;
		
		if(!$isAppend) $this->form = [];
		foreach($form as $name => $value) {
			$this->addForm($name, $value);
		}
		
		return $this;
	}
	
	public function addForm(string $name, $value) {
		$this->type = self::TYPE_FORM;
		$this->data = null;
		
		if($value instanceof \CURLFile) {
			$this->form[$name] = new File($value->getFilename(), $value->getMimeType());
		} elseif($value instanceof File || is_string($value) || is_int($value) || is_float($value)) {
			$this->form[$name] = $value;
		} elseif(is_object($value) && method_exists($value, '__toString')) {
			$this->form[$name] =  (string) $value;
		} else {
			$this->form[$name] = null;
		}

		return $this;
	}
	
	protected $file, $fileSize;
	public function setFile($file, int $size = 0) {
		$this->type = self::TYPE_FILE;
		$this->data = null;
		$this->form = null;
		$this->file = $file;
		$this->fileSize - $size;
		
		return $this;
	}
	
	protected $options = [
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_MAXREDIRS => 3,
	];

	public function setOptions(array $options) {
		unset($options[CURLOPT_VERBOSE], $options[CURLOPT_HEADER], $options[CURLOPT_RETURNTRANSFER]);
		unset($options[CURLOPT_STDERR], $options[CURLOPT_FILE], $options[CURLOPT_INFILE], $options[CURLOPT_WRITEHEADER]);

		$this->options = $options;
		
		return $this;
	}
	
	public function setOption(int $option, $value) {
		$this->options[$option] = $value;
	}
	
	protected $format = self::FORMAT_URL;
	public function setFormat(int $format) {
		$this->format = $format;
		
		switch($format) {
			case self::FORMAT_URL:
				$this->headers['Content-Type'] = 'application/x-www-form-urlencoded';
				break;
			case self::FORMAT_JSON:
				$this->headers['Content-Type'] = 'application/json';
				break;
			case self::FORMAT_XML:
				$this->headers['Content-Type'] = 'application/xml; charset=utf-8';
				break;
			case self::FORMAT_RAW:
				$this->headers['Content-Type'] = 'multipart/form-data';
				break;
			default:
				$this->format = self::FORMAT_URL;
				break;
		}
		
		return $this;
	}
	
	protected function makeXML(\SimpleXMLElement $xml, array $a) {
		foreach($a as $k => $v) {
			if(is_array($v)) {
				$this->makeXML($xml->addChild($k), $v);
			} else {
				$xml->addChild($k, $v);
			}
		}
	}
	
	protected function format() {
		switch($this->format) {
			case self::FORMAT_URL:
			default:
				return http_build_query($this->data);
			case self::FORMAT_JSON:
				curl_setopt($ch, CURLOPT_CON, $value);
				return json_encode($this->data, JSON_PRETTY_PRINT);
			case self::FORMAT_XML:
				$xml = new \SimpleXMLElement("<?xml version=\"1.0\"?><root></root>");
				if(is_array($this->data)) {
					$this->makeXML($xml, $this->data);
				}
				return $xml->asXML();
			case self::FORMAT_RAW:
				return $this->data;
		}
	}
	
	/**
	 * @param resource $ch cURL 句柄
	 */
	protected function makeBody($ch) {
		switch($this->type) {
			case self::TYPE_DATA:
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($this->data) ? $this->data : $this->format());
				break;
			case self::TYPE_FORM:
				$form = [];
				foreach($this->form as $name => $value) {
					if($value instanceof File) {
						$form[$name] = curl_file_create($value->file, $value->mime);
					} else {
						$form[$name] = $value;
					}
				}
				curl_setopt($ch, CURLOPT_POSTREDIR, 7);
				curl_setopt($ch, CURLOPT_POST, true);
				curl_setopt($ch, CURLOPT_POSTFIELDS, $form);
				break;
			case self::TYPE_FILE:
				curl_setopt($ch, CURLOPT_PUT, true);
				curl_setopt($ch, CURLOPT_INFILE, $this->file);
				curl_setopt($ch, CURLOPT_INFILESIZE, $this->fileSize);
				break;
			default:
				break;
		}
	}
	
	public $responseClass = Response::class;
	
	public function save2File(string $file, bool $isAppend = false) {
		if($isAppend) {
			$this->options[CURLOPT_RESUME_FROM] = $size = (@filesize($file) ?: 0);
		} else {
			$size = 0;
		}
		$this->responseClass = [
			'class' => ResponseFile::class,
			'file' => $file,
			'size' => $size,
			'isAppend' => $isAppend
		];
		return $this;
	}
	
	final public function make() {
		$ch = curl_init($this->url);
		curl_setopt_array($ch, $this->options);
		curl_setopt($ch, CURLOPT_HTTPHEADER, $this->headers);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $this->method);
		$this->makeBody($ch);
		
		return $ch;
	}
}
