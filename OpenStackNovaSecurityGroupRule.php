<?php

# TODO: Make this an abstract class, and make the EC2 API a subclass
class OpenStackNovaSecurityGroupRule {

	var $rule;

	function __construct( $apiInstanceResponse ) {
		$this->rule = $apiInstanceResponse;
	}

	function getToPort() {
		return $this->rule->toPort;
	}

	function getFromPort() {
		return $this->rule->fromPort;
	}

	function getIPProtocol() {
		return $this->rule->ipProtocol;
	}

	function getIPRanges() {
		$ranges = array();
		foreach ( $this->rule->ipRanges->item as $iprange ) {
			$ranges[] = $iprange->cidrIp;
		}
		return $ranges;
	}

	/**
	 * @return array
	 */
	function getGroups() {
		$groups = array();
		foreach ( $this->rule->groups->item as $group ) {
			$properties = array();
			$properties['groupname'] = $group->groupName;
			$properties['project'] = $group->userId;
			$groups[] = $properties;
		}
		return $groups;
	}

}
