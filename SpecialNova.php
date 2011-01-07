<?php
 
abstract class SpecialNova extends SpecialPage {
	function notLoggedIn() {
		global $wgOut;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-notloggedin' ) );
		$wgOut->addHTML( wfMsg( 'openstackmanager-mustbeloggedin' ) );
	}

	function noCredentials() {
		global $wgOut;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-nonovacred' ) );
		$wgOut->addHTML( wfMsg( 'openstackmanager-nonovacred-admincreate' ) );
	}

	function notInProject() {
		global $wgOut;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-noaccount' ) );
		$wgOut->addHTML( wfMsg( 'openstackmanager-noaccount2' ) );
	}
}
