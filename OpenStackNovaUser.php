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

	function isAdmin() {
		if ( isset( $this->userInfo[0]['isadmin'] ) ) {
			$isAdmin = $this->userInfo[0]['isadmin'];
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

	function hasProjects() {
		$this->connect();
		return array();
	}

	function hasRoles() {
		$this->connect();
		return array();
	}

	function inProject( $project ) {
		global $wgAuth;
		global $wgOpenStackManagerLDAPProjectBaseDN;

		$this->connect();

		$filter = "(&(cn=$project)(member=$this->userDN))";
                $entry = ldap_search( $wgAuth->ldapconn, $wgOpenStackManagerLDAPProjectBaseDN, $filter );
		if ( $entry ) {
			$entries = ldap_get_entries( $wgAuth->ldapconn, $entry );
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

	function inRole( $role, $project) {
		$this->connect();

		return true;
	}

	function connect() {
		global $wgAuth;
		global $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword;

		$wgAuth->connect();
		$wgAuth->bindAs( $wgOpenStackManagerLDAPUser, $wgOpenStackManagerLDAPUserPassword );
	}
}
