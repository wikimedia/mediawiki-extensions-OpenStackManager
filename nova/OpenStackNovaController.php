<?php

/**
 * todo comment me
 *
 * @file
 * @ingroup Extensions
 */

class OpenStackNovaController {
	public $username;
	public $user;
	public $project;
	public $region;
	public $token;
	public $admintoken;

	/**
	 * @param $user string
	 */
	function __construct( $user ) {
		$this->project = '';
		$this->token = '';

		$this->username = $user->getUsername();
		$this->user = $user;
	}

	/**
	 * @param $user string
	 * @return OpenStackNovaController
	 */
	static function newFromUser( $user ) {
		return new OpenStackNovaController( $user );
	}

	/**
	 * Get a mime representation of a file with the specified attachmenttext.
	 * No parameters are escaped in this function. This function should never be
	 * called when dealing with end-user provided data.
	 *
	 * @param $attachmenttext
	 * @param $mimetype
	 * @param $filename
	 *
	 * @return string
	 */
	function getAttachmentMime( $attachmenttext, $mimetype, $filename ) {
		$endl = $this->getLineEnding();
		$attachment = 'Content-Type: ' . $mimetype . '; charset="us-ascii"'. $endl;
		$attachment .= 'MIME-Version: 1.0' . $endl;
		$attachment .= 'Content-Transfer-Encoding: 7bit' . $endl;
		$attachment .= 'Content-Disposition: attachment; filename="' . $filename . '"' . $endl;
		$attachment .= $endl;
		$attachment .= $attachmenttext;
		return $attachment;
	}

	function getLineEnding() {
		if ( wfIsWindows() ) {
			return "\r\n";
		} else {
			return "\n";
		}
	}

	function getProject() {
		return $this->project;
	}

	function setProject( $project ) {
		$this->project = $project;
	}

	function getRegion() {
		return $this->region;
	}

	function getRegions( $service ) {
		global $wgMemc;
		global $wgUser;
		global $wgOpenStackManagerRestrictedRegions;

		// We need to ensure the project token has been
		// fetched before we can get the regions.
		$this->getProjectToken( $this->project );
		$key = wfMemcKey( 'openstackmanager', 'serviceCatalog-' . $this->project, $this->username );
		$serviceCatalog = json_decode( $wgMemc->get( $key ) );
		$regions = array();
		if ( $serviceCatalog ) {
			foreach ( $serviceCatalog as $entry ) {
				if ( $entry->type === "identity" ) {
					foreach ( $entry->endpoints as $endpoint ) {
						if ( !$wgUser->isAllowed( 'accessrestrictedregions' ) && in_array( $endpoint->region, $wgOpenStackManagerRestrictedRegions ) ) {
							continue;
						}
						$regions[] = $endpoint->region;
					}
				}
			}
		}
		return array_unique( $regions );
	}

	function setRegion( $region ) {
		$this->region = $region;
	}

	/**
	 * @param $id
	 * @return null
	 */
	function getAddress( $id ) {
		$id = urlencode( $id );
		$ret = $this->restCall( 'compute', '/os-floating-ips/' . $id, 'GET' );
		$address = self::_get_property( $ret['body'], 'floating_ip' );
		if ( $address ) {
			return new OpenStackNovaAddress( $address );
		} else {
			return null;
		}
	}

	/**
	 * @return array
	 */
	function getAddresses() {
		$addressesarr = array();
		$ret = $this->restCall( 'compute', '/os-floating-ips', 'GET' );
		$addresses = self::_get_property( $ret['body'], 'floating_ips' );
		if ( !$addresses ) {
			return $addressesarr;
		}
		foreach ( $addresses as $address ) {
			$address = new OpenStackNovaAddress( $address );
			$ip = $address->getPublicIp();
			$addressesarr[$ip] = $address;
		}
		return $addressesarr;
	}

	/**
	 * @param  $instanceId
	 * @return null|OpenStackNovaInstance
	 */
	function getInstance( $instanceId ) {
		$instanceId = urlencode( $instanceId );
		$ret = $this->restCall( 'compute', '/servers/' . $instanceId, 'GET' );
		if ( $ret['code'] === 200 ) {
			$server = self::_get_property( $ret['body'], 'server' );
			if ( $server ) {
				return new OpenStackNovaInstance( $server, $this->getRegion(), true );
			}
		}
		return null;
	}

	function createProxy( $fqdn, $backendHost, $backendPort ) {
		$data = array( 'domain' => $fqdn, 'backends' => array ( 'http://' . $backendHost . ':' . $backendPort ) );
		$ret = $this->restCall( 'proxy', '/mapping', 'PUT', $data );

		if ( $ret['code'] !== 200 ) {
			return null;
		}

		$proxyObj = new OpenStackNovaProxy( $this->project, $fqdn, $backendHost, $backendPort );
		return $proxyObj;
	}

