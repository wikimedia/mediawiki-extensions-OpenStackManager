<?php

/**
 * Special page from nova domain
 *
 * @file
 * @ingroup Extensions
 */

class SpecialNovaDomain extends SpecialNova {

	var $adminNova;
	var $userLDAP;

	function __construct() {
		parent::__construct( 'NovaDomain' );

		global $wgOpenStackManagerNovaAdminKeys;

		$this->userLDAP = new OpenStackNovaUser();
		$this->adminNova = new OpenStackNovaController( $wgOpenStackManagerNovaAdminKeys );
	}

	function execute( $par ) {
		global $wgOpenStackManagerLDAPRolesIntersect;

		if ( !$this->getUser()->isLoggedIn() ) {
			$this->notLoggedIn();
			return;
		}
		if ( !$this->userLDAP->exists() ) {
			$this->noCredentials();
			return;
		}
		# Must be in the global role
		if ( $wgOpenStackManagerLDAPRolesIntersect ) {
			# If roles intersect, we need to require cloudadmins, since
			# users are required to be in netadmins to manage project
			# specific netadmin things
			if ( !$this->userLDAP->inGlobalRole( 'cloudadmin' ) ) {
				$this->notInRole( 'cloudadmin' );
				return;
			}
		} elseif ( !$this->userLDAP->inGlobalRole( 'netadmin' ) ) {
			$this->notInRole( 'netadmin' );
			return;
		}

		$action = $this->getRequest()->getVal( 'action' );
		if ( $action == "delete" ) {
			$this->deleteDomain();
		} else {
			$this->listDomains();
		}
	}

	/**
	 * @return bool
	 */
	function deleteDomain() {
		$this->setHeaders();
		$this->getOutput()->setPagetitle( wfMsg( 'openstackmanager-deletedomain' ) );

		$domainname = $this->getRequest()->getText( 'domainname' );
		if ( ! $this->getRequest()->wasPosted() ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-deletedomain-confirm', $domainname );
		}
		$domainInfo = array();
		$domainInfo['domainname'] = array(
			'type' => 'hidden',
			'default' => $domainname,
			'name' => 'domainname',
		);
		$domainInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'delete',
			'name' => 'action',
		);
		$domainForm = new SpecialNovaDomainForm( $domainInfo, 'openstackmanager-novadomain' );
		$domainForm->setTitle( SpecialPage::getTitleFor( 'NovaDomain' ) );
		$domainForm->setSubmitID( 'novadomain-form-deletedomainsubmit' );
		$domainForm->setSubmitCallback( array( $this, 'tryDeleteSubmit' ) );
		$domainForm->show();

		return true;
	}

	/**
	 * @return void
	 */
	function listDomains() {
		$this->setHeaders();
		$this->getOutput()->setPagetitle( wfMsg( 'openstackmanager-domainlist' ) );

		$domainInfo = array();
		$domainInfo['domainname'] = array(
			'type' => 'text',
			'label-message' => 'openstackmanager-domainname',
			'default' => '',
			'section' => 'domain',
			'name' => 'domainname',
		);
		$domainInfo['fqdn'] = array(
			'type' => 'text',
			'label-message' => 'openstackmanager-fqdn',
			'default' => '',
			'section' => 'domain',
			'name' => 'fqdn',
		);
		$domainInfo['location'] = array(
			'type' => 'text',
			'label-message' => 'openstackmanager-location',
			'default' => '',
			'section' => 'domain',
			'help-message' => 'openstackmanager-location-help',
			'name' => 'location',
		);
		$domainInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'create',
			'name' => 'action',
		);

		$domainForm = new SpecialNovaDomainForm( $domainInfo, 'openstackmanager-novadomain' );
		$domainForm->setTitle( SpecialPage::getTitleFor( 'NovaDomain' ) );
		$domainForm->setSubmitID( 'novadomain-form-createdomainsubmit' );
		$domainForm->setSubmitCallback( array( $this, 'tryCreateSubmit' ) );
		$domainForm->show();

		$headers = Array( 'openstackmanager-domainname', 'openstackmanager-fqdn', 'openstackmanager-location', 'openstackmanager-actions' );
		$domains = OpenStackNovaDomain::getAllDomains();
		$domainRows = Array();
		foreach ( $domains as $domain ) {
			$domainRow = Array();
			$domainName = $domain->getDomainName();
			$this->pushResourceColumn( $domainRow, $domainName );
			$this->pushResourceColumn( $domainRow, $domain->getFullyQualifiedDomainName() );
			$this->pushResourceColumn( $domainRow, $domain->getLocation() );
			$this->pushRawResourceColumn( $domainRow, $this->createActionLink( 'openstackmanager-delete', array( 'action' => 'delete', 'domainname' => $domainName ) ) );
			array_push( $domainRows, $domainRow );
		}
		if ( $domainRows ) {
			$out = createResourceTable( $headers, $domainRows );
		} else {
			$out = '';
		}

		$this->getOutput()->addHTML( $out );
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryCreateSubmit( $formData, $entryPoint = 'internal' ) {
		$success = OpenStackNovaDomain::createDomain( $formData['domainname'], $formData['fqdn'], $formData['location'] );
		if ( ! $success ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-createdomainfailed' );
			return false;
		}
		$this->getOutput()->addWikiMsg( 'openstackmanager-createddomain' );

		$out = '<br />';
		$out .= Linker::link( $this->getTitle(), wfMsgHtml( 'openstackmanager-addadditionaldomain' ) );
		$this->getOutput()->addHTML( $out );

		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryDeleteSubmit( $formData, $entryPoint = 'internal' ) {
		$success = OpenStackNovaDomain::deleteDomain( $formData['domainname'] );
		if ( $success ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-deleteddomain' );
		} else {
			$this->getOutput()->addWikiMsg( 'openstackmanager-failedeleteddomain' );
		}

		$out = '<br />';
		$out .= Linker::link( $this->getTitle(), wfMsgHtml( 'openstackmanager-backdomainlist' ) );
		$this->getOutput()->addHTML( $out );

		return true;
	}

}

class SpecialNovaDomainForm extends HTMLForm {
}
