<?php

abstract class SpecialNova extends SpecialPage {

	/**
	 * @return void
	 */
	function notLoggedIn() {
		global $wgOut;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-notloggedin' ) );
		$wgOut->addHTML( wfMsg( 'openstackmanager-mustbeloggedin' ) );
	}

	/**
	 * @return void
	 */
	function noCredentials() {
		global $wgOut;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-nonovacred' ) );
		$wgOut->addHTML( wfMsg( 'openstackmanager-nonovacred-admincreate' ) );
	}

	/**
	 * @return void
	 */
	function notInProject() {
		global $wgOut;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-noaccount' ) );
		$wgOut->addHTML( wfMsg( 'openstackmanager-noaccount2' ) );
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
			$wgOut->addHTML( wfMsg( 'openstackmanager-needsysadminrole2' ) );
		} else if ( $role == 'netadmin' ) {
			$wgOut->setPagetitle( wfMsg( 'openstackmanager-neednetadminrole' ) );
			$wgOut->addHTML( wfMsg( 'openstackmanager-neednetadminrole2' ) );
		}
	}
}
