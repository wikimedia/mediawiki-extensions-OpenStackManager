<?php

/**
 * class for NovaInstance
 *
 * @file
 * @ingroup Extensions
 */

class OpenStackNovaInstance {

	public $instance;

	/**
	 * @var string
	 */
	public $region;

	/**
	 * @var OpenStackNovaHost
	 */
	public $host;

	/**
	 * @param  $apiInstanceResponse
	 * @param bool $loadhost, optional
	 */
	function __construct( $apiInstanceResponse, $region, $loadhost = false ) {
		$this->instance = $apiInstanceResponse;
		$this->region = $region;
		if ( $loadhost ) {
			$this->loadHost();
		} else {
			$this->host = null;
		}
	}

	function loadHost() {
		$this->host = OpenStackNovaHost::getHostByNameAndProject( $this->getInstanceName(), $this->getProject(), $this->region );
	}

	/**
	 * Manually set an OpenStackNovaHost object to this instance.
	 * @param  $host OpenStackNovaHost
	 * @return void
	 */
	function setHost( $host ) {
		$this->host = $host;
	}

	/**
	 * Return the host entry associated with this instance, or null if one is not
	 * associated.
	 *
	 * @return null|OpenStackNovaHost
	 */
	function getHost() {
		if ( !$this->host ) {
			$this->loadHost();
		}
		return $this->host;
	}

	/**
	 * Return the EC2 instance ID assigned to this instance
	 *
	 * @return string
	 */
	function getInstanceId() {
		return OpenStackNovaController::_get_property( $this->instance, 'OS-EXT-SRV-ATTR:instance_name' );
	}

	/**
	 * Return the EC2 instance ID assigned to this instance
	 *
	 * @return string
	 */
	function getInstanceOSId() {
		return OpenStackNovaController::_get_property( $this->instance, 'id' );
	}

	/**
	 * Return the private IP address assigned to this instance
	 *
	 * @return string
	 */
	function getInstancePrivateIPs() {
		$addrs = [];
		$addresses = OpenStackNovaController::_get_property( $this->instance, 'addresses' );
		if ( $addresses ) {
			foreach ( $addresses as $addresslist ) {
				foreach ( $addresslist as $address ) {
					$addr = OpenStackNovaController::_get_property( $address, 'addr' );
					if ( $addr && !filter_var( $addr, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE ) ) {
						$addrs[] = $addr;
					}
				}
			}
		}
		return $addrs;
	}

	/**
	 * Return the public IP address associated with this object. If there is no
	 * public IP associated, this will return the same as getInstancePrivateIPs().
	 *
	 * @return string
	 */
	function getInstancePublicIPs() {
		$addrs = [];
		$addresses = OpenStackNovaController::_get_property( $this->instance, 'addresses' );
		if ( $addresses ) {
			foreach ( $addresses as $addresslist ) {
				foreach ( $addresslist as $address ) {
					$addr = OpenStackNovaController::_get_property( $address, 'addr' );
					if ( $addr && filter_var( $addr, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE ) ) {
						$addrs[] = $addr;
					}
				}
			}
		}
		return $addrs;
	}

	/**
	 * Get the name assigned to this instance
	 *
	 * @return string
	 */
	function getInstanceName() {
		return OpenStackNovaController::_get_property( $this->instance, 'name' );
	}

	/**
	 * Get ID of the instance creator
	 *
	 * @return string
	 */
	function getInstanceCreator() {
		return OpenStackNovaController::_get_property( $this->instance, 'user_id' );
	}

	/**
	 * Get a human friendly name + id of this instance
	 *
	 * @return string
	 */
	function getInstanceForDisplay() {
		return wfMessage( 'openstackmanager-modifiedinstance' )
			->inContentLanguage()
			->params( $this->getInstanceId(), $this->getInstanceName() )
			->text();
	}

	/**
	 * Return the state in which this instance currently exists
	 *
	 * @return string
	 */
	function getInstanceState() {
		return OpenStackNovaController::_get_property( $this->instance, 'status' );
	}

	/**
	 * Return the task state in which this instance currently exists
	 *
	 * @return string
	 */
	function getInstanceTaskState() {
		return OpenStackNovaController::_get_property( $this->instance, 'OS-EXT-STS:task_state' );
	}

	/**
	 * Return the type (size) of the instance
	 *
	 * @return string
	 */
	function getInstanceType() {
		return OpenStackNovaController::_get_property( $this->instance->flavor, 'id' );
	}

	/**
	 * Return the image this instance was created using
	 *
	 * @return string
	 */
	function getImageId() {
		return OpenStackNovaController::_get_property( $this->instance->image, 'id' );
	}

	/**
	 * Return public ssh keys associated with this instance
	 *
	 * @return string
	 */
	function getKeyName() {
		return OpenStackNovaController::_get_property( $this->instance, 'key_name' );
	}

	/**
	 * Return the project this instance is a member of
	 *
	 * @return string
	 */
	function getProject() {
		return OpenStackNovaController::_get_property( $this->instance, 'tenant_id' );
	}

	/**
	 * Return the host this instance is running on
	 *
	 * @return string
	 */
	function getInstanceHost() {
		return OpenStackNovaController::_get_property( $this->instance, 'OS-EXT-SRV-ATTR:host' );
	}

	/**
	 * Return the availability zone this instance is associated with
	 * @return string
	 */
	function getAvailabilityZone() {
		return 'Unimplemented';
	}

	/**
	 * Return the region in which this instance exists
	 *
	 * @return string
	 */
	function getRegion() {
		return 'Unimplemented';
	}

	/**
	 * Return all security groups to which this instance belongs
	 * @return array
	 */
	function getSecurityGroups() {
		$secgroups = OpenStackNovaController::_get_property( $this->instance, 'security_groups' );
		$groups = [];
		if ( $secgroups ) {
			foreach ( $secgroups as $secgroup ) {
				$groups[] = OpenStackNovaController::_get_property( $secgroup, 'name' );
			}
		}
		return $groups;
	}

	/**
	 * Return the time at which this instance was created
	 *
	 * @return string
	 */
	function getLaunchTime() {
		return OpenStackNovaController::_get_property( $this->instance, 'created' );
	}

	function deleteArticle() {
		$host = $this->getHost();
		if ( $host ) {
			OpenStackNovaArticle::deleteArticle( $host->getFullyQualifiedHostName() );
		}
	}

	function deleteInstance( $userNova ) {
		global $wgUser;

		$success = $userNova->terminateInstance( $this->getInstanceOsId() );
		if ( !$success ) {
			return false;
		}
		OpenStackManagerEvent::createDeletionEvent( $this->getInstanceName(), $this->getProject(), $wgUser );
		$this->deleteArticle();
		return true;
	}

}