	function deleteProxy( $fqdn ) {
		$ret = $this->restCall( 'proxy', '/mapping/' . $fqdn, 'DELETE' );

		if ( $ret['code'] !== 200 ) {
			return false;
		}

		return true;
	}

	/**
	 * @return array
	 */
	function getProxiesForProject() {
		global $wgAuth;

		$proxyarr = array();
		$ret = $this->restCall( 'proxy', '/mapping', 'GET' );
		$proxies = self::_get_property( $ret['body'], 'routes' );
		if ( !$proxies ) {
			return $proxyarr;
		}
		foreach ( $proxies as $proxy ) {
			$domain = self::_get_property( $proxy, 'domain' );
			$backends = self::_get_property( $proxy, 'backends' );

			if ( (count( $backends ) ) > 1 ) {
				$wgAuth->printDebug( "Warning!  proxy $domain has multiple backends but we only support one backend per proxy.", NONSENSITIVE );
			}
			$backend = $backends[0];
			$backendarray = explode(  ':', $backends[0] );

			if ( strpos( $backend, "http" ) === 0 ) {
				if ( ( count( $backendarray ) < 2 ) or ( count( $backendarray ) > 3 ) ) {
					$wgAuth->printDebug( "Unable to parse backend $backend, discarding.", NONSENSITIVE );
				} elseif ( count( $backendarray ) == 2 ) {
					$backendHost = $backend;
					$backendPort = null;
				} else {
					$backendHost = $backendarray[0] . ":" . $backendarray[1];
					$backendPort = $backendarray[2];
				}
			} else {
				if ( ( count( $backendarray ) < 1 ) or ( count( $backendarray ) > 2 ) ) {
					$wgAuth->printDebug( "Unable to parse backend $backend, discarding.", NONSENSITIVE );
				} elseif ( count( $backendarray ) == 1 ) {
					$backendHost = $backend;
					$backendPort = null;
				} else {
					$backendHost = $backendarray[0];
					$backendPort = $backendarray[1];
				}
			}

			if ( $backendPort ) {
				$proxyObj = new OpenStackNovaProxy( $this->project, $domain, $backendHost, $backendPort );
			} else {
				$proxyObj = new OpenStackNovaProxy( $this->project, $domain, $backendHost );
			}

			$proxyarr[] = $proxyObj;
		}
		return $proxyarr;
	}

	/**
	 * @return a token for $wgOpenStackManagerLDAPUsername
	 *  who happens to have admin rights in Keystone.
	 */
	function _getAdminToken() {
		global $wgOpenStackManagerLDAPUsername, $wgOpenStackManagerLDAPUserPassword;
		global $wgOpenStackManagerProjectId, $wgAuth;
		global $wgMemc;

		if ( $this->admintoken ) {
			return $this->admintoken;
		}

		$key = wfMemcKey( 'openstackmanager', 'keystoneadmintoken' );

		$this->admintoken = $wgMemc->get( $key );
		if ( is_string( $this->admintoken ) ) {
			return $this->admintoken;
		}

		$data = array(
			'auth' => array(
				'passwordCredentials' => array(
					'username' => $wgOpenStackManagerLDAPUsername,
					'password' => $wgOpenStackManagerLDAPUserPassword ),
				'tenantId' => $wgOpenStackManagerProjectId ) );
		$headers = array(
			'Accept: application/json',
			'Content-Type: application/json',
		);
		$ret = $this->restCall( 'identity', '/tokens', 'POST', $data, $headers );
		if ( $ret['code'] !== 200 ) {
			$wgAuth->printDebug( "OpenStackNovaController::_getAdminToken return code: " . $ret['code'], NONSENSITIVE );
			return "";
		}

		$body = $ret['body'];
		$this->admintoken = self::_get_property( $body->access->token, 'id' );

		$wgMemc->set( $key, $this->admintoken, 300 );

		return $this->admintoken;
	}

	/**
	 * @return array of project ids => project names
	 */
	function getProjects() {
		$admintoken = $this->_getAdminToken();
		$headers = array( "X-Auth-Token: $admintoken" );

		$projarr = array();
		$ret = $this->restCall( 'identity', '/tenants', 'GET', array(), $headers );
		$projects = self::_get_property( $ret['body'], 'tenants' );
		if ( !$projects ) {
			return $projarr;
		}
		foreach ( $projects as $project ) {
			$projectname = self::_get_property( $project, 'name' );
			$projectid = self::_get_property( $project, 'id' );
			$projarr[$projectid] = $projectname;
		}
		return $projarr;
	}

