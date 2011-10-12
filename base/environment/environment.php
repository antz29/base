<?php

namespace Base;

class Enviroment {

	static private $_instance;

	private $_server = array();
	private $_env = array();

	/**
	 * Get a singleton instance of the class.
	 *
	 * @return Enviroment
	 */
	static public function getInstance()
	{
		if (!(self::$_instance instanceof self)) {
			self::init();
		}
		return self::$_instance;
	}

	/**
	 * init
	 *
	 * Initialise the environment.
	 */
	static public function init()
	{
		self::$_instance = new self();
	}

	private function __clone() {}

	private function __construct()
	{
		$this->setServer($_SERVER);
		$this->setEnv($_ENV);
		$this->setSapi(PHP_SAPI);
	}

	/**
	 * Set the current SAPI
	 *
	 * @param string $sapi
	 */
	public function setSapi($sapi)
	{
		$this->_sapi = $sapi;
	}

	/**
	 * Get the current SAPI
	 *
	 * @return unknown_type
	 */
	public function getSapi()
	{
		return $this->_sapi;
	}

	/**
	 * setServer
	 *
	 * Override the server data with the provided $server array.
	 *
	 * @param $server array
	 */
	public function setServer(array $server)
	{
		$this->_server = $server;
	}

	/**
	 * setEnv
	 *
	 * Override the env data with the provided $env array.
	 *
	 * @param $env array
	 */
	public function setEnv(array $env)
	{
		$this->_env = $env;
	}

	/**
	 * getServer
	 *
	 * Either return all the server data or pass a $name to retrieve a single value.
	 *
	 * @param $name string
	 * @return mixed
	 */
	public function getServer($name=null)
	{
		if (!isset($name)) return $this->_server;
 		return isset($this->_server[$name]) ? $this->_server[$name] : null;
	}

	/**
	 * getEnv
	 *
	 * Either return all the env data or pass a $name to retrieve a single value.
	 *
	 * @param $name string
	 * @return mixed
	 */
	public function getEnv($name=null)
	{
		if (!isset($name)) return $this->_env;
 		return isset($this->_env[$name]) ? $this->_env[$name] : null;
	}

	/**
	 * Detects the current environment by testing the _SERVER super global.
	 *
	 * Povide an associative array of 'environments' describing what each environment
	 * should match. ie.
	 *
	 * $environments = array (
	 * 		'production' => array(
	 * 			'HTTP_HOST' => 'schools.qsnetwork.com'
	 * 		),
	 * 		'staging' => array(
	 * 			'HTTP_HOST' => 'qs-sta.ibuildings.com'
	 * 		),
	 * 		'uat' => array(
	 * 			'HTTP_HOST' => 'qs-uat.ibuildings.com'
	 * 		),
	 * 		'integration' => array(
	 * 			'HTTP_HOST' => 'qcnetworks.dev.ibuildings.com'
	 * 		),
	 * 		'development' => array(
	 * 			'HTTP_HOST' => array('qs-john','qs-rowan','qs-ben','qs-alex') // In this case, it will match one of the provided values.
	 * 		)
	 * );
	 *
	 * It will return the environment that matches first, or the provided $default if not.
	 *
	 * @param array $environments
	 * @param string $default
	 * @return string
	 */
	public function detectEnvironment(array $environments,$default = 'production')
	{
		$host = $this->getServer('HTTP_HOST');

		foreach ($environments as $env => $settings) {
			if (isset($settings['HTTP_HOST'])) {
				$hosts = $settings['HTTP_HOST'];
				if (is_array($hosts)) {
					if (in_array($host, $hosts)) {
						return $env;
					}
				} else {
					if ($hosts == $host) {
						return $env;
					}
				}
			}
		}

		return $default;
	}
}