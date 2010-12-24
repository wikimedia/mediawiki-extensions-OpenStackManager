<?php

# TODO: Make this an abstract class, and make the EC2 API a subclass
class OpenStackNovaController {

	var $novaConnection;
	var $instances, $images, $keypairs, $availabilityZones;

	var $instanceTypes = array( 't1.micro', 'm1.small', 'm1.large', 'm1.xlarge', 'm2.xlarge', 'm2.2xlarge',
								'm2.4xlarge', 'c1.medium', 'c1.xlarge', 'cc1.4xlarge' );

	# TODO: Make disable_ssl, hostname, and resource_prefix config options
	function __construct( $credentials ) {
		global $wgOpenStackManagerNovaDisableSSL, $wgOpenStackManagerNovaServerName,
			$wgOpenStackManagerNovaPort, $wgOpenStackManagerNovaResourcePrefix;

		$this->novaConnection = new AmazonEC2( $credentials['accessKey'], $credentials['secretKey'] );
		$this->novaConnection->disable_ssl($wgOpenStackManagerNovaDisableSSL);
		$this->novaConnection->set_hostname($wgOpenStackManagerNovaServerName, $wgOpenStackManagerNovaPort);
		$this->novaConnection->set_resource_prefix($wgOpenStackManagerNovaResourcePrefix);
		$this->instances = array();
	}

	function getInstance( $instanceId, $reload = false ) {
		if ( isset( $this->instances[$instanceId] ) && !$reload ) {
			$instance = $this->instances[$instanceId];
		} else {
			$response = $this->novaConnection->describe_instances( $instanceId );
			$instance = new OpenStackNovaInstance( $response->body->reservationSet->item );
			$instanceId = $instance->getInstanceId();
			$this->instances["$instanceId"] = $instance;
		}
		return $instance;
	}

	function getInstances( $reload = false ) {
		if ( count( $this->instances ) == 0 || $reload ) {
			$this->instances = array();
			$response = $this->novaConnection->describe_instances();
			$instances = $response->body->reservationSet->item;
			foreach ( $instances as $instance ) {
				$instance = new OpenStackNovaInstance( $instance );
				$instanceId = $instance->getInstanceId();
				$this->instances["$instanceId"] = $instance;
			}
		}
		return $this->instances;
	}

	function getInstanceTypes() {
		return $this->instanceTypes;
	}

	function getImages( $reload = false ) {
		if ( count( $this->images ) == 0 || $reload ) {
			$this->images = array();
			$images = $this->novaConnection->describe_images();
			$images = $images->body->imagesSet->item;
			foreach ( $images as $image ) {
				if ( $image->imageType == 'machine' ) {
					$this->images["$image->imageId"] = $image;
				}
			}
		}
		return $this->images;
	}

	# TODO: make this user specific
	function getKeypairs( $reload = false ) {
		if ( count( $this->keypairs ) == 0 || $reload ) {
			$this->keypairs = array();
			$response = $this->novaConnection->describe_key_pairs();
			$keypairs = $response->body->keypairsSet->item;
			foreach ( $keypairs as $keypair ) {
				$keypair = new OpenStackNovaKeypair( $keypair );
				$keyname = $keypair->getKeyName();
				$this->keypairs["$keyname"] = $keypair;
			}
		}
		return $this->keypairs;
	}

	function getAvailabilityZones( $reload = false ) {
		if ( count( $this->availabilityZones ) == 0 || $reload ) {
			$this->availabilityZones = array();
			$availabilityZones = $this->novaConnection->describe_availability_zones();
			$availabilityZones = $availabilityZones->body->availabilityZoneInfo->item;
			foreach ( $availabilityZones as $availabilityZone ) {
				if ( $availabilityZones->zoneState == "available" ) {
					$this->availabilityZones["$availabilityZones->zoneName"] = $availabilityZone;
				}
			}
		}
		return $this->availabilityZones;
	}

	function createInstance( $instanceName, $image, $key, $instanceType, $availabilityZone, $domain ) {
		# 1, 1 is min and max number of instances to create.
		# We never want to make more than one at a time.
		$options = array();
		if ( $key ) {
			$options['KeyName'] = $key;
		}
		$options['InstanceType'] = $instanceType;
		$options['Placement.AvailabilityZone'] = $availabilityZone;
		$options['DisplayName'] = $instanceName;
		$response = $this->novaConnection->run_instances( $image, 1, 1, $options );
		if ( ! $response->isOK() ) {
			return null;
		}
		$instance = new OpenStackNovaInstance( $response->body, $domain );
		$instanceId = $instance->getInstanceId();
		$this->instances["$instanceId"] = $instance;

		return $instance;
	}

	function terminateInstance( $instanceId ) {
		$response = $this->novaConnection->terminate_instances( $instanceId );

		return $response->isOK();
	}

	function importKeyPair( $keyName, $key ) {
		$response = $this->novaConnection->import_key_pair( $keyName, $key );

		$keypair = new OpenStackNovaKeypair( $response->body );
		$keyName = $keypair->getKeyName();
		$this->keypairs["$keyName"] = $keypair;

		return $keypair;
	}

}
