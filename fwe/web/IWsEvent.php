<?php
namespace fwe\web;

interface IWsEvent {
	public function read(string $msg);
	public function free();
}
