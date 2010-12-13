<?php

class OpenStackNovaUser {

	var $userDN;
	var $userInfo;

	function __construct() {
		global $wgAuth, $wgUser;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;

		$this->connect();
		$this->userDN = $wgAuth->getUserDN( strtolower( $wgUser->getName() ) );
		$this->userInfo = $wgAuth->userInfo;
	}

	function getCredentials( $project='' ) {
		if ( isset( $this->userInfo[0]['accesskey'] ) ) {
			$accessKey = $this->userInfo[0]['accesskey'][0];
			$accessKey = $accessKey . ':' . $project;
		} else {
			$accessKey = '';
		}
		if ( isset( $this->userInfo[0]['secretkey'] ) ) {
			$secretKey = $this->userInfo[0]['secretkey'][0];
		} else {
			$secretKey = '';
		}
		return array( 'accessKey' => $accessKey, 'secretKey' => $secretKey );
	}

	function getKeypairs() {
		if ( isset( $this->userInfo[0]['sshpublickey'] ) ) {
			$keys = $this->userInfo[0]['sshpublickey'];
			$keypairs = array();
			if ( is_array( $keys ) ) {
				array_shift( $keys );
				foreach ( $keys as $key ) {
					$hash = md5( $key );
					$keypairs["$hash"] = $key;
				}
			} else {
				$hash = md5( $keys );
				$keypairs["$hash"] = $keys;
			}
			return $keypairs;
		} else {
			return array();
		}
	}

	function isAdmin() {
		if ( isset( $this->userInfo[0]['isadmin'] ) ) {
			$isAdmin = $this->userInfo[0]['isadmin'][0];
			if ( strtolower( $isAdmin ) == "true" ) {
				return true;
			}
		}
		return false;
	}

	function exists() {
		$credentials = $this->getCredentials();
		if ( $credentials['accessKey'] && $credentials['secretKey'] ) {
			return true;
		} else {
			return false;
		}
	}

	function getProjects() {
		global $wgAuth;
		global $wgOpenStackManagerLDAPProjectBaseDN;

		$this->connect();

		# All projects have a projectManager attribute, project
		# roles do not
		$projects = array();
		$filter = "(&(projectManager=*)(member=$this->userDN))";
                $result = ldap_search( $wgAuth->ldapconn, $wgOpenStackManagerLDAPProjectBaseDN, $filter );
		if ( $result ) {
			$entries = ldap_get_entries( $wgAuth->ldapconn, $result );
			if ( $entries ) {
				# First entry is always a count
				array_shift($entries);
				foreach ( $entries as $entry ) {
					array_push( $projects, $entry['cn'][0] );
				}
			}
		} else {
			$wgAuth->printDebug( "No result found when searching for user's projects", NONSENSITIVE );
		}
		return $projects;
	}

	function getRoles( $project='' ) {
		# Currently unsupported
		return array();
	}

	function inProject( $project ) {
		global $wgAuth;
		global $wgOpenStackManagerLDAPProjectBaseDN;

		$this->connect();

		$filter = "(&(cn=$project)(member=$this->userDN))";
                $result = ldap_search( $wgAuth->ldapconn, $wgOpenStackManagerLDAPProjectBaseDN, $filter );
		if ( $result ) {
			$entries = ldap_get_entries( $wgAuth->ldapconn, $result );
			if ( $entries ) {
				if ( $entries['count'] == "0" ) {
					$wgAuth->printDebug( "Couldn't find the user in project: $project", NONSENSITIVE );
					return false;
				} else {
					return true;
				}
			} else {
				return false;
			}
		} else {
			return false;
		}
	}

	function inRole( $role, $project='' ) {
		# Currently unsupported
		return true;
	}

	function connect() {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;

		$wgAuth->connect();
		$wgAuth->bindAs( $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword );
	}

	function importKeypair( $key ) {
		global $wgAuth;

		$this->connect();

		$keypairs = array();
		if ( isset( $this->userInfo[0]['sshpublickey'] ) ) {
			$keypairs = $this->userInfo[0]['sshpublickey'];
			array_shift( $keypairs );
		}
		$keypairs[] = $key;
		$values['sshpublickey'] = $keypairs;
		$success = @ldap_modify( $wgAuth->ldapconn, $this->userDN, $values );
		if ( $success ) {
			$wgAuth->printDebug( "Successfully imported the user's sshpublickey", NONSENSITIVE );
			return true;
		} else {
			$wgAuth->printDebug( "Failed to import the user's sshpublickey", NONSENSITIVE );
			return false;
		}
	}

