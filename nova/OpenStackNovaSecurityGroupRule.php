<?php

class OpenStackNovaSecurityGroupRule {

	public $rule;

	/**
	 * @param stdClass $apiInstanceResponse
	 */
	public function __construct( $apiInstanceResponse ) {
		$this->rule = $apiInstanceResponse;
	}

	/**
	 * @return string
	 */
	public function getId() {
		return OpenStackNovaController::_get_property( $this->rule, 'id' );
	}

	/**
	 * @return string
	 */
	public function getToPort() {
		return OpenStackNovaController::_get_property( $this->rule, 'to_port' );
	}

	/**
	 * @return string
	 */
	public function getFromPort() {
		return OpenStackNovaController::_get_property( $this->rule, 'from_port' );
	}

	/**
	 * @return string
	 */
	public function getIPProtocol() {
		return OpenStackNovaController::_get_property( $this->rule, 'ip_protocol' );
	}

	/**
	 * @return string
	 */
	public function getIPRange() {
		return OpenStackNovaController::_get_property( $this->rule->ip_range, 'cidr' );
	}

	/**
	 * @return array
	 */
	public function getGroup() {
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
