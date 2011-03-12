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
	 * Get the EC2 reservation ID associated with this instance.
	 *
	 * @return string
	 */
	function getReservationId() {
		return (string)$this->instance->reservationId;
	}

	/**
	 * Return the EC2 instance ID assigned to this instance
	 *
	 * @return string
	 */
	function getInstanceId() {
		return (string)$this->instance->instancesSet->item->instanceId;
	}

	/**
	 * Return the private IP address assigned to this instance
	 *
	 * @return string
	 */
	function getInstancePrivateIP() {
		# Though this is unintuitive, privateDnsName is the private IP
		return (string)$this->instance->instancesSet->item->privateDnsName;
	}

	/**
	 * Return the public IP address associated with this object. If there is no
	 * public IP associated, this will return the same as getInstancePrivateIP().
	 *
	 * @return string
	 */
	function getInstancePublicIP() {
		# Though this is unintuitive, privateDnsName is the private IP
		return (string)$this->instance->instancesSet->item->dnsName;
	}

	/**
	 * Get the name assigned to this instance
	 *
	 * @return string
	 */
	function getInstanceName() {
		return (string)$this->instance->instancesSet->item->displayName;
	}

	/**
	 * Return the state in which this instance currently exists
	 *
	 * @return string
	 */
	function getInstanceState() {
		return (string)$this->instance->instancesSet->item->instanceState->name;
	}

	/**
	 * Return the type (size) of the instance
	 *
	 * @return string
	 */
	function getInstanceType() {
		return (string)$this->instance->instancesSet->item->instanceType;
	}

	/**
	 * Return the image this instance was created using
	 *
	 * @return string
	 */
	function getImageId() {
		return (string)$this->instance->instancesSet->item->imageId;
	}

	/**
	 * Return public ssh keys associated with this instance
	 *
	 * @return string
	 */
	function getKeyName() {
		return (string)$this->instance->instancesSet->item->keyName;
	}

	/**
	 * Return the project this instance is a member of
	 *
	 * @return string
	 */
	function getOwner() {
		return (string)$this->instance->ownerId;
	}

	/**
	 * Return the availability zone this instance is associated with
	 * @return string
	 */
	function getAvailabilityZone() {
		return (string)$this->instance->instancesSet->item->placement->availabilityZone;
	}

	/**
	 * Return the region in which this instance exists
	 *
	 * @return string
	 */
	function getRegion() {
		# NOTE: This is non-existant in openstack for now
		return (string)$this->instance->instancesSet->item->region;
	}

	/**
	 * Return all security groups to which this instance belongs
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
	 * Return the time at which this instance was created
	 *
	 * @return string
	 */
	function getLaunchTime() {
		return (string)$this->instance->instancesSet->item->launchTime;
	}

	/**
	 * Adds or edits an article for this instance
	 *
	 * @return void
	 */
	function editArticle() {
		global $wgOpenStackManagerCreateResourcePages;

		if ( ! $wgOpenStackManagerCreateResourcePages ) {
			return;
		}

		$title = Title::newFromText( $this->getInstanceId(), NS_NOVA_RESOURCE );
		$article = new Article( $title );
		$format = "{{Nova Resource|resourcetype=instance|name=%s|reservationid=%s|instanceid={{PAGENAME}}|privateip=%s|publicip=%s|instancestate=%s|instancetype=%s|imageid=%s|project=%s|availabilityzone=%s|region=%s|securitygroups=%s|launchtime=%s|fqdn=%s|puppetclass=%s|puppetvar=%s}}";
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
				if ( $key == 'instancecreator_email' ) {
					continue;
				}
				$puppetvars .= $key . '=' . $val . ',';
			}
		}
		$text = sprintf( $format,
			$this->getInstanceName(),
			$this->getReservationId(),
			$this->getInstancePrivateIP(),
			$this->getInstancePublicIP(),
			// Since instance state is somewhat dynamic, is this useful?
			$this->getInstanceState(),
			$this->getInstanceType(),
			$this->getImageId(),
			$this->getOwner(),
			$this->getAvailabilityZone(),
			$this->getRegion(),
			implode( ',', $this->getSecurityGroups() ),
			$this->getLaunchTime(),
			$host->getFullyQualifiedHostName(),
			$puppetclasses,
			$puppetvars
		);
		$article->doEdit( $text, '', EDIT_SUPPRESS_RC );
	}

	function deleteArticle() {
		global $wgOpenStackManagerCreateResourcePages;

		if ( ! $wgOpenStackManagerCreateResourcePages ) {
			return;
		}

		$title = Title::newFromText( $this->getInstanceId(), NS_NOVA_RESOURCE );
		$article = new Article( $title );
		$suppress = Revision::DELETED_TEXT && Revision::DELETED_COMMENT && Revision::DELETED_USER && Revision::DELETED_RESTRICTED;
		$article->doDeleteArticle( '', $suppress );
	}

}