	/**
	 * @return string
	 */
	function getProjectName( $projectid ) {
		$admintoken = $this->_getAdminToken();
		$headers = array( "X-Auth-Token: $admintoken" );

		$userarr = array();
		$ret = $this->restCall( 'identity', "/tenants/$projectid", 'GET', array(), $headers );
		$tenant = self::_get_property( $ret['body'], 'tenant' );
		return $tenant->name;
	}

	/**
	 * @return id of new project or "" on failure
	 */
	function createProject( $projectname ) {
		$admintoken = $this->_getAdminToken();
		$headers = array(
			'Accept: application/json',
			'Content-Type: application/json',
			"X-Auth-Token: $admintoken"
		);
		$projname = urlencode( $projectname );
		$data = array( 'tenant' => array( 'name' => $projname, 'id' => $projname ) );
		$ret = $this->restCall( 'identity', '/tenants', 'POST', $data, $headers );
		if ( $ret['code'] == 200 ) {
			$tenant = self::_get_property( $ret['body'], 'tenant' );
			return self::_get_property( $tenant, 'id' );
		}
		return "";
	}

	function deleteProject( $projectid ) {
		$admintoken = $this->_getAdminToken();
		$headers = array( "X-Auth-Token: $admintoken" );

		$ret = $this->restCall( 'identity', "/tenants/$projectid", 'DELETE', array(), $headers );
		if ( $ret['code'] !== 200 && $ret['code'] !== 204 ) {
			return false;
		}
		return true;
	}

	/**
	 * @return array of user IDs => user names
	 */
	function getUsersInProject( $projectid ) {
		$admintoken = $this->_getAdminToken();
		$headers = array( "X-Auth-Token: $admintoken" );

		$userarr = array();
		$ret = $this->restCall( 'identity', "/tenants/$projectid/users", 'GET', array(), $headers );
		$users = self::_get_property( $ret['body'], 'users' );
		if ( !$users ) {
			return $userarr;
		}
		foreach ( $users as $user ) {
			$name = self::_get_property( $user, 'name' );
			$id = self::_get_property( $user, 'id' );
			$userarr[$id] = $name;
		}
		return $userarr;
	}

	/**
	 * @return array of $roleid => $rolename
	 */
	function getKeystoneRoles( ) {
		global $wgMemc;

		$key = wfMemcKey( 'openstackmanager', 'keystoneroles' );
		$rolearr = $wgMemc->get( $key );
		if ( is_array( $rolearr ) ) {
			return $rolearr;
		}

		$admintoken = $this->_getAdminToken();
		$headers = array( "X-Auth-Token: $admintoken" );

		$rolearr = array();
		$ret = $this->restCall( 'identity', "/OS-KSADM/roles", 'GET', array(), $headers );
		$roles = self::_get_property( $ret['body'], 'roles' );
		if ( !$roles ) {
			return $rolearr;
		}
		foreach ( $roles as $role ) {
			$name = self::_get_property( $role, 'name' );
			$id = self::_get_property( $role, 'id' );
			$rolearr[$id] = $name;
		}

		$wgMemc->set( $key, $rolearr, 3600 );

		return $rolearr;
	}

	/**
	 * @return array of projects ids
	 */
	function getProjectsForUser( $userid ) {
		$admintoken = $this->_getAdminToken();
		$headers = array( "X-Auth-Token: $admintoken" );

		$projects = array();
		$ret = $this->restCall( 'identityv3', "/role_assignments?user.id=$userid", 'GET', array(), $headers );
		$role_assignments = self::_get_property( $ret['body'], 'role_assignments' );
		if ( !$role_assignments ) {
			return $projects;
		}
		foreach ( $role_assignments as $assignment ) {
			$scope = self::_get_property( $assignment, 'scope' );
			$project = self::_get_property( $scope, 'project' );
			$projectid = self::_get_property( $project, 'id' );

			$projects[] = $projectid;
		}
		return array_unique( $projects );
	}

	/**
	 * @return array of arrays:  role ID => role Names
	 */
	function getRoleAssignmentsForProject( $projectid ) {
		$admintoken = $this->_getAdminToken();
		$headers = array( "X-Auth-Token: $admintoken" );

		$assignments = array();

		$ret = $this->restCall( 'identityv3', "/role_assignments?scope.project.id=$projectid", 'GET', array(), $headers );
		$role_assignments = self::_get_property( $ret['body'], 'role_assignments' );
		if ( !$role_assignments ) {
			return $assignments;
		}
		foreach ( $role_assignments as $assignment ) {
			$role = self::_get_property( $assignment, 'role' );
			$roleid = self::_get_property( $role, 'id' );
			$user = self::_get_property( $assignment, 'user' );
			$userid = self::_get_property( $user, 'id' );
			$assignments[$roleid][] = $userid;
		}
		return $assignments;
	}


