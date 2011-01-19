<?php

# TODO: Make this an abstract class, and make the EC2 API a subclass
class OpenStackNovaSecurityGroup {

	var $group;
	var $rules;

	function __construct( $apiInstanceResponse ) {
		$this->group = $apiInstanceResponse;
		$this->rules = array();
		foreach ( $this->group->ipPermissions->item as $permission ) {
			$this->rules[] = new OpenStackNovaSecurityGroupRule( $permission );
		}
	}

	function getGroupName() {
		return $this->group->groupName;
	}

	function getGroupDescription() {
		return $this->group->groupDescription;
	}

	function getOwner() {
		return $this->group->ownerId;
	}

	function getRules() {
		return $this->rules;
	}

}
