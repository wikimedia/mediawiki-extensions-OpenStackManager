<?php

/**
 * todo comment me
 *
 * @file
 * @ingroup Extensions
 */

class OpenStackNovaKeypair {

	public $keypair;

	/**
	 * @param string $apiKeypairResponse
	 */
	public function __construct( $apiKeypairResponse ) {
		$this->keypair = $apiKeypairResponse;
	}

	/**
	 * Return the name given to this key upon creation
	 *
	 * @return string
	 */
	public function getKeyName() {
		# not implemented
		return '';
	}

	/**
	 * Return the fingerprint generated from the public SSH key
	 *
	 * @return string
	 */
	public function getKeyFingerprint() {
		# not implemented
		return '';
	}

}
