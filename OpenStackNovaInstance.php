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
		return (string)$this->instance->reservationId;
	}

	/**
	 * @return
	 */
	function getInstanceId() {
		return (string)$this->instance->instancesSet->item->instanceId;
	}

	/**
	 * @return
	 */
	function getInstancePrivateIP() {
		# Though this is unintuitive, privateDnsName is the private IP
		return (string)$this->instance->instancesSet->item->privateDnsName;
	}

	/**
	 * @return
	 */
	function getInstancePublicIP() {
		# Though this is unintuitive, privateDnsName is the private IP
		return (string)$this->instance->instancesSet->item->dnsName;
	}

	/**
	 * @return
	 */
	function getInstanceName() {
		return (string)$this->instance->instancesSet->item->displayName;
	}

	/**
	 * @return
	 */
	function getInstanceState() {
		return (string)$this->instance->instancesSet->item->instanceState->name;
	}

	/**
	 * @return
	 */
	function getInstanceType() {
		return (string)$this->instance->instancesSet->item->instanceType;
	}

	/**
	 * @return
	 */
	function getImageId() {
		return (string)$this->instance->instancesSet->item->imageId;
	}

	/**
	 * @return
	 */
	function getKeyName() {
		return (string)$this->instance->instancesSet->item->keyName;
	}

	/**
	 * @return
	 */
	function getOwner() {
		return (string)$this->instance->ownerId;
	}

	/**
	 * @return
	 */
	function getAvailabilityZone() {
		return (string)$this->instance->instancesSet->item->placement->availabilityZone;
	}

	/**
	 * @return
	 */
	function getRegion() {
		# NOTE: This is non-existant in openstack for now
		return (string)$this->instance->instancesSet->item->region;
	}

	/**
	 * @return array
	 */
	function getSecurityGroups() {
		$groups = array();
		foreach ( $this->instance->groupSet->item as $group ) {
			$groups[] = (string)$group->groupId;
		}
		return $groups;
	}

	/**
	 * @return 
	 */
	function getLaunchTime() {
		return (string)$this->instance->instancesSet->item->launchTime;
	}

}
