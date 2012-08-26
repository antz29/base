<?php
namespace Base;

use Base\Config\Node;

abstract class Resource {
	
	private $base;
	private $resource;
	private $config;
	private $exposed;
	
	final public function __construct(Node $config, Base $base) {
		$this->base = $base;
		$this->resource = $this->setUp($config);
	}
	
	final public function __destruct() {
		$this->tearDown();
	}
	
	final public function retrieve() {
		return $this->resource;
	}

	final protected function getBase() {
		return $this->base;
	}
	
	final protected function setReInit($reinit) {
		$this->reinit = $reinit;
	}
	
	final protected function setExposed($exposed) {
		$this->exposed = $exposed ? true : false;
	}
	
	final public function isExposed() {
		return $this->exposed;
	}
	
	abstract protected function setUp(Node $config);
	
	protected function tearDown() {}
}