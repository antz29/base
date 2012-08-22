<?php

namespace Base\Extras\Hooks;

trait HookSupport {

	private $_hooks = [];
	
	protected function registerHook($name,$hook) 
	{
		$this->_hooks[$name][] = $hook;
	}

	protected function callHook($name,$args = []) 
	{
		if (!isset($this->_hooks[$name])) return;
		
		$continue = true;		
		array_map(function($func) use ($args,$continue) {
			if (!$continue) return;
			if ($func($args) === false) $continue = false;
		}, $this->_hooks[$name]);

		return $continue;
	}
}