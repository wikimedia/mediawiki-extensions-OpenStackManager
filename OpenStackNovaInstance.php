<?php

# TODO: Make this an abstract class, and make the EC2 API a subclass
class OpenStackNovaInstance {

	var $instance;

	/**
	 * @var OpenStackNovaHost
	 */
	var $host;

	function __construct( $apiInstanceResponse, $loadhost = false ) {
		$this->instance = $apiInstanceResponse;
		if ( $loadhost ) {
			$this->host = OpenStackNovaHost::getHostByInstanceId( $this->getInstanceId() );
		} else {
			$this->host = null;
		}
	}

	function setHost( $host ) {
		$this->host = $host;
	}

	function getHost() {
		return $this->host;
	}

	function getReservationId() {
		return $this->instance->reservationId;
	}

	function getInstanceId() {
		return $this->instance->instancesSet->item->instanceId;
	}

	function getInstancePrivateIP() {
		# Though this is unintuitive, privateDnsName is the private IP
		return $this->instance->instancesSet->item->privateDnsName;
	}

	function getInstancePublicIP() {
		# Though this is unintuitive, privateDnsName is the private IP
		return $this->instance->instancesSet->item->dnsName;
	}

	function getInstanceName() {
		return $this->instance->instancesSet->item->displayName;
	}

	function getInstanceState() {
		return $this->instance->instancesSet->item->instanceState->name;
	}

	function getInstanceType() {
		return $this->instance->instancesSet->item->instanceType;
	}

	function getImageId() {
		return $this->instance->instancesSet->item->imageId;
	}

	function getKeyName() {
		return $this->instance->instancesSet->item->keyName;
	}

	function getOwner() {
		return $this->instance->ownerId;
	}

	function getAvailabilityZone() {
		return $this->instance->instancesSet->item->placement->availabilityZone;
	}

	function getRegion() {
		# NOTE: This is non-existant in openstack for now
		return $this->instance->instancesSet->item->region;
	}

	function getSecurityGroups() {
		$groups = array();
		foreach ( $this->instance->groupSet->item as $group ) {
			$groups[] = $group->groupId;
		}
		return $groups;
	}

	function getLaunchTime() {
		return $this->instance->instancesSet->item->launchTime;
	}

}
