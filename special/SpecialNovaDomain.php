<?php

/**
 * Special page from nova domain
 *
 * @file
 * @ingroup Extensions
 */

class SpecialNovaDomain extends SpecialNova {
	public $userLDAP;

	function __construct() {
		parent::__construct( 'NovaDomain', 'managednsdomain' );
	}

	function execute( $par ) {
		if ( !$this->getUser()->isLoggedIn() ) {
			$this->notLoggedIn();
			return;
		}
		$this->userLDAP = new OpenStackNovaUser();
		if ( !$this->userLDAP->exists() ) {
			$this->noCredentials();
			return;
		}
		if ( !$this->userCanExecute( $this->getUser() ) ) {
			$this->displayRestrictionError();
			return;
		}
		$this->checkTwoFactor();

		$action = $this->getRequest()->getVal( 'action' );
		if ( $action === "delete" ) {
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
		$this->getOutput()->setPageTitle( $this->msg( 'openstackmanager-deletedomain' ) );

		$domainname = $this->getRequest()->getText( 'domainname' );
		if ( !$this->getRequest()->wasPosted() ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-deletedomain-confirm', $domainname );
		}
		$domainInfo = [];
		$domainInfo['domainname'] = [
			'type' => 'hidden',
			'default' => $domainname,
			'name' => 'domainname',
		];
		$domainInfo['action'] = [
			'type' => 'hidden',
			'default' => 'delete',
			'name' => 'action',
		];
		$domainForm = new HTMLForm(
			$domainInfo,
			$this->getContext(),
			'openstackmanager-novadomain'
		);
		$domainForm->setSubmitID( 'novadomain-form-deletedomainsubmit' );
		$domainForm->setSubmitCallback( [ $this, 'tryDeleteSubmit' ] );
		$domainForm->show();

		return true;
	}

	/**
	 * @return void
	 */
	function listDomains() {
		$this->setHeaders();
		$this->getOutput()->setPageTitle( $this->msg( 'openstackmanager-domainlist' ) );
		$this->getOutput()->addModuleStyles( 'ext.openstack' );

		$domainInfo = [];
		$domainInfo['domainname'] = [
			'type' => 'text',
			'label-message' => 'openstackmanager-domainname',
			'default' => '',
			'section' => 'domain',
			'name' => 'domainname',
		];
		$domainInfo['fqdn'] = [
			'type' => 'text',
			'label-message' => 'openstackmanager-fqdn',
			'default' => '',
			'section' => 'domain',
			'name' => 'fqdn',
		];
		$domainInfo['location'] = [
			'type' => 'text',
			'label-message' => 'openstackmanager-location',
			'default' => '',
			'section' => 'domain',
			'help-message' => 'openstackmanager-location-help',
			'name' => 'location',
		];
		$domainInfo['action'] = [
			'type' => 'hidden',
			'default' => 'create',
			'name' => 'action',
		];

		$domainForm = new HTMLForm(
			$domainInfo,
			$this->getContext(),
			'openstackmanager-novadomain'
		);
		$domainForm->setSubmitID( 'novadomain-form-createdomainsubmit' );
		$domainForm->setSubmitCallback( [ $this, 'tryCreateSubmit' ] );
		$domainForm->show();

		$headers = [
			'openstackmanager-domainname',
			'openstackmanager-fqdn',
			'openstackmanager-location',
			'openstackmanager-actions'
		];
		$domains = OpenStackNovaDomain::getAllDomains();
		$domainRows = [];
		foreach ( $domains as $domain ) {
			$domainRow = [];
			$domainName = $domain->getDomainName();
			$this->pushResourceColumn( $domainRow, $domainName );
			$this->pushResourceColumn( $domainRow, $domain->getFullyQualifiedDomainName() );
			$this->pushResourceColumn( $domainRow, $domain->getLocation() );
			$this->pushRawResourceColumn(
				$domainRow,
				$this->createActionLink(
					'openstackmanager-delete',
					[ 'action' => 'delete', 'domainname' => $domainName ]
				)
			);
			$domainRows[] = $domainRow;
		}
		if ( $domainRows ) {
			$out = $this->createResourceTable( $headers, $domainRows );
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
		$success = OpenStackNovaDomain::createDomain(
			$formData['domainname'], $formData['fqdn'], $formData['location']
		);
		if ( !$success ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-createdomainfailed' );
			return false;
		}
		$this->getOutput()->addWikiMsg( 'openstackmanager-createddomain' );

		$out = '<br />';
		$out .= Linker::link(
			$this->getPageTitle(),
			$this->msg( 'openstackmanager-addadditionaldomain' )->escaped()
		);
		$this->getOutput()->addHTML( $out );

		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryDeleteSubmit( $formData, $entryPoint = 'internal' ) {
		list( $success, $errmsg ) = OpenStackNovaDomain::deleteDomain( $formData['domainname'] );
		if ( $success ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-deleteddomain' );
		} else {
			$this->getOutput()->addWikiMsg( $errmsg );
		}

		$out = '<br />';
		$out .= Linker::link(
			$this->getPageTitle(),
			$this->msg( 'openstackmanager-backdomainlist' )->escaped()
		);
		$this->getOutput()->addHTML( $out );

		return true;
	}

	protected function getGroupName() {
		return 'nova';
	}
}
