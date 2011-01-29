<?php

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
	 * @return
	 */
	function getKeyName() {
		return (string)$this->keypair->keyName;
	}

	/**
	 * @return
	 */
	function getKeyFingerprint() {
		return (string)$this->keypair->keyFingerprint;
	}

}
