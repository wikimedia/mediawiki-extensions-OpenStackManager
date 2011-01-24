<?php

# TODO: Make this an abstract class, and make the EC2 API a subclass
class OpenStackNovaInstance {

	var $instance;

	/**
	 * @var OpenStackNovaHost
	 */
	var $host;

	/**
	 * @param  $apiInstanceResponse
	 * @param bool $loadhost
	 */
	function __construct( $apiInstanceResponse, $loadhost = false ) {
		$this->instance = $apiInstanceResponse;
		if ( $loadhost ) {
			$this->host = OpenStackNovaHost::getHostByInstanceId( $this->getInstanceId() );
		} else {
			$this->host = null;
		}
	}

	/**
	 * @param  $host
	 * @return void
	 */
	function setHost( $host ) {
		$this->host = $host;
	}

	/**
	 * @return null|OpenStackNovaHost
	 */
	function getHost() {
		return $this->host;
	}

	/**
	 * @return
	 */
	function getReservationId() {
		return $this->instance->reservationId;
	}

	/**
	 * @return
	 */
	function getInstanceId() {
		return $this->instance->instancesSet->item->instanceId;
	}

	/**
	 * @return
	 */
	function getInstancePrivateIP() {
		# Though this is unintuitive, privateDnsName is the private IP
		return $this->instance->instancesSet->item->privateDnsName;
	}

	/**
	 * @return
	 */
	function getInstancePublicIP() {
		# Though this is unintuitive, privateDnsName is the private IP
		return $this->instance->instancesSet->item->dnsName;
	}

	/**
	 * @return
	 */
	function getInstanceName() {
		return $this->instance->instancesSet->item->displayName;
	}

	/**
	 * @return
	 */
	function getInstanceState() {
		return $this->instance->instancesSet->item->instanceState->name;
	}

	/**
	 * @return
	 */
	function getInstanceType() {
		return $this->instance->instancesSet->item->instanceType;
	}

	/**
	 * @return
	 */
	function getImageId() {
		return $this->instance->instancesSet->item->imageId;
	}

	/**
	 * @return
	 */
	function getKeyName() {
		return $this->instance->instancesSet->item->keyName;
	}

	/**
	 * @return
	 */
	function getOwner() {
		return $this->instance->ownerId;
	}

	/**
	 * @return
	 */
	function getAvailabilityZone() {
		return $this->instance->instancesSet->item->placement->availabilityZone;
	}

	/**
	 * @return
	 */
	function getRegion() {
		# NOTE: This is non-existant in openstack for now
		return $this->instance->instancesSet->item->region;
	}

	/**
	 * @return array
	 */
	function getSecurityGroups() {
		$groups = array();
		foreach ( $this->instance->groupSet->item as $group ) {
			$groups[] = $group->groupId;
		}
		return $groups;
	}

	/**
	 * @return 
	 */
	function getLaunchTime() {
		return $this->instance->instancesSet->item->launchTime;
	}

}
