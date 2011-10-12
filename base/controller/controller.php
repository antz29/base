<?php
namespace Base;

abstract class Controller {

	private $_base;
	private $_layout;
	private $_template;

	final public function __construct(Base $base, Template $layout, Template $template) {
		$this->_base = $base;
		$this->_layout = $layout;
		$this->_template = $template;
		$this->init();
	}

	/**
	 * 
	 * Get the base.
	 * 
	 * @return Base
	 */
	final protected function getBase() {
		return $this->_base;
	}
	
	final protected function getTemplate() {
		return $this->_template;
	}
	
	final protected function getLayout() {
		return $this->_layout;
	}
	
	protected function init() {}  
}