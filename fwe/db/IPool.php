<?php
namespace fwe\db;

interface IPool {
	public function push($db);
	public function pop(bool $isSalve = true);
	public function remove($db);
	public function clean(float $time): string;
}