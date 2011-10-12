<?php
namespace Base;

class Session {
	
	private static $_instance;
	private static $_proxy;
	
	public static function setProxy(SessionProxy $proxy) {
		self::$_proxy = $proxy;
	}
	
	/**
	 * 
	 * @return Session;
	 */
	public static function getInstance() {
		if (!(self::$_proxy instanceof SessionProxy)) {
			self::$_proxy = new SessionDefault();
		}
		
		if (!(self::$_instance instanceof self)) {
			self::$_instance = new self();
		}
		
		return self::$_instance;
	}
	
	private function __construct() {
		return self::$_proxy->init();
	}

	public function getData() {
		return self::$_proxy->getData();
	}
	
	public function set($name,$value) {
		return self::$_proxy->set($name,$value);
	}
	
	public function get($name) {
		return self::$_proxy->get($name);
	}

	public function remove($name) {
		return self::$_proxy->remove($name);
	}
	
	public function destroy() {
		return self::$_proxy->destroy();
	}
}
