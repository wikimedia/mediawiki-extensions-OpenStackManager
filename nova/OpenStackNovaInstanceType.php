<?php

/**
 * todo comment me
 *
 * @file
 * @ingroup Extensions
 */

class OpenStackNovaInstanceType {

	public $instanceType;

	/**
	 * @param  $apiInstanceResponse
	 */
	function __construct( $apiInstanceResponse ) {
		$this->instanceType = $apiInstanceResponse;
	}

	/**
	 * Return the amount of RAM this instance type will use
	 *
	 * @return string
	 */
	function getMemorySize() {
		return $this->instanceType->ram;
	}

	/**
	 * Return the number of CPUs this instance will have
	 *
	 * @return string
	 */
	function getNumberOfCPUs() {
		return $this->instanceType->vcpus;
	}

	/**
	 * Return the name of this instanceType
	 *
	 * @return string
	 */
	function getInstanceTypeName() {
		return $this->instanceType->name;
	}

	/**
	 * Return the amount of root storage this instance will use
	 *
	 * @return string
	 */
	function getRootStorageSize() {
		return $this->instanceType->disk;
	}

	/**
	 * Return the amount of storage this instance will use
	 *
	 * @return string
	 */
	function getStorageSize() {
		return $this->instanceType->{'OS-FLV-EXT-DATA:ephemeral'};
	}

	/**
	 * Return the id of this instanceType
	 *
	 * @return int
	 */
	function getInstanceTypeId() {
		return $this->instanceType->id;
	}

	/**
	 * @static
	 * @param OpenStackNovaInstanceType $a
	 * @param OpenStackNovaInstanceType $b
	 * @return bool
	 */
	public static function sorter( $a, $b ) {
		return $a->getInstanceTypeId() > $b->getInstanceTypeId();
	}

	public static function sort( &$collection ) {
		usort( $collection, array( __CLASS__, 'sorter' ) );
	}

}
