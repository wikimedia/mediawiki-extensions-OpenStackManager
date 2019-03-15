<?php

/**
 * Class for Nova Address
 *
 * @file
 * @ingroup Extensions
 */

class OpenStackNovaAddress {

	public $address;

	/**
	 * @param array $apiInstanceResponse
	 */
	public function __construct( $apiInstanceResponse ) {
		$this->address = $apiInstanceResponse;
	}

	/**
	 * Return the instance associated with this address, or an
	 * empty string if the address isn't associated
	 *
	 * @return string
	 */
	public function getInstanceId() {
		return OpenStackNovaController::_get_property( $this->address, 'instance_id' );
	}

	/**
	 * Return the floating IP address from the EC2 response
	 *
	 * @return string
	 */
	public function getPublicIP() {
		return OpenStackNovaController::_get_property( $this->address, 'ip' );
	}

	public function getAddressId() {
		return OpenStackNovaController::_get_property( $this->address, 'id' );
	}

}
