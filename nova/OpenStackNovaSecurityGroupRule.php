<?php

class OpenStackNovaSecurityGroupRule {

	public $rule;

	/**
	 * @param  $apiInstanceResponse
	 */
	function __construct( $apiInstanceResponse ) {
		$this->rule = $apiInstanceResponse;
	}

	/**
	 * @return
	 *
	 */
	function getId() {
		return OpenStackNovaController::_get_property( $this->rule, 'id' );
	}

	/**
	 * @return
	 *
	 */
	function getToPort() {
		return OpenStackNovaController::_get_property( $this->rule, 'to_port' );
	}

	/**
	 * @return
	 */
	function getFromPort() {
		return OpenStackNovaController::_get_property( $this->rule, 'from_port' );
	}

	/**
	 * @return
	 */
	function getIPProtocol() {
		return OpenStackNovaController::_get_property( $this->rule, 'ip_protocol' );
	}

	/**
	 * @return string
	 */
	function getIPRange() {
		return OpenStackNovaController::_get_property( $this->rule->ip_range, 'cidr' );
	}

	/**
	 * @return array
	 */
	function getGroup() {
		$properties = [];
		$properties['groupname'] = OpenStackNovaController::_get_property(
			$this->rule->group, 'name'
		);
		$properties['project'] = OpenStackNovaController::_get_property(
			$this->rule->group, 'tenant_id'
		);

		if ( $properties['groupname'] && $properties['project'] ) {
			return $properties;
		} else {
			return [];
		}
	}

}
