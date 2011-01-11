<?php

# TODO: Make this an abstract class, and make the EC2 API a subclass
class OpenStackNovaAddress {

	var $address;

	function __construct( $apiInstanceResponse ) {
		$this->address = $apiInstanceResponse;
	}

	function getInstanceId() {
		# instanceId returns as: instanceid (project)
		$info = explode( ' ', $this->address->instanceId );
		if ( $info[0] == 'None' ) {
			return '';
		} else {
			return $info[0];
		}
	}

	function getPublicIP() {
		return $this->address->publicIp;
	}

	function getProject() {
		# instanceId returns as: instanceid (project)
		$info = explode( ' ', $this->address->instanceId );
		return str_replace( array('(',')'), '', $info[1] );
	}

}
