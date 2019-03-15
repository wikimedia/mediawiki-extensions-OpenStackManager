<?php

/**
 * class for NovaInstance
 *
 * @file
 * @ingroup Extensions
 */

class OpenStackNovaInstance {

	/**
	 * @var stdClass
	 */
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
	 * @param stdClass $apiInstanceResponse
	 * @param string $region
	 * @param bool $loadhost optional
	 */
	public function __construct( $apiInstanceResponse, $region, $loadhost = false ) {
		$this->instance = $apiInstanceResponse;
		$this->region = $region;
		if ( $loadhost ) {
			$this->loadHost();
		} else {
			$this->host = null;
		}
	}

	public function loadHost() {
		$this->host = OpenStackNovaHost::getHostByNameAndProject(
			$this->getInstanceName(), $this->getProject(), $this->region
		);
	}

	/**
	 * Manually set an OpenStackNovaHost object to this instance.
	 * @param OpenStackNovaHost $host
	 * @return void
	 */
	public function setHost( $host ) {
		$this->host = $host;
	}

	/**
	 * Return the host entry associated with this instance, or null if one is not
	 * associated.
	 *
	 * @return null|OpenStackNovaHost
	 */
	public function getHost() {
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
	public function getInstanceId() {
		return OpenStackNovaController::_get_property(
			$this->instance, 'OS-EXT-SRV-ATTR:instance_name'
		);
	}

	/**
	 * Return the EC2 instance ID assigned to this instance
	 *
	 * @return string
	 */
	public function getInstanceOSId() {
		return OpenStackNovaController::_get_property( $this->instance, 'id' );
	}

	/**
	 * Return the private IP address assigned to this instance
	 *
	 * @return string[]
	 */
	public function getInstancePrivateIPs() {
		$addrs = [];
		$addresses = OpenStackNovaController::_get_property( $this->instance, 'addresses' );
		if ( $addresses ) {
			foreach ( $addresses as $addresslist ) {
				foreach ( $addresslist as $address ) {
					$addr = OpenStackNovaController::_get_property( $address, 'addr' );
					if ( $addr &&
						!filter_var( $addr, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE )
					) {
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
	 * @return string[]
	 */
	public function getInstancePublicIPs() {
		$addrs = [];
		$addresses = OpenStackNovaController::_get_property( $this->instance, 'addresses' );
		if ( $addresses ) {
			foreach ( $addresses as $addresslist ) {
				foreach ( $addresslist as $address ) {
					$addr = OpenStackNovaController::_get_property( $address, 'addr' );
					if ( $addr &&
						filter_var( $addr, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE )
					) {
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
	public function getInstanceName() {
		return OpenStackNovaController::_get_property( $this->instance, 'name' );
	}

	/**
	 * Get ID of the instance creator
	 *
	 * @return string
	 */
	public function getInstanceCreator() {
		return OpenStackNovaController::_get_property( $this->instance, 'user_id' );
	}

	/**
	 * Get a human friendly name + id of this instance
	 *
	 * @return string
	 */
	public function getInstanceForDisplay() {
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
	public function getInstanceState() {
		return OpenStackNovaController::_get_property( $this->instance, 'status' );
	}

	/**
	 * Return the task state in which this instance currently exists
	 *
	 * @return string
	 */
	public function getInstanceTaskState() {
		return OpenStackNovaController::_get_property( $this->instance, 'OS-EXT-STS:task_state' );
	}

	/**
	 * Return the type (size) of the instance
	 *
	 * @return string
	 */
	public function getInstanceType() {
		return OpenStackNovaController::_get_property( $this->instance->flavor, 'id' );
	}

	/**
	 * Return the image this instance was created using
	 *
	 * @return string
	 */
	public function getImageId() {
		return OpenStackNovaController::_get_property( $this->instance->image, 'id' );
	}

	/**
	 * Return public ssh keys associated with this instance
	 *
	 * @return string
	 */
	public function getKeyName() {
		return OpenStackNovaController::_get_property( $this->instance, 'key_name' );
	}

	/**
	 * Return the project this instance is a member of
	 *
	 * @return string
	 */
	public function getProject() {
		return OpenStackNovaController::_get_property( $this->instance, 'tenant_id' );
	}

	/**
	 * Return the host this instance is running on
	 *
	 * @return string
	 */
	public function getInstanceHost() {
		return OpenStackNovaController::_get_property( $this->instance, 'OS-EXT-SRV-ATTR:host' );
	}

	/**
	 * Return the availability zone this instance is associated with
	 * @return string
	 */
	public function getAvailabilityZone() {
		return 'Unimplemented';
	}

	/**
	 * Return the region in which this instance exists
	 *
	 * @return string
	 */
	public function getRegion() {
		return 'Unimplemented';
	}

	/**
	 * Return all security groups to which this instance belongs
	 * @return array
	 */
	public function getSecurityGroups() {
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
	public function getLaunchTime() {
		return OpenStackNovaController::_get_property( $this->instance, 'created' );
	}

	public function deleteArticle() {
		$host = $this->getHost();
		if ( $host ) {
			'@phan-var OpenStackNovaPrivateHost $host';
			OpenStackNovaArticle::deleteArticle( $host->getFullyQualifiedHostName() );
		}
	}

	public function deleteInstance( $userNova ) {
		global $wgUser;

		$success = $userNova->terminateInstance( $this->getInstanceOsId() );
		if ( !$success ) {
			return false;
		}
		OpenStackManagerEvent::createDeletionEvent(
			$this->getInstanceName(), $this->getProject(), $wgUser
		);
		$this->deleteArticle();
		return true;
	}

}