	/**
	 * @return array role IDs => role Names
	 */
	function getRolesForProjectAndUser( $projectid, $userid ) {
		$admintoken = $this->_getAdminToken();
		$headers = array( "X-Auth-Token: $admintoken" );

		$rolearr = array();
		$ret = $this->restCall( 'identity', "/tenants/$projectid/users/$userid/roles", 'GET', array(), $headers );
		$roles = self::_get_property( $ret['body'], 'roles' );
		if ( !$roles ) {
			return $rolearr;
		}
		foreach ( $roles as $role ) {
			$id = self::_get_property( $role, 'id' );
			$name = self::_get_property( $role, 'name' );
			$rolearr[$id] = $name;
		}
		return $rolearr;
	}

	function grantRoleForProjectAndUser( $roleid, $projectid, $userid ) {
		$admintoken = $this->_getAdminToken();
		$headers = array(
			'Accept: application/json',
			'Content-Type: application/json',
			"X-Auth-Token: $admintoken"
		);

		$rolearr = array();
		$ret = $this->restCall( 'identity', "/tenants/$projectid/users/$userid/roles/OS-KSADM/$roleid", 'PUT', array(), $headers );
		if ( $ret['code'] !== 200 && $ret['code'] !== 201 ) {
			return false;
		}
		return true;
	}

	function revokeRoleForProjectAndUser( $roleid, $projectid, $userid ) {
		$admintoken = $this->_getAdminToken();
		$headers = array(
			'Accept: application/json',
			'Content-Type: application/json',
			"X-Auth-Token: $admintoken"
		);

		$rolearr = array();
		$ret = $this->restCall( 'identity', "/tenants/$projectid/users/$userid/roles/OS-KSADM/$roleid", 'DELETE', array(), $headers );
		if ( $ret['code'] !== 204 && $ret['code'] !== 200 ) {
			return false;
		}
		return true;
	}

	/**
	 * @return OpenStackNovaInstance[]
	 */
	function getInstances() {
		$instancesarr = array();
		$ret = $this->restCall( 'compute', '/servers/detail', 'GET' );
		$instances = self::_get_property( $ret['body'], 'servers' );
		if ( !$instances ) {
			return $instancesarr;
		}
		foreach ( $instances as $instance ) {
			$instance = new OpenStackNovaInstance( $instance, $this->getRegion(), true );
			$id = $instance->getInstanceOSId();
			$instancesarr[$id] = $instance;
		}
		return $instancesarr;
	}

	/**
	 * @param $instancetypeid
	 * @return OpenStackNovaInstanceType
	 */
	function getInstanceType( $instancetypeid ) {
		$instancetypeid = urlencode( $instancetypeid );
		$ret = $this->restCall( 'compute', '/flavors/' . $instancetypeid, 'GET' );
		$flavor = self::_get_property( $ret['body'], 'flavor' );
		if ( $flavor ) {
			return new OpenStackNovaInstanceType( $flavor );
		}
		return null;
	}

	/**
	 * @return array
	 */
	function getInstanceTypes() {
		$ret = $this->restCall( 'compute', '/flavors/detail', 'GET' );
		$instanceTypesarr = array();
		$instanceTypes = self::_get_property( $ret['body'], 'flavors' );
		if ( !$instanceTypes ) {
			return $instanceTypesarr;
		}
		foreach ( $instanceTypes as $instanceType ) {
			$instanceType = new OpenStackNovaInstanceType( $instanceType );
			$instanceTypeName = $instanceType->getInstanceTypeName();
			$instanceTypesarr[$instanceTypeName] = $instanceType;
		}
		OpenStackNovaInstanceType::sort( $instanceTypesarr );
		return $instanceTypesarr;
	}

	/**
	 * @param $imageid
	 * @return null|\OpenStackNovaImage
	 */
	function getImage( $imageid ) {
		$imageid = urlencode( $imageid );
		$ret = $this->restCall( 'compute', '/images/' . $imageid, 'GET' );
		$image = self::_get_property( $ret['body'], 'image' );
		if ( $image ) {
			return new OpenStackNovaImage( $image );
		}
		return null;
	}

	/**
	 * @return array
	 */
	function getImages() {
		$ret = $this->restCall( 'compute', '/images/detail', 'GET' );
		$imagesarr = array();
		$images = self::_get_property( $ret['body'], 'images' );
		if ( !$images ) {
			return $imagesarr;
		}
		foreach ( $images as $image ) {
			$image = new OpenStackNovaImage( $image );
			$imageId = $image->getImageId();
			$imagesarr[$imageId] = $image;
		}
		return $imagesarr;
	}

