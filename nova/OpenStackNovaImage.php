<?php

/**
 * todo comment me
 *
 * @file
 * @ingroup Extensions
 */

class OpenStackNovaImage {

	public $image;

	/**
	 * @param stdClass $apiInstanceResponse
	 */
	public function __construct( $apiInstanceResponse ) {
		$this->image = $apiInstanceResponse;
	}

	/**
	 * Return the name of this image
	 *
	 * @return string
	 */
	public function getImageName() {
		return $this->image->name;
	}

	/**
	 * Return the ID of this image
	 *
	 * @return string
	 */
	public function getImageId() {
		return $this->image->id;
	}

	/**
	 * Return the availability state of this image
	 *
	 * @return string
	 */
	public function getImageState() {
		return $this->image->status;
	}

	/**
	 * Return the value of the metadata key requested
	 *
	 * @param string $key
	 * @return string
	 */
	public function getImageMetadata( $key ) {
		return OpenStackNovaController::_get_property( $this->image->metadata, $key );
	}
}
