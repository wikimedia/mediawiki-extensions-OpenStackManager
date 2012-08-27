<?php

/**
 * class for NovaInstance 
 *
 * @file
 * @ingroup Extensions
 */

class OpenStackNovaInstance {

	var $instance;

	/**
	 * @var OpenStackNovaHost
	 */
	var $host;

	/**
	 * @param  $apiInstanceResponse
	 * @param bool $loadhost, optional
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
		$addrs = array();
		$fixedaddrs = OpenStackNovaController::_get_property( $this->instance->addresses, 'fixed' );
		if ( $fixedaddrs ) {
			foreach ( $fixedaddrs as $fixed ) {
				array_push( $addrs, OpenStackNovaController::_get_property( $fixed, 'addr' ) );
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
		$addrs = array();
		$floatings = OpenStackNovaController::_get_property( $this->instance->addresses, 'floating' );
		if ( $floatings ) {
			foreach ( $floatings as $floating ) {
				array_push( $addrs, OpenStackNovaController::_get_property( $floating, 'addr' ) );
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
		return OpenStackNovaController::_get_property( $this->instance, 'OS-EXT-STS:vm_state' );
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
		// Currently not implemented in the OpenStack API, so we're
		// implementing it as metadata for now
		$secgroup = OpenStackNovaController::_get_property( $this->instance->metadata, 'secgroup' );
		$groups = array();
		if ( $secgroup ) {
			$groups = explode( ',', $secgroup );
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

	/**
	 * Adds or edits an article for this instance
	 *
	 * @return void
	 */
	function editArticle( $userNova ) {
		if ( ! OpenStackNovaArticle::canCreatePages() ) {
			return;
		}

		$format = <<<RESOURCEINFO
{{Nova Resource
|Resource Type=instance
|Instance Name=%s
|Reservation Id=
|Instance Id=%s
|Instance OS Id=%s
|Private IP=%s
|Public IP=%s
|Instance State=%s
|Instance Host=%s
|Instance Type=%s
|RAM Size=%s
|Number of CPUs=%s
|Amount of Storage=%s
|Image Id=%s
|Project=%s
|Availability Zone=%s
|Region=%s
|Security Group=%s
|Launch Time=%s
|FQDN=%s
|Puppet Class=%s
|Puppet Var=%s}}
RESOURCEINFO;
		$host = $this->getHost();
		$puppetinfo = $host->getPuppetConfiguration();
		if ( $puppetinfo['puppetclass'] ) {
			$puppetclasses = implode( ',', $puppetinfo['puppetclass'] );
		} else {
			$puppetclasses = '';
		}
		$puppetvars = '';
		if ( $puppetinfo['puppetvar'] ) {
			foreach ( $puppetinfo['puppetvar'] as $key => $val ) {
				# Let's not leak user's email addresses; we know this
				# will be set, since we are setting it.
				if ( $key === 'instancecreator_email' ) {
					continue;
				}
				$puppetvars .= $key . '=' . $val . ',';
			}
		}
		$instanceType = $userNova->getInstanceType( $this->getInstanceType() );
		$image = $userNova->getImage( $this->getImageId() );
		$text = sprintf( $format,
			$this->getInstanceName(),
			$this->getInstanceId(),
			$this->getInstanceOSId(),
			implode( ',', $this->getInstancePrivateIPs() ),
			implode( ',', $this->getInstancePublicIPs() ),
			// Since instance state is somewhat dynamic, is this useful?
			$this->getInstanceState(),
			$this->getInstanceHost(),
			$instanceType->getInstanceTypeName(),
			$instanceType->getMemorySize(),
			$instanceType->getNumberOfCPUs(),
			$instanceType->getStorageSize(),
			$image->getImageName(),
			$this->getProject(),
			$this->getAvailabilityZone(),
			$this->getRegion(),
			implode( ',', $this->getSecurityGroups() ),
			$this->getLaunchTime(),
			$host->getFullyQualifiedHostName(),
			$puppetclasses,
			$puppetvars
		);
		OpenStackNovaArticle::editArticle( $this->getInstanceId(), $text );
	}

	function deleteArticle() {
		OpenStackNovaArticle::deleteArticle( $this->getInstanceId() );
	}

}
