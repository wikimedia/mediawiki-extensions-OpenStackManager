<?php

abstract class SpecialNova extends SpecialPage {

	/**
	 * @return void
	 */
	function notLoggedIn() {
		global $wgOut;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-notloggedin' ) );
		$wgOut->addWikiMsg( 'openstackmanager-mustbeloggedin' );
	}

	/**
	 * @return void
	 */
	function noCredentials() {
		global $wgOut;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-nonovacred' ) );
		$wgOut->addWikiMsg( 'openstackmanager-nonovacred-admincreate' );
	}

	/**
	 * @return void
	 */
	function notInProject() {
		global $wgOut;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-noaccount' ) );
		$wgOut->addWikiMsg( 'openstackmanager-noaccount2' );
	}

	/**
	 * @param  $role
	 * @return void
	 */
	function notInRole( $role ) {
		global $wgOut;

		$this->setHeaders();
		if ( $role == 'sysadmin' ) {
			$wgOut->setPagetitle( wfMsg( 'openstackmanager-needsysadminrole' ) );
			$wgOut->addWikiMsg( 'openstackmanager-needsysadminrole2' );
		} else if ( $role == 'netadmin' ) {
			$wgOut->setPagetitle( wfMsg( 'openstackmanager-neednetadminrole' ) );
			$wgOut->addWikiMsg( 'openstackmanager-neednetadminrole2' );
		} else if ( $role == 'cloudadmin' ) {
			$wgOut->setPagetitle( wfMsg( 'openstackmanager-needcloudadminrole' ) );
			$wgOut->addWikiMsg( 'openstackmanager-needcloudadminrole2' );
		}
	}

	/**
	 * @param  $hostname
	 * @return bool|string
	 */
	function validateText( $hostname, $error ) {
		if ( ! preg_match( "/^[a-z][a-z0-9\-]*$/", $hostname ) ) {
			return Xml::element( 'span', array( 'class' => 'error' ), wfMsg( $error ) );
		} else {
			return true;
		}
	}

}