	function deleteKeypair( $key ) {
		global $wgAuth;

		if ( isset( $this->userInfo[0]['sshpublickey'] ) ) {
			$keypairs = $this->userInfo[0]['sshpublickey'];
			array_shift( $keypairs );
			$index = array_search( $key, $keypairs );
			if ( $index === false ) {
				$wgAuth->printDebug( "Unable to find the sshpublickey to be deleted", NONSENSITIVE );
				return false;
			} else {
				unset( $keypairs[$index] );
			}
			if ( sizeof( $keypairs ) == 1 ) {
				$values['sshpublickey'] = $keypairs[0];
			} else {
				$values['sshpublickey'] = $keypairs;
			}
			$success = @ldap_modify( $wgAuth->ldapconn, $this->userDN, $values );
			if ( $success ) {
				$wgAuth->printDebug( "Successfully deleted the user's sshpublickey", NONSENSITIVE );
				return true;
			} else {
				$wgAuth->printDebug( "Failed to delete the user's sshpublickey", NONSENSITIVE );
				$wgAuth->printDebug( "KEY: $key", NONSENSITIVE );
				$wgAuth->printDebug( "sshpublickey: ", NONSENSITIVE, $values['sshpublickey'] );
				return false;
			}
		} else {
			$wgAuth->printDebug( "User does not have a sshpublickey attribute", NONSENSITIVE );
			return false;
		}
	}

	static function uuid4() {
		uuid_create( &$uuid );
		uuid_make( $uuid, UUID_MAKE_V4 );
		uuid_export( $uuid, UUID_FMT_STR, &$uuidExport );
		return trim( $uuidExport );
	}

	/**
	 * Does not ensure uniqueness during concurrent use!
	 * Also does not work when resource limits are set for
	 * LDAP queries by the client or the server.
	 *
	 * TODO: write a better and more efficient version of this.
	 */
	static function getNextIdNumber( $auth, $attr ) {
		$highest = '';
		$filter = "(objectclass=posixaccount)";
                $result = ldap_search( $auth->ldapconn, $auth->getBaseDN( USERDN ), $filter );
		if ( $result ) {
			$entries = ldap_get_entries( $auth->ldapconn, $result );
			if ( $entries ) {
				if ( $entries['count'] == "0" ) {
					$highest = '500';
				} else {
					array_shift( $entries );
					$uids = array();
					foreach ( $entries as $entry ) {
						array_push( $uids, $entry[$attr][0] );
					}
					sort( $uids, SORT_NUMERIC );
					$highest = array_pop( $uids ) + 1;
				}
			} else {
				$auth->printDebug( "Failed to find any entries when searching for next $attr", NONSENSITIVE );
			}
		} else {
			$auth->printDebug( "Failed to get a result searching for next $attr", NONSENSITIVE );
		}
		$auth->printDebug( "id returned: $highest", NONSENSITIVE );
		return $highest;
	}

	/**
	 * Hook to add objectclasses and attributes for users being created.
	 */
	static function LDAPSetCreationValues( $auth, $username, &$values, &$result ) {
		global $wgOpenStackManagerLDAPDefaultGid;

		$values['objectclass'][] = 'person';
		$values['objectclass'][] = 'novauser';
		$values['objectclass'][] = 'ldappublickey';
		$values['objectclass'][] = 'posixaccount';
		$values['objectclass'][] = 'shadowaccount';
		$values['accesskey'] = OpenStackNovaUser::uuid4();
		$values['secretkey'] = OpenStackNovaUser::uuid4();
		$values['isadmin'] = 'FALSE';
		$uid = OpenStackNovaUser::getNextIdNumber( $auth, 'uidnumber' );
		if ( ! $uid ) {
			$result = false;
			return false;
		}
		$values['uidnumber'] = $uid;
		$values['gidnumber'] = $wgOpenStackManagerLDAPDefaultGid;
		$values['homedirectory'] = '/home/' . $username;

		$auth->printDebug( "User account's objectclasses: ", NONSENSITIVE, $values['objectclass'] );
		$auth->printDebug( "User account's attributes: ", HIGHLYSENSITIVE, $values );

		return true;
	}

}
