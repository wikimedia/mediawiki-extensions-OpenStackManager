<?php

/**
 * todo comment me
 *
 * @file
 * @ingroup Extensions
 */

class OpenStackNovaKeyPair {

	public $keypair;

	/**
	 * @param  $apiKeypairResponse
	 */
	function __construct( $apiKeypairResponse ) {
		$this->keypair = $apiKeypairResponse;
	}

	/**
	 * Return the name given to this key upon creation
	 *
	 * @return string
	 */
	function getKeyName() {
		# not implemented
		return '';
	}

	/**
	 * Return the fingerprint generated from the public SSH key
	 *
	 * @return string
	 */
	function getKeyFingerprint() {
		# not implemented
		return '';
	}

}
