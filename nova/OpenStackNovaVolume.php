<?php

/**
 * todo comment me
 *
 * @file
 * @ingroup Extensions
 */

class OpenStackNovaVolume {

	var $volume;

	/**
	 * @param $apiVolumeResponse
	 */
	function __construct( $apiVolumeResponse ) {
		$this->volume = $apiVolumeResponse;
	}

	/**
	 * Return the assigned display name of this volume
	 *
	 * @return string
	 */
	function getVolumeName() {
		return OpenStackNovaController::_get_property( $this->volume, 'display_name' );
	}

	/**
	 * Return the assigned description of this volume
	 *
	 * @return string
	 */
	function getVolumeDescription() {
		return OpenStackNovaController::_get_property( $this->volume, 'display_description' );
	}

	/**
	 * Return the ID of this volume
	 *
	 * @return string
	 */
	function getVolumeId() {
		return OpenStackNovaController::_get_property( $this->volume, 'id' );
	}

	/**
	 * Returns the instance ID this volume is attached to, or an empty string if
	 * not attached
	 *
	 * @return string
	 */
	function getAttachedInstanceId() {
		# unimplemented
		return '';
	}

	/**
	 * Returns the attachment status of this volume
	 *
	 * @return string
	 */
	function getAttachmentStatus() {
		# unimplemented
		return '';
	}

	/**
	 * Returns the attachment time of this volume
	 *
	 * @return string
	 */
	function getAttachmentTime() {
		# unimplemented
		return '';
	}

	/**
	 * Return the device used when attached to an instance
	 *
	 * @return string
	 */
	function getAttachedDevice() {
		# unimplemented
		return '';
	}

	/**
	 * Return the size, in GB, of this volume
	 *
	 * @return int
	 */
	function getVolumeSize() {
		return OpenStackNovaController::_get_property( $this->volume, 'size' );
	}

	/**
	 * Return the creation date of this volume
	 *
	 * @return string
	 */
	function getVolumeCreationTime() {
		return OpenStackNovaController::_get_property( $this->volume, 'created_at' );
	}

	/**
	 * Return the volume's availability zone
	 *
	 * @return string
	 */
	function getVolumeAvailabilityZone() {
		return OpenStackNovaController::_get_property( $this->volume, 'availability_zone' );
	}

}
