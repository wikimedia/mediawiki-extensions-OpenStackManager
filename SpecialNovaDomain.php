<?php
class SpecialNovaDomain extends SpecialPage {

	var $userNova, $adminNova;

	function __construct() {
		parent::__construct( 'NovaDomain' );

		global $wgOpenStackManagerNovaAdminKeys;

		$this->userLDAP = new OpenStackNovaUser();
		$this->adminNova = new OpenStackNovaController( $wgOpenStackManagerNovaAdminKeys );
	}

	public function isRestricted() {
		return true;
	}

	function execute( $par ) {
		global $wgRequest, $wgUser;

		#if ( ! $wgUser->isAllowed( 'manageproject' ) ) {
		#	return false;
		#}
		if ( ! $wgUser->isLoggedIn() ) {
			return false;
		}

		$action = $wgRequest->getVal('action');
		if ( $action == "create" ) {
			$this->createDomain();
		} else if ( $action == "delete" ) {
			$this->deleteDomain();
		} else {
			$this->listDomains();
		}
	}

	function notLoggedIn() {
			global $wgOut;

			$this->setHeaders();
			$wgOut->setPagetitle("Not logged in");
			$wgOut->addHTML('<p>You must be logged in to perform this action</p>');
	}

	function noCredentials() {
			global $wgOut;

			$this->setHeaders();
			$wgOut->setPagetitle("No Nova credentials found for your account");
			$wgOut->addHTML('<p>There were no Nova credentials found for your user account. ' .
							'Please ask a Nova administrator to create credentials for you.</p>');
	}

	function createDomain() { 
		global $wgRequest, $wgOut;

		$this->setHeaders();
		$wgOut->setPagetitle("Create Domain");

		$domainInfo = Array(); 
		$domainInfo['domainname'] = array(
			'type' => 'text',
			'label-message' => 'domainname',
			'default' => '',
			'section' => 'domain/info',
		);
		$domainInfo['fqdn'] = array(
			'type' => 'text',
			'label-message' => 'fqdn',
			'default' => '',
			'section' => 'domain/info',
		);


		$domainInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'create',
		);

		$domainForm = new SpecialNovaDomainForm( $domainInfo, 'novadomainform' );
		$domainForm->setTitle( SpecialPage::getTitleFor( 'NovaDomain' ) );
		$domainForm->setSubmitID( 'novadomain-form-createdomainsubmit' );
		$domainForm->setSubmitCallback( array( $this, 'tryCreateSubmit' ) );
		$domainForm->show();

		return true;
	}

	function deleteDomain() {
		global $wgOut, $wgRequest;

		$this->setHeaders();
		$wgOut->setPagetitle("Delete domain");

		$domainname = $wgRequest->getText('domainname');
		if ( ! $wgRequest->wasPosted() ) {
			$out = Html::element( 'p', array(), 'Are you sure you wish to delete domain "' . $domainname .
												'"? This action has reprecusions on all VMs. Do not take this action lightly!' );
			$wgOut->addHTML( $out );
		}
		$domainInfo = Array(); 
		$domainInfo['domainname'] = array(
			'type' => 'hidden',
			'default' => $domainname,
		);
		$domainInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'delete',
		);
		$domainForm = new SpecialNovaDomainForm( $domainInfo, 'novadomain-form' );
		$domainForm->setTitle( SpecialPage::getTitleFor( 'NovaDomain' ));
		$domainForm->setSubmitID( 'novadomain-form-deletedomainsubmit' );
		$domainForm->setSubmitCallback( array( $this, 'tryDeleteSubmit' ) );
		$domainForm->setSubmitText( 'confirm' );
		$domainForm->show();

		return true;
	}

	function listDomains() {
		global $wgOut, $wgUser;

		$this->setHeaders();
		$wgOut->setPagetitle("Domain list");

		$out = '';
		$sk = $wgUser->getSkin();
		$out .= $sk->link( $this->getTitle(), 'Create a new domain', array(), array( 'action' => 'create' ), array() );
				$domainsOut = Html::element( 'th', array(), 'Domain name' );
				$domainsOut .= Html::element( 'th', array(), 'FQDN' );
				$domainsOut .= Html::element( 'th', array(), 'Action' );
		$domains = OpenStackNovaDomain::getAllDomains();
		foreach ( $domains as $domain ) {
			$domainName = $domain->getDomainName();
			$fqdn = $domain->getFullyQualifiedDomainName();
			$domainOut = Html::element( 'td', array(), $domainName );
			$domainOut .= Html::element( 'td', array(), $fqdn );
			$link = $sk->link( $this->getTitle(), 'delete domain', array(),
							   array( 'action' => 'delete', 'domainname' => $domainName ), array() );
			$domainOut .= Html::rawElement( 'td', array(), $link );
			$domainsOut .= Html::rawElement( 'tr', array(), $domainOut );
		}
		$out .= Html::rawElement( 'table', array( 'class' => 'wikitable' ), $domainsOut );

		$wgOut->addHTML( $out );
	}

	function tryCreateSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut, $wgUser;

		$success = OpenStackNovaDomain::createDomain( $formData['domainname'], $formData['fqdn'] );
		if ( ! $success ) {
			$out = Html::element( 'p', array(), 'Failed to create domain' );
			return false;
		}
		$out = Html::element( 'p', array(), 'Created domain' );
		$out .= '<br />';
		$sk = $wgUser->getSkin();
		$out .= $sk->link( $this->getTitle(), 'Back to domain list', array(), array(), array() );
		$wgOut->addHTML( $out );

		return true;
	}

	function tryDeleteSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut, $wgUser;

		$success = OpenStackNovaDomain::deleteDomain( $formData['domainname'] );
		if ( $success ) {
			$out = Html::element( 'p', array(), 'Successfully deleted domain' );
		} else {
			$out = Html::element( 'p', array(), 'Failed to delete domain' );
		}
		$out .= '<br />';
		$sk = $wgUser->getSkin();
		$out .= $sk->link( $this->getTitle(), 'Back to domain list', array(), array(), array() );
		$wgOut->addHTML( $out );

		return true;
	}

}

class SpecialNovaDomainForm extends HTMLForm {
}
