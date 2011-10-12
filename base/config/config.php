<?php
namespace Base\Config;

class Config extends Node {

	private $_env = 'shared';
	
	public function __construct($file) 
	{		
		$config = include(CONFIG_ROOT.$file);
		$this->_data = $this->getConfigForHost($config);
	}	

	public function setModule($module) 
	{
		if (!isset($this->_data['modules'][$module]['config'])) return;

		$config = $this->_data['modules'][$module]['config'];

		$config = $this->getConfigForHost($config);

		$this->_data = $this->mergeArray($this->_data,$config);
	}

	public function getEnvironment() {
		return $this->_env;
	}

	private function getConfigForHost($config)
	{
		$host = $_SERVER['HTTP_HOST'];

		$data = isset($config['shared']) ? $config['shared'] : array();

		if (isset($config[$host])) {
			$data = $this->mergeArray($data,$config[$host]);
			$this->_env = $host;
		}
		else {
			foreach ($config as $name => $cfg) {
				if ($name == 'shared') continue;
				if (!isset($cfg['alias'])) continue;
				foreach ($cfg['alias'] as $alias) {
					if ($alias == $host) {
						$data = $this->mergeArray($data,$cfg);
						$this->_env = $name;
						break(2);
					}
				}
			}
		}

		return $data;
	}

	private function mergeArray($arr1, $arr2)
	{
		foreach($arr2 as $key => $value)
		{
			if(array_key_exists($key, $arr1) && is_array($value)) {
				$arr1[$key] = $this->mergeArray($arr1[$key], $arr2[$key]);
			}
			else {
				$arr1[$key] = $value;
			}
		}

		return $arr1;
	}	

}