	/**
	 */
	function getKeypairs() {
		// Currently unimplemented
	}

	/**
	 * @param $groupid
	 * @return OpenStackNovaSecurityGroup
	 */
	function getSecurityGroup( $groupid ) {
		// The API annoyingly doesn't allow you to pull a single group
		// pull them all, then return a single entry.
		$groups = $this->getSecurityGroups();
		if ( isset( $groups[$groupid] ) ) {
			return $groups[$groupid];
		} else {
			return null;
		}
	}

	/**
	 * @return array
	 */
	function getSecurityGroups() {
		$ret = $this->restCall( 'compute', '/os-security-groups', 'GET' );
		$groups = array();
		$securityGroups = self::_get_property( $ret['body'], 'security_groups' );
		if ( !$securityGroups ) {
			return $groups;
		}
		foreach ( $securityGroups as $securityGroup ) {
			$securityGroupObj = new OpenStackNovaSecurityGroup( $securityGroup );
			$groupid = $securityGroupObj->getGroupId();
			$groups[$groupid] = $securityGroupObj;
		}
		return $groups;
	}

	/**
	 * Get the console output of an instance
	 *
	 * @param $instanceid string
	 * @return string
	 */
	function getConsoleOutput( $instanceid ) {
		$instanceid = urlencode( $instanceid );
		$data = array( 'os-getConsoleOutput' => array( 'length' => null ) );
		$ret = $this->restCall( 'compute', '/servers/' . $instanceid . '/action', 'POST', $data );
		if ( $ret['code'] !== 200 ) {
			return '';
		}
		return self::_get_property( $ret['body'], 'output' );
	}

	/**
	 * @param  $volumeId
	 * @return null|OpenStackNovaVolume
	 */
	function getVolume( $volumeId ) {
		# unimplemented
		return null;
	}

	/**
	 * Get all volumes
	 *
	 * @return array
	 */
	function getVolumes() {
		# unimplemented
		return array();
	}

	/**
	 * @param  $instanceName
	 * @param  $image
	 * @param  $key
	 * @param  $instanceType
	 * @param  $groups
	 * @return null|OpenStackNovaInstance
	 */
	function createInstance( $instanceName, $image, $key, $instanceType, $groups ) {
		global $wgOpenStackManagerInstanceUserData;

		$data = array( 'server' => array() );
		if ( $key ) {
			$data['key_name'] = $key;
		}
		$data['server']['flavorRef'] = $instanceType;
		$data['server']['imageRef'] = $image;
		$data['server']['name'] = $instanceName;
		if ( $wgOpenStackManagerInstanceUserData ) {
			$random_hash = md5(date('r', time()));
			$endl = OpenStackNovaController::getLineEnding();
			$boundary = '===============' . $random_hash .'==';
			$userdata = 'Content-Type: multipart/mixed; boundary="' . $boundary .'"' . $endl;
			$userdata .= 'MIME-Version: 1.0' . $endl;
			$boundary = '--' . $boundary;
			$userdata .= $endl;
			$userdata .= $boundary;
			if ( $wgOpenStackManagerInstanceUserData['cloud-config'] ) {
				$userdata .= $endl . $this->getAttachmentMime( Spyc::YAMLDump( $wgOpenStackManagerInstanceUserData['cloud-config'] ), 'text/cloud-config', 'cloud-config.txt' );
				$userdata .= $endl . $boundary;
			}
			if ( $wgOpenStackManagerInstanceUserData['scripts'] ) {
				foreach ( $wgOpenStackManagerInstanceUserData['scripts'] as $scriptname => $script ) {
					wfSuppressWarnings();
					$stat = stat( $script );
					wfRestoreWarnings();
					if ( ! $stat ) {
						continue;
					}
					$scripttext = file_get_contents( $script );
					$userdata .= $endl . $this->getAttachmentMime( $scripttext, 'text/x-shellscript', $scriptname );
					$userdata .= $endl . $boundary;
				}
			}
			if ( $wgOpenStackManagerInstanceUserData['upstarts'] ) {
				foreach ( $wgOpenStackManagerInstanceUserData['upstarts'] as $upstartname => $upstart ) {
					wfSuppressWarnings();
					$stat = stat( $upstart );
					wfRestoreWarnings();
					if ( ! $stat ) {
						continue;
					}
					$upstarttext = file_get_contents( $upstart );
					$userdata .= $endl . $this->getAttachmentMime( $upstarttext, 'text/upstart-job', $upstartname );
					$userdata .= $endl . $boundary;
				}
			}
			$userdata .= '--';
			$data['server']['user_data'] = base64_encode( $userdata );
		}
		$data['server']['security_groups'] = array();
		foreach ( $groups as $group ) {
			$data['server']['security_groups'][] = array( 'name' => $group );
		}
		$ret = $this->restCall( 'compute', '/servers', 'POST', $data );
		if ( $ret['code'] !== 202 ) {
			return null;
		}
		$instance = new OpenStackNovaInstance( $ret['body']->server, $this->getRegion() );

		return $instance;
	}

