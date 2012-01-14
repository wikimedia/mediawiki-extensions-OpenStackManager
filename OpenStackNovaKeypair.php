<?php

/**
 * todo comment me
 *
 * @file
 * @ingroup Extensions
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	echo( "This file is a part of the OpenStackManager extension and cannot be used standalone.\n" );
	die( 1 );
}

# TODO: Make this an abstract class, and make the EC2 API a subclass
class OpenStackNovaKeyPair {

	var $keypair;

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
		return (string)$this->keypair->keyName;
	}

	/**
	 * Return the fingerprint generated from the public SSH key
	 *
	 * @return string
	 */
	function getKeyFingerprint() {
		return (string)$this->keypair->keyFingerprint;
	}

}
