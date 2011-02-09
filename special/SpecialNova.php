<?php

abstract class SpecialNova extends SpecialPage {

	/**
	 * @return void
	 */
	function notLoggedIn() {
		global $wgOut;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-notloggedin' ) );
		$wgOut->wrapWikiMsg( '<div>$1</div>', array( 'openstackmanager-mustbeloggedin' ) );
	}

	/**
	 * @return void
	 */
	function noCredentials() {
		global $wgOut;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-nonovacred' ) );
		$wgOut->wrapWikiMsg( '<div>$1</div>', array( 'openstackmanager-nonovacred-admincreate' ) );
	}

	/**
	 * @return void
	 */
	function notInProject() {
		global $wgOut;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-noaccount' ) );
		$wgOut->wrapWikiMsg( '<div>$1</div>', array( 'openstackmanager-noaccount2' ) );
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
			$wgOut->wrapWikiMsg( '<div>$1</div>', array( 'openstackmanager-needsysadminrole2' ) );
		} else if ( $role == 'netadmin' ) {
			$wgOut->setPagetitle( wfMsg( 'openstackmanager-neednetadminrole' ) );
			$wgOut->wrapWikiMsg( '<div>$1</div>', array( 'openstackmanager-neednetadminrole2' ) );
		} else if ( $role == 'cloudadmin' ) {
			$wgOut->setPagetitle( wfMsg( 'openstackmanager-needcloudadminrole' ) );
			$wgOut->wrapWikiMsg( '<div>$1</div>', array( 'openstackmanager-needcloudadminrole2' ) );
		}
	}
}
