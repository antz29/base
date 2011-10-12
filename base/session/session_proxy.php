<?php
namespace Base;

interface SessionProxy {
	
	public function init();
	public function set($name,$value);
	public function get($name);
	public function remove($name);
	public function destroy();
	public function getData();
	
}
