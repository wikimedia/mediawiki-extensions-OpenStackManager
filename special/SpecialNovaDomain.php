<?php
class SpecialNovaDomain extends SpecialNova {

	var $userNova, $adminNova;

	function __construct() {
		parent::__construct( 'NovaDomain' );

		global $wgOpenStackManagerNovaAdminKeys;

		$this->userLDAP = new OpenStackNovaUser();
		$this->adminNova = new OpenStackNovaController( $wgOpenStackManagerNovaAdminKeys );
	}

	function execute( $par ) {
		global $wgRequest, $wgUser;

		# if ( ! $wgUser->isAllowed( 'manageproject' ) ) {
		#	return false;
		# }
		if ( ! $wgUser->isLoggedIn() ) {
			$this->notLoggedIn();
			return false;
		}
		$project = $wgRequest->getText( 'project' );
		# Must be in the global role
		if ( ! $this->userLDAP->inRole( 'netadmin' ) ) {
			$this->notInRole( 'netadmin' );
			return false;
		}

		$action = $wgRequest->getVal( 'action' );
		if ( $action == "create" ) {
			$this->createDomain();
		} else if ( $action == "delete" ) {
			$this->deleteDomain();
		} else {
			$this->listDomains();
		}
	}

	function createDomain() {
		global $wgRequest, $wgOut;
		global $wgOpenStackManagerDNSOptions;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-createdomain' ) );

		$domainInfo = Array();
		$domainInfo['domainname'] = array(
			'type' => 'text',
			'label-message' => 'openstackmanager-domainname',
			'default' => '',
			'section' => 'domain/info',
		);
		$domainInfo['fqdn'] = array(
			'type' => 'text',
			'label-message' => 'openstackmanager-fqdn',
			'default' => '',
			'section' => 'domain/info',
		);
		$domainInfo['location'] = array(
			'type' => 'text',
			'label-message' => 'openstackmanager-location',
			'default' => '',
			'section' => 'domain/info',
			'help-message' => 'openstackmanager-location-help',
		);
		$domainInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'create',
		);

		$domainForm = new SpecialNovaDomainForm( $domainInfo, 'openstackmanager-novadomain' );
		$domainForm->setTitle( SpecialPage::getTitleFor( 'NovaDomain' ) );
		$domainForm->setSubmitID( 'novadomain-form-createdomainsubmit' );
		$domainForm->setSubmitCallback( array( $this, 'tryCreateSubmit' ) );
		$domainForm->show();

		return true;
	}

	function deleteDomain() {
		global $wgOut, $wgRequest;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-deletedomain' ) );

		$domainname = $wgRequest->getText( 'domainname' );
		if ( ! $wgRequest->wasPosted() ) {
			$out = Html::element( 'p', array(), wfMsgExt( 'openstackmanager-deletedomain-confirm', array(), $domainname ) );
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
		$domainForm = new SpecialNovaDomainForm( $domainInfo, 'openstackmanager-novadomain' );
		$domainForm->setTitle( SpecialPage::getTitleFor( 'NovaDomain' ) );
		$domainForm->setSubmitID( 'novadomain-form-deletedomainsubmit' );
		$domainForm->setSubmitCallback( array( $this, 'tryDeleteSubmit' ) );
		$domainForm->setSubmitText( 'confirm' );
		$domainForm->show();

		return true;
	}

	function listDomains() {
		global $wgOut, $wgUser;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-domainlist' ) );

		$out = '';
		$sk = $wgUser->getSkin();
		$out .= $sk->link( $this->getTitle(), wfMsg( 'openstackmanager-createdomain' ), array(), array( 'action' => 'create' ), array() );
		$domainsOut = Html::element( 'th', array(), wfMsg( 'openstackmanager-domainname' ) );
		$domainsOut .= Html::element( 'th', array(), wfMsg( 'openstackmanager-fqdn' ) );
		$domainsOut .= Html::element( 'th', array(), wfMsg( 'openstackmanager-location' ) );
		$domainsOut .= Html::element( 'th', array(), wfMsg( 'openstackmanager-actions' ) );
		$domains = OpenStackNovaDomain::getAllDomains();
		foreach ( $domains as $domain ) {
			$domainName = $domain->getDomainName();
			$fqdn = $domain->getFullyQualifiedDomainName();
			$location = $domain->getLocation();
			$domainOut = Html::element( 'td', array(), $domainName );
			$domainOut .= Html::element( 'td', array(), $fqdn );
			$domainOut .= Html::element( 'td', array(), $location );
			$msg = wfMsg( 'openstackmanager-delete' );
			$link = $sk->link( $this->getTitle(), $msg, array(),
							   array( 'action' => 'delete', 'domainname' => $domainName ), array() );
			$domainOut .= Html::rawElement( 'td', array(), $link );
			$domainsOut .= Html::rawElement( 'tr', array(), $domainOut );
		}
		if ( $domains ) {
			$out .= Html::rawElement( 'table', array( 'class' => 'wikitable' ), $domainsOut );
		}

		$wgOut->addHTML( $out );
	}

	function tryCreateSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut, $wgUser;

		$success = OpenStackNovaDomain::createDomain( $formData['domainname'], $formData['fqdn'], $formData['location'] );
		if ( ! $success ) {
			$out = Html::element( 'p', array(), wfMsg( 'openstackmanager-createdomainfailed' ) );
			$wgOut->addHTML( $out );
			return false;
		}
		$out = Html::element( 'p', array(), wfMsg( 'openstackmanager-createddomain' ) );
		$out .= '<br />';
		$sk = $wgUser->getSkin();
		$out .= $sk->link( $this->getTitle(), wfMsg( 'openstackmanager-backdomainlist' ), array(), array(), array() );
		$wgOut->addHTML( $out );

		return true;
	}

	function tryDeleteSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut, $wgUser;

		$success = OpenStackNovaDomain::deleteDomain( $formData['domainname'] );
		if ( $success ) {
			$out = Html::element( 'p', array(), wfMsg( 'openstackmanager-deleteddomain' ) );
		} else {
			$out = Html::element( 'p', array(), wfMsg( 'openstackmanager-failedeletedomain' ) );
		}
		$out .= '<br />';
		$sk = $wgUser->getSkin();
		$out .= $sk->link( $this->getTitle(), wfMsg( 'openstackmanager-backdomainlist' ), array(), array(), array() );
		$wgOut->addHTML( $out );

		return true;
	}

}

class SpecialNovaDomainForm extends HTMLForm {
}
