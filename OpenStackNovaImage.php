<?php

/**
 * todo comment me
 *
 * @file
 * @ingroup Extensions
 */

class OpenStackNovaImage {

	var $image;

	/**
	 * @param  $apiInstanceResponse
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

}
