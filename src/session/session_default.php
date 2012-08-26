<?php
namespace Base;

class SessionDefault implements SessionProxy {
	
	public function init() {
		session_start();
	}
	
	public function set($name,$value) {
		$_SESSION[$name] = $value;
	}
	
	public function get($name) {
		return isset($_SESSION[$name]) ? $_SESSION[$name] : null;
	}

	public function remove($name) {
		unset($_SESSION[$name]);
	}
	
	public function destroy() {
		session_destroy();
	}

	public function getData() {
		return $_SESSION;
	}
	
}