	/**
	 * @param $instanceid
	 * @return bool
	 */
	function terminateInstance( $instanceid ) {
		$addresses = $this->getAddresses();
		foreach ( $addresses as $address ) {
			if ( $address->getInstanceId() === $instanceid ) {
				$this->disassociateAddress( $instanceid, $address->getPublicIP() );
			}
		}
		$instanceid = urlencode( $instanceid );
		$ret = $this->restCall( 'compute', '/servers/' . $instanceid, 'DELETE' );

		return( $ret['code'] === 204 );
	}

	/**
	 * @param  $groupname
	 * @param  $description
	 * @return null|OpenStackNovaSecurityGroup
	 */
	function createSecurityGroup( $groupname, $description ) {
		$data = array( 'security_group' => array( 'name' => $groupname, 'description' => $description ) );
		$ret = $this->restCall( 'compute', '/os-security-groups', 'POST', $data );
		if ( $ret['code'] !== 200 ) {
			return null;
		}
		$attr = self::_get_property( $ret['body'], 'security_group' );
		if ( !$attr ) {
			return null;
		}
		$securityGroup = new OpenStackNovaSecurityGroup( $attr );

		return $securityGroup;
	}

	/**
	 * @param $groupid
	 * @return bool
	 */
	function deleteSecurityGroup( $groupid ) {
		$groupid = urlencode( $groupid );
		$ret = $this->restCall( 'compute', '/os-security-groups/' . $groupid, 'DELETE' );

		return ( $ret['code'] === 202 );
	}

	/**
	 * @param $groupid
	 * @param string $fromport
	 * @param string $toport
	 * @param string $protocol
	 * @param string $range
	 * @param string $group
	 * @return bool
	 */
	function addSecurityGroupRule( $groupid, $fromport='', $toport='', $protocol='', $range='', $group='' ) {
		if ( $group && $range ) {
			return false;
		} else if ( $range ) {
			$data = array( 'security_group_rule' => array (
				'parent_group_id' => (int)$groupid,
				'from_port' => $fromport,
				'to_port' => $toport,
				'ip_protocol' => $protocol,
				'cidr' => $range )
			);
		} else if ( $group ) {
			$data = array( 'security_group_rule' => array (
				'parent_group_id' => (int)$groupid,
				'group_id' => (int)$group )
			);
		}
		$ret = $this->restCall( 'compute', '/os-security-group-rules', 'POST', $data );

		return ( $ret['code'] === 200 );
	}

	/**
	 * @param $ruleid
	 * @return bool
	 */
	function removeSecurityGroupRule( $ruleid ) {
		$ruleid = urlencode( $ruleid );
		$ret = $this->restCall( 'compute', '/os-security-group-rules/' . $ruleid, 'DELETE' );

		return ( $ret['code'] === 202 );
	}

	/**
	 * @return null|OpenStackNovaAddress
	 */
	function allocateAddress() {
		$ret = $this->restCall( 'compute', '/os-floating-ips', 'POST', array() );
		if ( $ret['code'] !== 200 ) {
			return null;
		}
		$floating_ip = self::_get_property( $ret['body'], 'floating_ip' );
		if ( !$floating_ip ) {
			return null;
		}
		$address = new OpenStackNovaAddress( $floating_ip );

		return $address;
	}

	/**
	 * Release ip address
	 *
	 * @param $id
	 * @return bool
	 */
	function releaseAddress( $id ) {
		$id = urlencode( $id );
		$ret = $this->restCall( 'compute', '/os-floating-ips/' . $id, 'DELETE' );

		return ( $ret['code'] === 202 );
	}

	/**
	 * Attach new ip address to instance
	 *
	 * @param  $instanceid
	 * @param  $ip
	 * @return bool
	 */
	function associateAddress( $instanceid, $ip ) {
		$instanceid = urlencode( $instanceid );
		$data = array( 'addFloatingIp' => array( 'address' => $ip ) );
		$ret = $this->restCall( 'compute', '/servers/' . $instanceid . '/action', 'POST', $data );

		return ( $ret['code'] === 202 );
	}

