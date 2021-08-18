<?php

namespace fwe\curl;

class File {
	protected $file;
	protected $mime;

	public function __construct(string $file, string $mime = '') {
		$this->file = $file;
		if($mime === '') $mime = mime_content_type($file);
		$this->mime = $mime;
	}

	public function __get($name) {
		return $this->$name;
	}
}
