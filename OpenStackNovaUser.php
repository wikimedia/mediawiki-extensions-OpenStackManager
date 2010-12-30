<?php

class OpenStackNovaUser {

	var $username;
	var $userDN;
	var $userInfo;

	function __construct( $username='' ) {
		$this->username = $username;
		$this->connect();
		$this->fetchUserInfo();
	}

	function fetchUserInfo() {
		global $wgAuth, $wgUser;

		if ( $this->username ) {
			$this->userDN = $wgAuth->getUserDN( strtolower( $this->username ) );
		} else {
			$this->userDN = $wgAuth->getUserDN( strtolower( $wgUser->getName() ) );
		}
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
		global $wgAuth;

		$this->fetchUserInfo();
		if ( $this->userInfo[0]['sshpublickey'] ) {
			$keys = $this->userInfo[0]['sshpublickey'];
			$keypairs = array();
			array_shift( $keys );
			foreach ( $keys as $key ) {
				$hash = md5( $key );
				$keypairs["$hash"] = $key;
			}
			return $keypairs;
		} else {
			$wgAuth->printDebug( "No keypairs found", NONSENSITIVE );
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
		wfSuppressWarnings();
		$result = ldap_search( $wgAuth->ldapconn, $wgOpenStackManagerLDAPProjectBaseDN, $filter );
		wfRestoreWarnings();
		if ( $result ) {
			wfSuppressWarnings();
			$entries = ldap_get_entries( $wgAuth->ldapconn, $result );
			wfRestoreWarnings();
			if ( $entries ) {
				# First entry is always a count
				array_shift( $entries );
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
		wfSuppressWarnings();
		$result = ldap_search( $wgAuth->ldapconn, $wgOpenStackManagerLDAPProjectBaseDN, $filter );
		wfRestoreWarnings();
		if ( $result ) {
			wfSuppressWarnings();
			$entries = ldap_get_entries( $wgAuth->ldapconn, $result );
			wfRestoreWarnings();
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

		$keypairs = array();
		if ( isset( $this->userInfo[0]['sshpublickey'] ) ) {
			$keypairs = $this->userInfo[0]['sshpublickey'];
			array_shift( $keypairs );
		}
		$keypairs[] = $key;
		$values['sshpublickey'] = $keypairs;
		wfSuppressWarnings();
		$success = ldap_modify( $wgAuth->ldapconn, $this->userDN, $values );
		wfRestoreWarnings();
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
			}
			unset( $keypairs[$index] );
			$values['sshpublickey'] = array();
			foreach ( $keypairs as $keypair ) {
				$values['sshpublickey'][] = $keypair;
			}
			wfSuppressWarnings();
			$success = ldap_modify( $wgAuth->ldapconn, $this->userDN, $values );
			wfRestoreWarnings();
			if ( $success ) {
				$wgAuth->printDebug( "Successfully deleted the user's sshpublickey", NONSENSITIVE );
				return true;
			} else {
				$wgAuth->printDebug( "Failed to delete the user's sshpublickey", NONSENSITIVE );
				return false;
			}
		} else {
			$wgAuth->printDebug( "User does not have a sshpublickey attribute", NONSENSITIVE );
			return false;
		}
	}

	static function uuid4() {
		$uuid = '';
		uuid_create( &$uuid );
		uuid_make( $uuid, UUID_MAKE_V4 );
		$uuidExport = '';
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
		if ( $attr == 'gidnumber' ) {
			$filter = "(objectclass=posixgroup)";
			$base = GROUPDN;
		} else {
			$filter = "(objectclass=posixaccount)";
			$base = USERDN;
		}
		wfSuppressWarnings();
		$result = ldap_search( $auth->ldapconn, $auth->getBaseDN( $base ), $filter );
		wfRestoreWarnings();
		if ( $result ) {
			wfSuppressWarnings();
			$entries = ldap_get_entries( $auth->ldapconn, $result );
			wfRestoreWarnings();
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
					if ( $attr == 'gidnumber' && $highest % 2 ) {
						# Ensure groups are always even, since they'll
						# be used for namespaces as well.
						$highest = $highest + 1;
					}
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
		$uidnumber = OpenStackNovaUser::getNextIdNumber( $auth, 'uidnumber' );
		if ( ! $uidnumber ) {
			$result = false;
			return false;
		}
		$values['uidnumber'] = $uidnumber;
		$values['gidnumber'] = $wgOpenStackManagerLDAPDefaultGid;
		$values['homedirectory'] = '/home/' . $username;

		$auth->printDebug( "User account's objectclasses: ", NONSENSITIVE, $values['objectclass'] );
		$auth->printDebug( "User account's attributes: ", HIGHLYSENSITIVE, $values );

		return true;
	}

	static function LDAPModifyUITemplate( &$template ) {
		$input = array( 'msg' => 'shellaccountname', 'type' => 'text', 'name' => 'shellaccountname', 'value' => '', 'helptext' => 'shellaccountnamehelp' );
		$template->set( 'extraInput', array( $input ) );

		return true;
	}

}