	/**
	 * Disassociate address from an instance
	 *
	 * @param $instanceid
	 * @param  $ip
	 * @return bool
	 */
	function disassociateAddress( $instanceid, $ip ) {
		$instanceid = urlencode( $instanceid );
		$data = array( 'removeFloatingIp' => array ( 'address' => $ip ) );
		$ret = $this->restCall( 'compute', '/servers/' . $instanceid . '/action', 'POST', $data );

		return ( $ret['code'] === 202 );
	}

	/**
	 * Create a Nova volume
	 *
	 * @param  $zone
	 * @param  $size
	 * @param  $name
	 * @param  $description
	 * @return OpenStackNovaVolume
	 */
	function createVolume( $zone, $size, $name, $description ) {
		# Unimplemented
		return null;
	}

	/**
	 * Delete a Nova volume
	 *
	 * @param  $volumeid
	 * @return boolean
	 */
	function deleteVolume( $volumeid ) {
		# unimplemented
		return false;
	}

	/**
	 * Attach a nova volume to the specified device on an instance
	 *
	 * @param volumeid
	 * @param instanceid
	 * @param device
	 * @return boolean
	 */
	function attachVolume( $volumeid, $instanceid, $device ) {
		# unimplemented
		return false;
	}

	/**
	 * Detaches a nova volume from an instance
	 *
	 * @param volumeid
	 * @param force
	 * @return boolean
	 */
	function detachVolume( $volumeid, $force ) {
		# unimplemented
		return false;
	}

	/**
	 * Reboots an instance
	 *
	 * @param type
	 * @param string $type
	 * @return boolean
	 */
	function rebootInstance( $instanceid, $type='SOFT' ) {
		$instanceid = urlencode( $instanceid );
		$data = array( 'reboot' => array( 'type' => $type ) );
		$ret = $this->restCall( 'compute', '/servers/' . $instanceid . '/action', 'POST', $data );
		return ( $ret['code'] === 202 );
	}

	function getLimits() {
		$ret = $this->restCall( 'compute', '/limits', 'GET', array() );
		if ( $ret['code'] !== 200 ) {
			return null;
		}
		$limits = self::_get_property( $ret['body'], 'limits' );
		if ( !$limits ) {
			return null;
		}
		$limits = new OpenStackNovaProjectLimits( $limits );

		return $limits;
	}

	function authenticate( $username, $password ) {
		global $wgAuth;
		global $wgMemc;

		$wgAuth->printDebug( "Entering OpenStackNovaController::authenticate", NONSENSITIVE );
		$headers = array(
			'Accept: application/json',
			'Content-Type: application/json',
		);
		$data = array( 'auth' => array( 'passwordCredentials' => array( 'username' => $username, 'password' => $password ) ) );
		$ret = $this->restCall( 'identity', '/tokens', 'POST', $data, $headers );
		if ( $ret['code'] !== 200 ) {
			$wgAuth->printDebug( "OpenStackNovaController::authenticate return code: " . $ret['code'], NONSENSITIVE );
			return '';
		}
		$user = $ret['body'];
		$this->token = $this->_get_property( $user->access->token, 'id' );
		$key = wfMemcKey( 'openstackmanager', 'fulltoken', $username );
		// Expiration time is unneccessary. Token expiration is expected
		// to be longer than MediaWiki's token, so a re-auth will occur
		// before the generic token expires.
		$wgMemc->set( $key, $this->token );

		return $this->token;
	}

	function getUnscopedToken() {
		global $wgMemc;

		$token = '';
		$key = wfMemcKey( 'openstackmanager', "fulltoken", $this->username );
		$fulltoken = $wgMemc->get( $key );
		if ( is_string( $fulltoken ) ) {
			$token = $fulltoken;
		} else {
			if ( !$this->token ) {
				$wikiuser = User::newFromName( $this->user->getUsername() );
				$token = OpenStackNovaUser::loadToken( $wikiuser );
				if ( !$token ) {
					// Log this user out!
					$wikiuser->doLogout();
					return '';
				}
				$wgMemc->set( $key, $token );
			} else {
				$token = $this->token;
			}
		}
		return $token;
	}

	function getProjectToken( $project ) {
		global $wgMemc;

		// Try to fetch the project token
		$projectkey = wfMemcKey( 'openstackmanager', "fulltoken-$project", $this->username );
		$projecttoken = $wgMemc->get( $projectkey );
		if ( is_string( $projecttoken ) ) {
			return $projecttoken;
		}
		$token = $this->getUnscopedToken();
		if ( !$token ) {
			// If there's no non-project token, there's nothing to do, the
			// user will need to re-authenticate.
			return '';
		}
		$headers = array(
			'Accept: application/json',
			'Content-Type: application/json',
		);
		$data = array( 'auth' => array( 'token' => array( 'id' => $token ), 'tenantName' => $project ) );
		$path = '/tokens';
		$ret = $this->restCall( 'identity', $path, 'POST', $data, $headers );
		if ( $ret['code'] !== 200 ) {
			return '';
		}
		$user = $ret['body'];
		$token = $this->_get_property( $user->access->token, 'id' );
		$expires = strtotime( $this->_get_property( $user->access->token, 'expires' ) );
		$wgMemc->set( $projectkey, $token, $expires );
		$key = wfMemcKey( 'openstackmanager', 'serviceCatalog-' . $project, $this->username );
		$wgMemc->set( $key, json_encode( $user->access->serviceCatalog ), $expires );

		return $token;
	}

