<?php

/**
 * todo comment me
 *
 * @file
 * @ingroup Extensions
 */

class OpenStackNovaSecurityGroup {

	public $group;
	public $rules;

	/**
	 * @param array $apiInstanceResponse
	 */
	public function __construct( $apiInstanceResponse ) {
		$this->group = $apiInstanceResponse;
		$this->rules = [];
		// @phan-suppress-next-line PhanTypeMismatchForeach
		foreach ( OpenStackNovaController::_get_property( $this->group, 'rules' ) as $permission ) {
			$this->rules[] = new OpenStackNovaSecurityGroupRule( $permission );
		}
	}

	/**
	 * @return string
	 */
	public function getGroupName() {
		return OpenStackNovaController::_get_property( $this->group, 'name' );
	}

	/**
	 * @return string
	 */
	public function getGroupId() {
		return OpenStackNovaController::_get_property( $this->group, 'id' );
	}

	/**
	 * @return string
	 */
	public function getGroupDescription() {
		return OpenStackNovaController::_get_property( $this->group, 'description' );
	}

	/**
	 * @return string
	 */
	public function getProject() {
		return OpenStackNovaController::_get_property( $this->group, 'tenant_id' );
	}

	/**
	 * @return array|OpenStackNovaSecurityGroupRule
	 */
	public function getRules() {
		return $this->rules;
	}

}
