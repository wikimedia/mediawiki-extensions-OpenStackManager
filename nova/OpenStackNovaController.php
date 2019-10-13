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
	 * @param OpenStackNovaUser $user
	 */
	function __construct( $user ) {
		$this->project = '';
		$this->token = '';

		$this->username = $user->getUsername();
		$this->user = $user;
	}

	/**
	 * @param OpenStackNovaUser $user
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
	 * @param string $attachmenttext
	 * @param string $mimetype
	 * @param string $filename
	 *
	 * @return string
	 */
	function getAttachmentMime( $attachmenttext, $mimetype, $filename ) {
		$endl = $this->getLineEnding();
		$attachment = 'Content-Type: ' . $mimetype . '; charset="us-ascii"' . $endl;
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
		$regions = [];
		if ( $serviceCatalog ) {
			foreach ( $serviceCatalog as $entry ) {
				if ( $entry->type === "identity" ) {
					foreach ( $entry->endpoints as $endpoint ) {
						if ( !$wgUser->isAllowed( 'accessrestrictedregions' ) &&
							in_array( $endpoint->region, $wgOpenStackManagerRestrictedRegions )
						) {
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
	 * @param string $id
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
		$addressesarr = [];
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
	 * @param string $instanceId
	 * @return null|OpenStackNovaInstance
	 */
	function getInstance( $instanceId ) {
		$instanceId = urlencode( $instanceId );
		$ret = $this->restCall( 'compute', '/servers/' . $instanceId, 'GET' );
		if ( self::isApiError( $ret['code'] ) ) {
			return null;
		}
		$server = self::_get_property( $ret['body'], 'server' );
		if ( $server ) {
			return new OpenStackNovaInstance( $server, $this->getRegion(), true );
		}
	}

	function createProxy( $fqdn, $backendHost, $backendPort ) {
		$data = [
			'domain' => $fqdn,
			'backends' => [ 'http://' . $backendHost . ':' . $backendPort ]
		];
		$ret = $this->restCall( 'proxy', '/mapping', 'PUT', $data );

		if ( self::isApiError( $ret['code'] ) ) {
			return null;
		}

		$proxyObj = new OpenStackNovaProxy( $this->project, $fqdn, $backendHost, $backendPort );
		return $proxyObj;
	}

	function deleteProxy( $fqdn ) {
		$ret = $this->restCall( 'proxy', '/mapping/' . $fqdn, 'DELETE' );
		return self::isApiSuccess( $ret['code'] );
	}

	/**
	 * @return array
	 */
	function getProxiesForProject() {
		$ldap = LdapAuthenticationPlugin::getInstance();
		$proxyarr = [];
		$ret = $this->restCall( 'proxy', '/mapping', 'GET' );
		$proxies = self::_get_property( $ret['body'], 'routes' );
		if ( !$proxies ) {
			return $proxyarr;
		}
		foreach ( $proxies as $proxy ) {
			$domain = self::_get_property( $proxy, 'domain' );
			$backends = self::_get_property( $proxy, 'backends' );

			if ( ( count( $backends ) ) > 1 ) {
				$ldap->printDebug( "Warning!  proxy $domain has multiple backends " .
					"but we only support one backend per proxy.", NONSENSITIVE );
			}
			'@phan-var array $backends';
			$backend = $backends[0];
			$backendarray = explode( ':', $backends[0] );

			if ( strpos( $backend, "http" ) === 0 ) {
				if ( ( count( $backendarray ) < 2 ) || ( count( $backendarray ) > 3 ) ) {
					$ldap->printDebug(
						"Unable to parse backend $backend, discarding.", NONSENSITIVE
					);
				} elseif ( count( $backendarray ) == 2 ) {
					$backendHost = $backend;
					$backendPort = null;
				} else {
					$backendHost = $backendarray[0] . ":" . $backendarray[1];
					$backendPort = $backendarray[2];
				}
			} else {
				if ( ( count( $backendarray ) < 1 ) || ( count( $backendarray ) > 2 ) ) {
					$ldap->printDebug(
						"Unable to parse backend $backend, discarding.", NONSENSITIVE
					);
				} elseif ( count( $backendarray ) == 1 ) {
					$backendHost = $backend;
					$backendPort = null;
				} else {
					$backendHost = $backendarray[0];
					$backendPort = $backendarray[1];
				}
			}

			if ( $backendPort ) {
				$proxyObj = new OpenStackNovaProxy(
					$this->project, $domain, $backendHost, $backendPort
				);
			} else {
				$proxyObj = new OpenStackNovaProxy( $this->project, $domain, $backendHost );
			}

			$proxyarr[] = $proxyObj;
		}
		return $proxyarr;
	}

	/**
	 * @return string a token for $wgOpenStackManagerLDAPUsername
	 *  who happens to have admin rights in Keystone.
	 */
	function _getAdminToken() {
		global $wgOpenStackManagerLDAPUsername, $wgOpenStackManagerLDAPUserPassword;
		global $wgMemc;

		$ldap = LdapAuthenticationPlugin::getInstance();

		if ( $this->admintoken ) {
			return $this->admintoken;
		}

		$key = wfMemcKey( 'openstackmanager', 'keystoneadmintoken' );

		$this->admintoken = $wgMemc->get( $key );
		if ( is_string( $this->admintoken ) ) {
			return $this->admintoken;
		}

		$data = [
			'auth' => [
				'identity' => [
					'methods' => [
						'password'
					],
					'password' => [
						'user' => [
							'domain' => [
								'name' => 'Default'
							],
							'name' => $wgOpenStackManagerLDAPUsername,
							'password' => $wgOpenStackManagerLDAPUserPassword,
						]
					]
				]
			]
		];
		$headers = [
			'Accept: application/json',
			'Content-Type: application/json',
		];
		$ret = $this->restCall(
			'identityv3', '/auth/tokens', 'POST', $data, $headers );
		if ( self::isApiError( $ret['code'] ) ) {
			$ldap->printDebug(
				"OpenStackNovaController::_getAdminToken return code: " . $ret['code'], NONSENSITIVE
			);
			return "";
		}

		// Keystone v3 returns the admin token as a header
		$this->admintoken = $ret['headers']['X-Subject-Token'] ?? '*BOGUS*';
		if ( $this->admintoken !== '*BOGUS*' ) {
			$wgMemc->set( $key, $this->admintoken, 300 );
		}

		return $this->admintoken;
	}

	/**
	 * @return array of project ids => project names
	 */
	function getProjects() {
		$admintoken = $this->_getAdminToken();
		$headers = [ "X-Auth-Token: $admintoken" ];

		$projarr = [];
		$ret = $this->restCall(
			'identityv3', '/projects', 'GET', [], $headers );
		$projects = self::_get_property( $ret['body'], 'projects' );
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
	 * @param string $projectid
	 * @return string
	 */
	function getProjectName( $projectid ) {
		$admintoken = $this->_getAdminToken();
		$headers = [ "X-Auth-Token: $admintoken" ];

		$userarr = [];
		$ret = $this->restCall(
			'identityv3', "/projects/$projectid", 'GET', [], $headers );
		$tenant = self::_get_property( $ret['body'], 'project' );
		// @phan-suppress-next-line PhanTypeExpectedObjectPropAccessButGotNull
		return $tenant->name;
	}

	/**
	 * @param string $projectname
	 * @return string id of new project or "" on failure
	 */
	function createProject( $projectname ) {
		// TODO: test this or remove
		$admintoken = $this->_getAdminToken();
		$headers = [
			'Accept: application/json',
			'Content-Type: application/json',
			"X-Auth-Token: $admintoken"
		];
		$projname = urlencode( $projectname );
		$data = [
			"project" => [
				"name" => $projname,
			],
		];

		$ret = $this->restCall(
			'identityv3', '/projects', 'POST', $data, $headers );
		if ( self::isApiError( $ret['code'] ) ) {
			return "";
		}
		$tenant = self::_get_property( $ret['body'], 'project' );
		return self::_get_property( $tenant, 'id' );
	}

	function deleteProject( $projectid ) {
		// TODO: test this or remove
		$admintoken = $this->_getAdminToken();
		$headers = [ "X-Auth-Token: $admintoken" ];

		$ret = $this->restCall(
			'identityv3', "/projects/$projectid", 'DELETE', [], $headers );
		return self::isApiSuccess( $ret['code'] );
	}

	/**
	 * @param string $projectid
	 * @return array of user IDs => user names
	 */
	function getUsersInProject( $projectid ) {
		global $wgOpenStackHiddenUsernames;

		$admintoken = $this->_getAdminToken();
		$headers = [ "X-Auth-Token: $admintoken" ];

		$userarr = [];
		$ret = $this->restCall(
			'identityv3',
			"/role_assignments?scope.project.id=" . urlencode( $projectid )
				. "&include_names=1",
			'GET', [], $headers
		);

		$role_assignments = self::_get_property(
			$ret['body'], 'role_assignments' );
		if ( !$role_assignments ) {
			return $userarr;
		}
		foreach ( $role_assignments as $assignment ) {
			$user = self::_get_property( $assignment, 'user' );
			if ( $user ) {
				$name = self::_get_property( $user, 'name' );
				$id = self::_get_property( $user, 'id' );
				if ( !in_array( $id, $wgOpenStackHiddenUsernames ) ) {
					$userarr[$id] = $name;
				}
			}
		}
		return $userarr;
	}

	/**
	 * @return array of $roleid => $rolename
	 */
	function getKeystoneRoles() {
		global $wgMemc;

		$key = wfMemcKey( 'openstackmanager', 'keystoneroles' );
		$rolearr = $wgMemc->get( $key );
		if ( is_array( $rolearr ) ) {
			return $rolearr;
		}

		$admintoken = $this->_getAdminToken();
		$headers = [ "X-Auth-Token: $admintoken" ];

		$rolearr = [];
		$ret = $this->restCall( 'identityv3', "/roles", 'GET', [], $headers );
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
	 * @param string $userid
	 * @return array of arrays:  project ID => role IDs
	 *
	 *  Return array only includes entries for projects
	 *  with roles assigned, so calling array_keys
	 *  on the return value will answer the question
	 *  'what projects is this user in?'
	 */
	function getRoleAssignmentsForUser( $userid ) {
		$admintoken = $this->_getAdminToken();
		$headers = [ "X-Auth-Token: $admintoken" ];

		$assignments = [];
		$ret = $this->restCall(
			'identityv3', "/role_assignments?user.id=" . urlencode( $userid ),
			'GET', [], $headers
		);
		$role_assignments = self::_get_property( $ret['body'], 'role_assignments' );
		if ( !$role_assignments ) {
			return $assignments;
		}
		foreach ( $role_assignments as $assignment ) {
			$scope = self::_get_property( $assignment, 'scope' );
			$role = self::_get_property( $assignment, 'role' );
			$roleid = self::_get_property( $role, 'id' );
			$project = self::_get_property( $scope, 'project' );
			$projectid = self::_get_property( $project, 'id' );

			$assignments[$projectid][] = $roleid;
		}
		return $assignments;
	}

	/**
	 * @param string $projectid
	 * @return array of arrays:  role ID => user IDs
	 */
	function getRoleAssignmentsForProject( $projectid ) {
		$admintoken = $this->_getAdminToken();
		$headers = [ "X-Auth-Token: $admintoken" ];

		$assignments = [];

		$ret = $this->restCall(
			'identityv3',
			"/role_assignments?scope.project.id=" . urlencode( $projectid ),
			'GET', [], $headers
		);
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
	 * @param string $projectid
	 * @param string $userid
	 * @return array role IDs => role Names
	 */
	function getRolesForProjectAndUser( $projectid, $userid ) {
		$admintoken = $this->_getAdminToken();
		$headers = [ "X-Auth-Token: $admintoken" ];

		$rolearr = [];
		$ret = $this->restCall(
			'identityv3',
			"/projects/" . urlencode( $projectid ) . "/users/"
				. urlencode( $userid ) . "/roles",
			'GET', [], $headers
		);
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
		$headers = [
			'Accept: application/json',
			'Content-Type: application/json',
			"X-Auth-Token: $admintoken"
		];

		$rolearr = [];
		$ret = $this->restCall(
			'identityv3',
			"/projects/" . urlencode( $projectid ) . "/users/"
				. urlencode( $userid ) . "/roles/" . urlencode( $roleid ),
			'PUT', [], $headers
		);
		return self::isApiSuccess( $ret['code'] );
	}

	function revokeRoleForProjectAndUser( $roleid, $projectid, $userid ) {
		$admintoken = $this->_getAdminToken();
		$headers = [
			'Accept: application/json',
			'Content-Type: application/json',
			"X-Auth-Token: $admintoken"
		];

		$rolearr = [];
		$ret = $this->restCall(
			'identityv3',
			"/projects/" . urlencode( $projectid ) . "/users/"
				. urlencode( $userid ) . "/roles/" . urlencode( $roleid ),
			'DELETE', [], $headers
		);
		return self::isApiSuccess( $ret['code'] );
	}

	/**
	 * @return OpenStackNovaInstance[]
	 */
	function getInstances() {
		$instancesarr = [];
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
	 * @param string $instancetypeid
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
		$instanceTypesarr = [];
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
	 * @param string $imageid
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
		$imagesarr = [];
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
	 * @param string $groupid
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
		$groups = [];
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
	 * @param string $instanceid
	 * @return string
	 */
	function getConsoleOutput( $instanceid ) {
		$instanceid = urlencode( $instanceid );
		$data = [ 'os-getConsoleOutput' => [ 'length' => null ] ];
		$ret = $this->restCall( 'compute', '/servers/' . $instanceid . '/action', 'POST', $data );
		if ( self::isApiError( $ret['code'] ) ) {
			return '';
		}
		return self::_get_property( $ret['body'], 'output' );
	}

	/**
	 * @param string $volumeId
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
		return [];
	}

	/**
	 * @param string $instanceName
	 * @param string $image
	 * @param string $key
	 * @param string $instanceType
	 * @param array $groups
	 * @return null|OpenStackNovaInstance
	 */
	function createInstance( $instanceName, $image, $key, $instanceType, $groups ) {
		global $wgOpenStackManagerInstanceUserData;

		$data = [ 'server' => [] ];
		if ( $key ) {
			$data['key_name'] = $key;
		}
		$data['server']['flavorRef'] = $instanceType;
		$data['server']['imageRef'] = $image;
		$data['server']['name'] = $instanceName;
		if ( $wgOpenStackManagerInstanceUserData ) {
			$random_hash = md5( date( 'r', time() ) );
			$endl = self::getLineEnding();
			$boundary = '===============' . $random_hash . '==';
			$userdata = 'Content-Type: multipart/mixed; boundary="' . $boundary . '"' . $endl;
			$userdata .= 'MIME-Version: 1.0' . $endl;
			$boundary = '--' . $boundary;
			$userdata .= $endl;
			$userdata .= $boundary;
			if ( $wgOpenStackManagerInstanceUserData['cloud-config'] ) {
				$userdata .= $endl . $this->getAttachmentMime(
					Spyc::YAMLDump( $wgOpenStackManagerInstanceUserData['cloud-config'] ),
					'text/cloud-config',
					'cloud-config.txt'
				);
				$userdata .= $endl . $boundary;
			}
			if ( $wgOpenStackManagerInstanceUserData['scripts'] ) {
				foreach (
					$wgOpenStackManagerInstanceUserData['scripts'] as $scriptname => $script
				) {
					Wikimedia\suppressWarnings();
					$stat = stat( $script );
					Wikimedia\restoreWarnings();
					if ( !$stat ) {
						continue;
					}
					$scripttext = file_get_contents( $script );
					$userdata .= $endl . $this->getAttachmentMime(
						$scripttext, 'text/x-shellscript', $scriptname
					);
					$userdata .= $endl . $boundary;
				}
			}
			if ( $wgOpenStackManagerInstanceUserData['upstarts'] ) {
				foreach (
					$wgOpenStackManagerInstanceUserData['upstarts'] as $upstartname => $upstart
				) {
					Wikimedia\suppressWarnings();
					$stat = stat( $upstart );
					Wikimedia\restoreWarnings();
					if ( !$stat ) {
						continue;
					}
					$upstarttext = file_get_contents( $upstart );
					$userdata .= $endl . $this->getAttachmentMime(
						$upstarttext, 'text/upstart-job', $upstartname
					);
					$userdata .= $endl . $boundary;
				}
			}
			$userdata .= '--';
			$data['server']['user_data'] = base64_encode( $userdata );
		}
		$data['server']['security_groups'] = [];
		foreach ( $groups as $group ) {
			$data['server']['security_groups'][] = [ 'name' => $group ];
		}
		$ret = $this->restCall( 'compute', '/servers', 'POST', $data );
		if ( self::isApiError( $ret['code'] ) ) {
			return null;
		}
		$instance = new OpenStackNovaInstance( $ret['body']->server, $this->getRegion() );

		return $instance;
	}

	/**
	 * @param string $instanceid
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

		return self::isApiSuccess( $ret['code'] );
	}

	/**
	 * @param string $groupname
	 * @param string $description
	 * @return null|OpenStackNovaSecurityGroup
	 */
	function createSecurityGroup( $groupname, $description ) {
		$data = [ 'security_group' => [ 'name' => $groupname, 'description' => $description ] ];
		$ret = $this->restCall( 'compute', '/os-security-groups', 'POST', $data );
		if ( self::isApiError( $ret['code'] ) ) {
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
	 * @param string $groupid
	 * @return bool
	 */
	function deleteSecurityGroup( $groupid ) {
		$groupid = urlencode( $groupid );
		$ret = $this->restCall( 'compute', '/os-security-groups/' . $groupid, 'DELETE' );

		return self::isApiSuccess( $ret['code'] );
	}

	/**
	 * @param string $groupid
	 * @param string $fromport
	 * @param string $toport
	 * @param string $protocol
	 * @param string $range
	 * @param string $group
	 * @return bool
	 */
	function addSecurityGroupRule(
		$groupid, $fromport = '', $toport = '', $protocol = '', $range = '', $group = ''
	) {
		if ( $group && $range ) {
			return false;
		} elseif ( $range ) {
			$data = [ 'security_group_rule' => [
				'parent_group_id' => (int)$groupid,
				'from_port' => $fromport,
				'to_port' => $toport,
				'ip_protocol' => $protocol,
				'cidr' => $range ]
			];
		} elseif ( $group ) {
			$data = [ 'security_group_rule' => [
				'parent_group_id' => (int)$groupid,
				'group_id' => (int)$group ]
			];
		}
		$ret = $this->restCall( 'compute', '/os-security-group-rules', 'POST', $data );

		return self::isApiSuccess( $ret['code'] );
	}

	/**
	 * @param string $ruleid
	 * @return bool
	 */
	function removeSecurityGroupRule( $ruleid ) {
		$ruleid = urlencode( $ruleid );
		$ret = $this->restCall( 'compute', '/os-security-group-rules/' . $ruleid, 'DELETE' );

		return self::isApiSuccess( $ret['code'] );
	}

	/**
	 * @return null|OpenStackNovaAddress
	 */
	function allocateAddress() {
		$ret = $this->restCall( 'compute', '/os-floating-ips', 'POST', [] );
		if ( self::isApiError( $ret['code'] ) ) {
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
	 * @param string $id
	 * @return bool
	 */
	function releaseAddress( $id ) {
		$id = urlencode( $id );
		$ret = $this->restCall( 'compute', '/os-floating-ips/' . $id, 'DELETE' );

		return self::isApiSuccess( $ret['code'] );
	}

	/**
	 * Attach new ip address to instance
	 *
	 * @param string $instanceid
	 * @param string $ip
	 * @return bool
	 */
	function associateAddress( $instanceid, $ip ) {
		$instanceid = urlencode( $instanceid );
		$data = [ 'addFloatingIp' => [ 'address' => $ip ] ];
		$ret = $this->restCall( 'compute', '/servers/' . $instanceid . '/action', 'POST', $data );

		return self::isApiSuccess( $ret['code'] );
	}

	/**
	 * Disassociate address from an instance
	 *
	 * @param string $instanceid
	 * @param string $ip
	 * @return bool
	 */
	function disassociateAddress( $instanceid, $ip ) {
		$instanceid = urlencode( $instanceid );
		$data = [ 'removeFloatingIp' => [ 'address' => $ip ] ];
		$ret = $this->restCall( 'compute', '/servers/' . $instanceid . '/action', 'POST', $data );

		return self::isApiSuccess( $ret['code'] );
	}

	/**
	 * Create a Nova volume
	 *
	 * @param string $zone
	 * @param string $size
	 * @param string $name
	 * @param string $description
	 * @return OpenStackNovaVolume
	 */
	function createVolume( $zone, $size, $name, $description ) {
		# Unimplemented
		return null;
	}

	/**
	 * Delete a Nova volume
	 *
	 * @param string $volumeid
	 * @return bool
	 */
	function deleteVolume( $volumeid ) {
		# unimplemented
		return false;
	}

	/**
	 * Attach a nova volume to the specified device on an instance
	 *
	 * @param string $volumeid
	 * @param string $instanceid
	 * @param string $device
	 * @return bool
	 */
	function attachVolume( $volumeid, $instanceid, $device ) {
		# unimplemented
		return false;
	}

	/**
	 * Detaches a nova volume from an instance
	 *
	 * @param string $volumeid
	 * @param string $force
	 * @return bool
	 */
	function detachVolume( $volumeid, $force ) {
		# unimplemented
		return false;
	}

	/**
	 * Reboots an instance
	 *
	 * @param string $instanceid
	 * @param string $type
	 * @return bool
	 */
	function rebootInstance( $instanceid, $type = 'SOFT' ) {
		$instanceid = urlencode( $instanceid );
		$data = [ 'reboot' => [ 'type' => $type ] ];
		$ret = $this->restCall( 'compute', '/servers/' . $instanceid . '/action', 'POST', $data );
		return self::isApiSuccess( $ret['code'] );
	}

	function getLimits() {
		$ret = $this->restCall( 'compute', '/limits', 'GET', [] );
		if ( self::isApiError( $ret['code'] ) ) {
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
		global $wgMemc;

		$ldap = LdapAuthenticationPlugin::getInstance();
		$ldap->printDebug( "Entering OpenStackNovaController::authenticate", NONSENSITIVE );
		$headers = [
			'Accept: application/json',
			'Content-Type: application/json',
		];
		$data = [
			'auth' => [
				'identity' => [
					'methods' => [
						'password'
					],
					'password' => [
						'user' => [
							'name' => $username,
							'domain' => [
								'name' => 'Default'
							],
							'password' => $password
						]
					]
				]
			]
		];
		$ret = $this->restCall(
			'identityv3', '/auth/tokens', 'POST', $data, $headers );
		if ( self::isApiError( $ret['code'] ) ) {
			$ldap->printDebug(
				"OpenStackNovaController::authenticate return code: " . $ret['code'], NONSENSITIVE
			);
			return '';
		}
		$this->token = $ret['headers']['X-Subject-Token'] ?? '*BOGUS*';
		if ( $this->token !== '*BOGUS*' ) {
			$key = wfMemcKey( 'openstackmanager', 'fulltoken', $username );
			// Expiration time is unnecessary. Token expiration is expected
			// to be longer than MediaWiki's token, so a re-auth will occur
			// before the generic token expires.
			$wgMemc->set( $key, $this->token );
		}

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
				$wikiuser = User::newFromName( $this->username );
				if ( !$wikiuser ) {
					// No user, no token
					return '';
				}
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
		$headers = [
			'Accept: application/json',
			'Content-Type: application/json',
		];
		$data = [ 'auth' => [ 'token' => [ 'id' => $token ], 'tenantName' => $project ] ];
		$path = '/tokens';
		$ret = $this->restCall( 'identity', $path, 'POST', $data, $headers );
		if ( self::isApiError( $ret['code'] ) ) {
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
		$endpoints = [];
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
		$headers = [
			'Accept: application/json',
			'Content-Type: application/json',
			'X-Auth-Project-Id: ' . preg_replace( "/[^a-z0-9-]/", "", $project ),
			'X-Auth-Token: ' . $token,
		];
		return $headers;
	}

	function restCall( $service, $path, $method, $data = [], $authHeaders = '', $retrying = false ) {
		global $wgOpenStackManagerNovaIdentityURI;
		global $wgOpenStackManagerNovaIdentityV3URI;
		global $wgMemc;

		$ldap = LdapAuthenticationPlugin::getInstance();
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
		$ldap->printDebug( "OpenStackNovaController::restCall fullurl: " . $fullurl, NONSENSITIVE );
		$handle = curl_init();
		switch ( $method ) {
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
		$raw_headers = substr( $response, 0, $header_size );
		$body = substr( $response, $header_size );
		$body = json_decode( $body );

		// Parse response headers to make using them easier
		$headers = [];
		foreach ( explode( "\r\n", $raw_headers ) as $line ) {
			if ( strpos( $line, ':' ) !== false ) {
				// Ignore HTTP status response and blank lines
				list( $key, $value ) = explode( ': ', $line );
				$headers[$key] = $value;
			}
		}

		return [
			'code' => $code,
			'body' => $body,
			'headers' => $headers,
		];
	}

	static function _get_property( $object, $id ) {
		if ( isset( $object ) && is_object( $object ) ) {
			if ( property_exists( $object, $id ) ) {
				return $object->$id;
			}
		}
		return null;
	}

	/**
	 * Is this http status code a sign of success?
	 *
	 * @param int $code
	 * @return bool
	 */
	static function isApiSuccess( $code ) {
		return $code >= 200 && $code < 300;
	}

	/**
	 * Is this http status code a sign of failure?
	 *
	 * @param int $code
	 * @return bool
	 */
	static function isApiError( $code ) {
		return $code < 200 || $code >= 300;
	}
}
