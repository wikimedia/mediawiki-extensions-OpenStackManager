<?php

/**
 * class for nova instance proxies
 *
 * @file
 * @ingroup Extensions
 */

class OpenStackNovaProxy {

	public $proxyFQDN;
	public $backendName;
	public $backendPort;

	/**
	 * @param $sudoername
	 * @param $projectName
	 */
	function __construct( $projectName, $proxyFQDN, $backendName = "", $backendPort = '80' ) {
		$this->projectName = $projectName;
		$this->proxyFQDN = $proxyFQDN;
		$this->backendName = $backendName;
		$this->backendPort = $backendPort;
	}

	/**
	 * Return the proxy hostname
	 *
	 * @return string
	 */
	function getProxyFQDN() {
		return $this->proxyFQDN;
	}

	/**
	 * Return the proxy hostname
	 *
	 * @return string
	 */
	function getBackend() {
		$backend = $this->backendName . ":" . $this->backendPort;
		return $backend;
	}
}
