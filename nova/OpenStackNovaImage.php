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
	 * @param string $apiInstanceResponse
	 */
	function __construct( $apiInstanceResponse ) {
		$this->image = $apiInstanceResponse;
	}

	/**
	 * Return the name of this image
	 *
	 * @return string
	 */
	function getImageName() {
		return $this->image->name;
	}

	/**
	 * Return the ID of this image
	 *
	 * @return string
	 */
	function getImageId() {
		return $this->image->id;
	}

	/**
	 * Return the availability state of this image
	 *
	 * @return string
	 */
	function getImageState() {
		return $this->image->status;
	}

	/**
	 * Return the value of the metadata key requested
	 *
	 * @param string $key
	 * @return string
	 */
	function getImageMetadata( $key ) {
		return OpenStackNovaController::_get_property( $this->image->metadata, $key );
	}
}
