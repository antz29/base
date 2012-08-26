<?php
namespace Base;

abstract class TemplatePlugin {

	private $_template;
	
	public function __construct(Template $template) {
		$this->_template = $template;
	}
	
	final protected function getBase() {
		return $this->getTemplate()->getBase();
	}
	
	final protected function getTemplate() {
		return $this->_template;
	}
	
	abstract public function exec(array $args = array()); 
}