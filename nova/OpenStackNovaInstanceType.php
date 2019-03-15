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
	 * @param stdClass $apiInstanceResponse
	 */
	public function __construct( $apiInstanceResponse ) {
		$this->instanceType = $apiInstanceResponse;
	}

	/**
	 * Return the amount of RAM this instance type will use
	 *
	 * @return string
	 */
	public function getMemorySize() {
		return $this->instanceType->ram;
	}

	/**
	 * Return the number of CPUs this instance will have
	 *
	 * @return string
	 */
	public function getNumberOfCPUs() {
		return $this->instanceType->vcpus;
	}

	/**
	 * Return the name of this instanceType
	 *
	 * @return string
	 */
	public function getInstanceTypeName() {
		return $this->instanceType->name;
	}

	/**
	 * Return the amount of root storage this instance will use
	 *
	 * @return string
	 */
	public function getRootStorageSize() {
		return $this->instanceType->disk;
	}

	/**
	 * Return the amount of storage this instance will use
	 *
	 * @return string
	 */
	public function getStorageSize() {
		return $this->instanceType->{'OS-FLV-EXT-DATA:ephemeral'};
	}

	/**
	 * Return the id of this instanceType
	 *
	 * @return int
	 */
	public function getInstanceTypeId() {
		return $this->instanceType->id;
	}

	/**
	 * @param OpenStackNovaInstanceType $a
	 * @param OpenStackNovaInstanceType $b
	 * @return bool
	 */
	public static function sorter( $a, $b ) {
		return $a->getInstanceTypeId() > $b->getInstanceTypeId();
	}

	public static function sort( &$collection ) {
		usort( $collection, [ __CLASS__, 'sorter' ] );
	}

}