	function getEndpoints( $service ) {
		global $wgMemc;

		$key = wfMemcKey( 'openstackmanager', 'serviceCatalog-' . $this->project, $this->username );
		$serviceCatalog = json_decode( $wgMemc->get( $key ) );
		$endpoints = array();
		if ( $serviceCatalog ) {
			foreach ( $serviceCatalog as $entry ) {
				if ( $entry->type === $service ) {
					foreach ( $entry->endpoints as $endpoint ) {
						$endpoints[] = $endpoint;
					}
				}
			}
		}
		return $endpoints;
	}

	function getTokenHeaders( $token, $project ) {
		// Project names can only contain a-z0-9-, strip everything else.
		$headers = array(
			'Accept: application/json',
			'Content-Type: application/json',
			'X-Auth-Project-Id: ' . preg_replace("/[^a-z0-9-]/", "", $project ),
			'X-Auth-Token: ' . $token,
		);
		return $headers;
	}

	function restCall( $service, $path, $method, $data = array(), $authHeaders='', $retrying=false ) {
		global $wgAuth;
		global $wgOpenStackManagerNovaIdentityURI;
		global $wgOpenStackManagerNovaIdentityV3URI;
		global $wgMemc;

		if ( $authHeaders ) {
			$headers = $authHeaders;
		} else {
			// This isn't an authentication call, we need to get the
			// tokens and the headers.
			$token = $this->getProjectToken( $this->getProject() );
			$headers = $this->getTokenHeaders( $token, $this->getProject() );
		}

		if ( $service === 'identity' ) {
			$endpointURL = $wgOpenStackManagerNovaIdentityURI;
		} elseif ( $service === 'identityv3' ) {
			$endpointURL = $wgOpenStackManagerNovaIdentityV3URI;
		} else {
			$endpoints = $this->getEndpoints( $service );
			foreach ( $endpoints as $endpoint ) {
				if ( $endpoint->region === $this->getRegion() ) {
					$endpointURL = $endpoint->publicURL;
				}
			}
		}
		$fullurl = $endpointURL . $path;
		$wgAuth->printDebug( "OpenStackNovaController::restCall fullurl: " . $fullurl, NONSENSITIVE );
		$handle = curl_init();
		switch( $method ) {
		case 'GET':
			if ( $data ) {
				$fullurl .= '?' . wfArrayToCgi( $data );
			}
			break;
		case 'POST':
			curl_setopt( $handle, CURLOPT_POST, true );
			curl_setopt( $handle, CURLOPT_POSTFIELDS, json_encode( $data ) );
			break;
		case 'PUT':
			curl_setopt( $handle, CURLOPT_CUSTOMREQUEST, 'PUT' );
			curl_setopt( $handle, CURLOPT_POSTFIELDS, json_encode( $data ) );
			break;
		case 'DELETE':
			curl_setopt( $handle, CURLOPT_CUSTOMREQUEST, 'DELETE' );
			break;
		}

		curl_setopt( $handle, CURLOPT_URL, $fullurl );
		curl_setopt( $handle, CURLOPT_HTTPHEADER, $headers );
		curl_setopt( $handle, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $handle, CURLOPT_HEADER, 1 );
		$response = curl_exec( $handle );
		$code = curl_getinfo( $handle, CURLINFO_HTTP_CODE );

		if ( $code === 401 && !$retrying ) {
			$wgMemc->delete(
				wfMemcKey( 'openstackmanager', "fulltoken-" . $this->getProject(), $this->username )
			);
			return $this->restCall( $service, $path, $method, $data, $authHeaders, true );
		}

		$header_size = curl_getinfo( $handle, CURLINFO_HEADER_SIZE );
		$response_headers = substr( $response, 0, $header_size );
		$body = substr( $response, $header_size );
		$body = json_decode( $body );

		return array( 'code' => $code, 'body' => $body, 'response_headers' => $response_headers );
	}

	static function _get_property( $object, $id ) {
		if ( isset( $object ) && is_object( $object ) ) {
			if ( property_exists( $object, $id ) ) {
				return $object->$id;
			}
		}
		return null;
	}
}
