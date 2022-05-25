<?php
namespace fwe\utils;

class FileHelper {
	public static function list(string $path) {
		$files = [];
		if(($dh = @opendir($path)) !== false) {
			while(($f = readdir($dh)) !== false) {
				if($f !== '.' && $f !== '..') {
					$files[] = $f;
				}
			}
			closedir($dh);
		}
		return $files;
	}
	
	public static function mkdir(string $path, bool $recursive = true) {
		if(is_dir($path)) {
			return true;
		} elseif($recursive) {
			return static::mkdir(dirname($path), true) && mkdir($path, 0755);
		} else {
			return mkdir($path, 0755);
		}
	}
}
