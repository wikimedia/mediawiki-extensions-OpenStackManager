<?php

# TODO: Make this an abstract class, and make the EC2 API a subclass
class OpenStackNovaSecurityGroupRule {

	var $rule;

	function __construct( $apiInstanceResponse ) {
		$this->rule = $apiInstanceResponse;
	}

	function getToPort() {
		return $this->toPort;
	}

	function getFromPort() {
		return $this->fromPort;
	}

	function getIPProtocol() {
		return $this->ipProtocol;
	}

	function getIPRanges() {
		$ranges = array();
		foreach ( $this->ipRanges as $iprange ) {
			$ranges[] = $iprange->cidrIp;
		}
		return $ranges;
	}

	function getGroups() {
		$groups = array();
		foreach ( $this->groups as $group ) {
			$properties = array();
			$properties['groupname'] = $group->groupName;
			$properties['project'] = $group->userId;
			$groups[] = $properties;
		}
		return $groups;
	}

}
