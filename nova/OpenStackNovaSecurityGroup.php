<?php

/**
 * todo comment me
 *
 * @file
 * @ingroup Extensions
 */

class OpenStackNovaSecurityGroup {

	var $group;
	var $rules;

	/**
	 * @param $apiInstanceResponse
	 */
	function __construct( $apiInstanceResponse ) {
		$this->group = $apiInstanceResponse;
		$this->rules = array();
		foreach ( OpenStackNovaController::_get_property( $this->group, 'rules' ) as $permission ) {
			$this->rules[] = new OpenStackNovaSecurityGroupRule( $permission );
		}
	}

	/**
	 * @return string
	 */
	function getGroupName() {
		return OpenStackNovaController::_get_property( $this->group, 'name' );
	}

	/**
	 * @return string
	 */
	function getGroupId() {
		return OpenStackNovaController::_get_property( $this->group, 'id' );
	}

	/**
	 * @return string
	 */
	function getGroupDescription() {
		return OpenStackNovaController::_get_property( $this->group, 'description' );
	}

	/**
	 * @return string
	 */
	function getProject() {
		return OpenStackNovaController::_get_property( $this->group, 'tenant_id' );
	}

	/**
	 * @return array|OpenStackNovaSecurityGroupRule
	 */
	function getRules() {
		return $this->rules;
	}

}
