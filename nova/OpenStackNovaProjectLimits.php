<?php

/**
 * class for OpenStackNovaProjectLimits
 *
 * @file
 * @ingroup Extensions
 */

class OpenStackNovaProjectLimits {

	private $limits;
	private $rate;
	private $absolute;

	/**
	 * @param  $apiLimitsResponse
	 * @param bool $loadhost optional
	 */
	function __construct( $apiLimitsResponse ) {
		$this->limits = $apiLimitsResponse;
		$this->rate = OpenStackNovaController::_get_property( $this->limits, 'rate' );
		$this->absolute = OpenStackNovaController::_get_property( $this->limits, 'absolute' );
	}

	/**
	 * Return the amount of RAM available
	 *
	 * @return string
	 */
	function getRamAvailable() {
		return OpenStackNovaController::_get_property( $this->absolute, 'maxTotalRAMSize' );
	}

	/**
	 * Return the amount of RAM used
	 *
	 * @return string
	 */
	function getRamUsed() {
		return OpenStackNovaController::_get_property( $this->absolute, 'totalRAMUsed' );
	}

	/**
	 * Return the number of floating IPs available
	 *
	 * @return string
	 */
	function getFloatingIpsAvailable() {
		return OpenStackNovaController::_get_property( $this->absolute, 'maxTotalFloatingIps' );
	}

	/**
	 * Return the number of floating IPs used
	 *
	 * @return string
	 */
	function getFloatingIpsUsed() {
		return OpenStackNovaController::_get_property( $this->absolute, 'totalFloatingIpsUsed' );
	}

	/**
	 * Return the number of cores available
	 *
	 * @return string
	 */
	function getCoresAvailable() {
		return OpenStackNovaController::_get_property( $this->absolute, 'maxTotalCores' );
	}

	/**
	 * Return the number of cores used
	 *
	 * @return string
	 */
	function getCoresUsed() {
		return OpenStackNovaController::_get_property( $this->absolute, 'totalCoresUsed' );
	}

	/**
	 * Return the number of instances available
	 *
	 * @return string
	 */
	function getInstancesAvailable() {
		return OpenStackNovaController::_get_property( $this->absolute, 'maxTotalInstances' );
	}

	/**
	 * Return the number of instances used
	 *
	 * @return string
	 */
	function getInstancesUsed() {
		return OpenStackNovaController::_get_property( $this->absolute, 'totalInstancesUsed' );
	}

	/**
	 * Return the number of security groups available
	 *
	 * @return string
	 */
	function getSecurityGroupsAvailable() {
		return OpenStackNovaController::_get_property( $this->absolute, 'maxSecurityGroups' );
	}

	/**
	 * Return the number of security groups used
	 *
	 * @return string
	 */
	function getSecurityGroupsUsed() {
		return OpenStackNovaController::_get_property( $this->absolute, 'totalSecurityGroupsUsed' );
	}

}
