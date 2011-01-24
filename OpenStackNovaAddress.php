<?php

# TODO: Make this an abstract class, and make the EC2 API a subclass
class OpenStackNovaAddress {

	var $address;

	/**
	 * @param  $apiInstanceResponse
	 */
	function __construct( $apiInstanceResponse ) {
		$this->address = $apiInstanceResponse;
	}

	/**
	 * @return string
	 */
	function getInstanceId() {
		# instanceId returns as: instanceid (project)
		$info = explode( ' ', $this->address->instanceId );
		if ( $info[0] == 'None' ) {
			return '';
		} else {
			return $info[0];
		}
	}

	/**
	 * @return
	 */
	function getPublicIP() {
		return $this->address->publicIp;
	}

	/**
	 * @return mixed
	 */
	function getProject() {
		# instanceId returns as: instanceid (project)
		$info = explode( ' ', $this->address->instanceId );
		return str_replace( array('(',')'), '', $info[1] );
	}

}
