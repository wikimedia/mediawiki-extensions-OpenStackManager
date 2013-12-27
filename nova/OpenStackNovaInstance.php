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
			$this->loadHost();
		} else {
			$this->host = null;
		}
	}

	function loadHost() {
		$this->host = OpenStackNovaHost::getHostByInstanceId( $this->getInstanceId() );
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
		$addrs = array();
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

	function getPuppetStatus() {
		global $wgPuppetInterval;

		$metadata = OpenStackNovaController::_get_property( $this->instance, 'metadata' );
		if ( ! property_exists( $metadata, 'puppetstatus' ) ) {
			return 'unknown';
		}
		if ( ! property_exists( $metadata, 'puppettimestamp' ) ) {
			return 'unknown';
		}
		$status = $metadata->puppetstatus;
		$timestamp = $metadata->puppettimestamp;
		$elapsed = ( time() - $timestamp ) / 60;
		if ( $elapsed > $wgPuppetInterval ) {
			return 'stale';
		}
		if ( $status == 'changed' ) {
			return 'ok';
		}
		return $status;
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
		$groups = array();
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

	/**
	 * Adds or edits an article for this instance
	 *
	 * @param $userNova
	 */
	function editArticle( $userNova ) {
		if ( ! OpenStackNovaArticle::canCreatePages() ) {
			return;
		}

		$host = $this->getHost();

	        // There might already be an autogenerated instance status on this page,
	        // so set it aside in $instanceStatus.  We'll re-insert it at
	        // the start of the new page.
	        $instanceStatus = '';
	        $oldtext = OpenStackNovaArticle::getText( $host->getFullyQualifiedHostName() );
	        if ( $oldtext ) {
	            $startFlag = '<!-- autostatus begin -->';
	            $endFlag = '<!-- autostatus end -->';
	            $statusStart = strpos( $oldtext, $startFlag );
	            if ($statusStart !== false) {
	                $statusEnd = strpos( $oldtext, $endFlag, $statusStart );
	                if ( $statusEnd !== false ) {
	                    $instanceStatus = substr( $oldtext, $statusStart, $statusEnd - $statusStart + strlen( $endFlag ) );
	                }
	            }
	        }

		$format = <<<RESOURCEINFO
%s
{{Nova Resource
|Resource Type=instance
|Instance Name=%s
|Image Id=%s
|Project=%s
|Region=%s
|FQDN=%s
|Puppet Class=%s
|Puppet Var=%s}}
RESOURCEINFO;
		$puppetinfo = $host->getPuppetConfiguration();
		if ( $puppetinfo['puppetclass'] ) {
			$puppetclasses = implode( ',', $puppetinfo['puppetclass'] );
		} else {
			$puppetclasses = '';
		}
		$puppetvars = '';
		if ( $puppetinfo['puppetvar'] ) {
			foreach ( $puppetinfo['puppetvar'] as $key => $val ) {
				$puppetvars .= $key . '=' . $val . ',';
			}
		}
		$image = $userNova->getImage( $this->getImageId() );
		$text = sprintf( $format,
			$instanceStatus,
			$this->getInstanceName(),
			$image->getImageName(),
			$this->getProject(),
			$this->getRegion(),
			$host->getFullyQualifiedHostName(),
			$puppetclasses,
			$puppetvars
		);
		OpenStackNovaArticle::editArticle( $host->getFullyQualifiedHostName(), $text );
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
