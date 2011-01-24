<?php

# TODO: Make this an abstract class, and make the EC2 API a subclass
class OpenStackNovaSecurityGroup {

	var $group;
	var $rules;

	/**
	 * @param  $apiInstanceResponse
	 */
	function __construct( $apiInstanceResponse ) {
		$this->group = $apiInstanceResponse;
		$this->rules = array();
		foreach ( $this->group->ipPermissions->item as $permission ) {
			$this->rules[] = new OpenStackNovaSecurityGroupRule( $permission );
		}
	}

	/**
	 * @return
	 */
	function getGroupName() {
		return $this->group->groupName;
	}

	/**
	 * @return
	 */
	function getGroupDescription() {
		return $this->group->groupDescription;
	}

	/**
	 * @return
	 */
	function getOwner() {
		return $this->group->ownerId;
	}

	/**
	 * @return array|OpenStackNovaSecurityGroupRule
	 */
	function getRules() {
		return $this->rules;
	}

}
