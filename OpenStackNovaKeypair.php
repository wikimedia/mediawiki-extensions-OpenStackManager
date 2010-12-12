<?php

# TODO: Make this an abstract class, and make the EC2 API a subclass
class OpenStackNovaKeyPair {

	var $keypair;

	function __construct( $apiKeypairResponse ) {
		$this->keypair = $apiKeypairResponse;
	}

	function getKeyName() {
		return $this->keypair->keyName;
	}

	function getKeyFingerprint() {
		return $this->keypair->keyFingerprint;
	}

}
