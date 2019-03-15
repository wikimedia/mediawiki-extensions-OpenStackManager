<?php

/**
 * todo comment me
 *
 * @file
 * @ingroup Extensions
 */

class OpenStackNovaVolume {

	public $volume;

	/**
	 * @param array $apiVolumeResponse
	 */
	public function __construct( $apiVolumeResponse ) {
		$this->volume = $apiVolumeResponse;
	}

	/**
	 * Return the assigned display name of this volume
	 *
	 * @return string
	 */
	public function getVolumeName() {
		return OpenStackNovaController::_get_property( $this->volume, 'display_name' );
	}

	/**
	 * Return the assigned description of this volume
	 *
	 * @return string
	 */
	public function getVolumeDescription() {
		return OpenStackNovaController::_get_property( $this->volume, 'display_description' );
	}

	/**
	 * Return the ID of this volume
	 *
	 * @return string
	 */
	public function getVolumeId() {
		return OpenStackNovaController::_get_property( $this->volume, 'id' );
	}

	/**
	 * Returns the instance ID this volume is attached to, or an empty string if
	 * not attached
	 *
	 * @return string
	 */
	public function getAttachedInstanceId() {
		# unimplemented
		return '';
	}

	/**
	 * Returns the attachment status of this volume
	 *
	 * @return string
	 */
	public function getAttachmentStatus() {
		# unimplemented
		return '';
	}

	/**
	 * Returns the attachment time of this volume
	 *
	 * @return string
	 */
	public function getAttachmentTime() {
		# unimplemented
		return '';
	}

	/**
	 * Return the device used when attached to an instance
	 *
	 * @return string
	 */
	public function getAttachedDevice() {
		# unimplemented
		return '';
	}

	/**
	 * Return the size, in GB, of this volume
	 *
	 * @return int
	 */
	public function getVolumeSize() {
		return OpenStackNovaController::_get_property( $this->volume, 'size' );
	}

	/**
	 * Return the creation date of this volume
	 *
	 * @return string
	 */
	public function getVolumeCreationTime() {
		return OpenStackNovaController::_get_property( $this->volume, 'created_at' );
	}

	/**
	 * Return the volume's availability zone
	 *
	 * @return string
	 */
	public function getVolumeAvailabilityZone() {
		return OpenStackNovaController::_get_property( $this->volume, 'availability_zone' );
	}

}
