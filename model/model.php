<?php
namespace Base;

class Model {
	
	private $_base;
	
	public final function __construct(Base $base) {
		$this->_base = $base;
		$this->init();
	}
	
	protected final function getBase() {
		return $this->_base;
	}
	
	protected function init() {}
	
}