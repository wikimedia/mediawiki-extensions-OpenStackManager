<?php
class SpecialNovaAddress extends SpecialNova {

	var $adminNova;
	var $userNova;
	var $userLDAP;

	function __construct() {
		parent::__construct( 'NovaAddress' );
	}

	function execute( $par ) {
		global $wgRequest, $wgUser;
		global $wgOpenStackManagerNovaAdminKeys;

		if ( ! $wgUser->isLoggedIn() ) {
			$this->notLoggedIn();
			return false;
		}
		$this->userLDAP = new OpenStackNovaUser();
                if ( ! $this->userLDAP->exists() ) {
                        $this->noCredentials();
                        return true;
                }
		$adminCredentials = $wgOpenStackManagerNovaAdminKeys;
		$this->adminNova = new OpenStackNovaController( $adminCredentials );

        	$action = $wgRequest->getVal( 'action' );
		if ( $action == "allocate" ) {
			$this->allocateAddress();
		} else if ( $action == "release" ) {
			$this->releaseAddress();
		} else if ( $action == "associate" ) {
			$this->associateAddress();
		} else if ( $action == "disassociate" ) {
			$this->disassociateAddress();
		} else {
			$this->listAddresses();
		}
	}

	function allocateAddress() {
		global $wgRequest, $wgOut;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-allocateaddress' ) );

		$project = $wgRequest->getText( 'project' );
		if ( ! $this->userLDAP->inRole( 'netadmin', $project ) ) {
			$this->notInRole( 'netadmin' );
			return false;
		}
                $userCredentials = $this->userLDAP->getCredentials( $project );
                $this->userNova = new OpenStackNovaController( $userCredentials );
		if ( ! $wgRequest->wasPosted() ) {
			$out = Html::element( 'p', array(), wfMsgExt( 'openstackmanager-allocateaddress-confirm', array(), $project ) );
			$wgOut->addHTML( $out );
		}
		$addressInfo = Array();
		$addressInfo['project'] = array(
			'type' => 'hidden',
			'default' => $project,
		);
		$addressInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'allocate',
		);

		$addressForm = new SpecialNovaAddressForm( $addressInfo, 'openstackmanager-novaaddress' );
		$addressForm->setTitle( SpecialPage::getTitleFor( 'NovaAddress' ) );
		$addressForm->setSubmitID( 'novaaddress-form-allocateaddresssubmit' );
		$addressForm->setSubmitCallback( array( $this, 'tryAllocateSubmit' ) );
		$addressForm->show();

		return true;
	}

	function releaseAddress() {
		global $wgOut, $wgRequest;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-releaseaddress' ) );

		$project = $wgRequest->getText( 'project' );
		if ( ! $this->userLDAP->inRole( 'netadmin', $project ) ) {
			$this->notInRole( 'netadmin' );
			return false;
		}
                $userCredentials = $this->userLDAP->getCredentials( $project );
                $this->userNova = new OpenStackNovaController( $userCredentials );
		$ip = $wgRequest->getText( 'ip' );
		if ( ! $wgRequest->wasPosted() ) {
			$out = Html::element( 'p', array(), wfMsgExt( 'openstackmanager-releaseaddress-confirm', array(), $ip ) );
			$wgOut->addHTML( $out );
		}
		$addressInfo = Array();
		$addressInfo['project'] = array(
			'type' => 'hidden',
			'default' => $project,
		);
		$addressInfo['ip'] = array(
			'type' => 'hidden',
			'default' => $ip,
		);
		$addressInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'release',
		);
		$addressForm = new SpecialNovaAddressForm( $addressInfo, 'openstackmanager-novaaddress' );
		$addressForm->setTitle( SpecialPage::getTitleFor( 'NovaAddress' ) );
		$addressForm->setSubmitID( 'novaaddress-form-releaseaddresssubmit' );
		$addressForm->setSubmitCallback( array( $this, 'tryReleaseSubmit' ) );
		$addressForm->setSubmitText( 'confirm' );
		$addressForm->show();

		return true;
	}

	function associateAddress() {
		global $wgOut, $wgRequest;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-associateaddress' ) );

		$ip = $wgRequest->getText( 'ip' );
		$project = $wgRequest->getText( 'project' );
		if ( ! $this->userLDAP->inRole( 'netadmin', $project ) ) {
			$this->notInRole( 'netadmin' );
			return false;
		}
                $userCredentials = $this->userLDAP->getCredentials( $project );
                $this->userNova = new OpenStackNovaController( $userCredentials );
		$instances = $this->userNova->getInstances();
		$instance_keys = array();
		foreach ( $instances as $instance ) {
			if ( $instance->getOwner() == $project ) {
				$instancename = $instance->getInstanceName();
				$instanceid = $instance->getInstanceId();
				$instance_keys["$instancename"] = $instanceid;
			}
		}
		$addressInfo = array();
		$addressInfo['project'] = array(
			'type' => 'hidden',
			'default' => $project,
		);
		$addressInfo['ip'] = array(
			'type' => 'hidden',
			'default' => $ip,
		);
		$addressInfo['instanceid'] = array(
			'type' => 'select',
			'label-message' => 'openstackmanager-instancename',
			'options' => $instance_keys,
		);
		$addressInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'associate',
		);
		$addressForm = new SpecialNovaAddressForm( $addressInfo, 'openstackmanager-novaaddress' );
		$addressForm->setTitle( SpecialPage::getTitleFor( 'NovaAddress' ) );
		$addressForm->setSubmitID( 'novaaddress-form-releaseaddresssubmit' );
		$addressForm->setSubmitCallback( array( $this, 'tryAssociateSubmit' ) );
		$addressForm->show();

	}

	function disassociateAddress() {
		global $wgOut, $wgRequest;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-disassociateaddress' ) );

		$project = $wgRequest->getText( 'project' );
		if ( ! $this->userLDAP->inRole( 'netadmin', $project ) ) {
			$this->notInRole( 'netadmin' );
			return false;
		}
                $userCredentials = $this->userLDAP->getCredentials( $project );
                $this->userNova = new OpenStackNovaController( $userCredentials );
		$ip = $wgRequest->getText( 'ip' );
		if ( ! $wgRequest->wasPosted() ) {
			$out = Html::element( 'p', array(), wfMsgExt( 'openstackmanager-disassociateaddress-confirm', array(), $ip ) );
			$wgOut->addHTML( $out );
		}
		$addressInfo = Array();
		$addressInfo['project'] = array(
			'type' => 'hidden',
			'default' => $project,
		);
		$addressInfo['ip'] = array(
			'type' => 'hidden',
			'default' => $ip,
		);
		$addressInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'disassociate',
		);
		$addressForm = new SpecialNovaAddressForm( $addressInfo, 'openstackmanager-novaaddress' );
		$addressForm->setTitle( SpecialPage::getTitleFor( 'NovaAddress' ) );
		$addressForm->setSubmitID( 'novaaddress-form-disassociateaddresssubmit' );
		$addressForm->setSubmitCallback( array( $this, 'tryDisassociateSubmit' ) );
		$addressForm->setSubmitText( 'confirm' );
		$addressForm->show();

	}

	function listAddresses() {
		global $wgOut, $wgUser;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-addresslist' ) );

		$userProjects = $this->userLDAP->getProjects();
		$out = '';
		$sk = $wgUser->getSkin();
		$header = Html::element( 'th', array(), wfMsg( 'openstackmanager-address' ) );
		$header .= Html::element( 'th', array(), wfMsg( 'openstackmanager-instanceid' ) );
		$header .= Html::element( 'th', array(), wfMsg( 'openstackmanager-instancename' ) );
		$header .= Html::element( 'th', array(), wfMsg( 'openstackmanager-actions' ) );
		$addresses = $this->adminNova->getAddresses();
		$projectArr = array();
		foreach ( $addresses as $address ) {
			$ip = $address->getPublicIP();
			$instanceid = $address->getInstanceId();
			$project = $address->getProject();
			$addressOut = Html::element( 'td', array(), $ip );
			if ( $instanceid ) {
				$addressOut .= Html::element( 'td', array(), $instanceid );
				$instance = $this->adminNova->getInstance( $instanceid );
				$instancename = $instance->getInstanceName();
				$addressOut .= Html::element( 'td', array(), $instancename );
			} else {
				$addressOut .= Html::element( 'td', array(), '' );
				$addressOut .= Html::element( 'td', array(), '' );
			}
			if ( $instanceid ) {
				$msg = wfMsg( 'openstackmanager-reassociateaddress' );
			} else {
				$msg = wfMsg( 'openstackmanager-releaseaddress' );
				$link = $sk->link( $this->getTitle(), $msg, array(),
						   array( 'action' => 'release', 'ip' => $ip, 'project' => $project ), array() );
				$actions = Html::rawElement( 'li', array(), $link );
				$msg = wfMsg( 'openstackmanager-associateaddress' );
			}
			$link = $sk->link( $this->getTitle(), $msg, array(),
					   array( 'action' => 'associate', 'ip' => $ip, 'project' => $project ), array() );
			$actions .= Html::rawElement( 'li', array(), $link );
			if ( $instanceid ) {
				$msg = wfMsg( 'openstackmanager-disassociateaddress' );
				$link = $sk->link( $this->getTitle(), $msg, array(),
						   array( 'action' => 'disassociate', 'ip' => $ip, 'project' => $project ), array() );
				$actions .= Html::rawElement( 'li', array(), $link );
			}
			$actions = Html::rawElement( 'ul', array(), $actions );
			$addressOut .= Html::rawElement( 'td', array(), $actions );
			$projectArr["$project"] = Html::rawElement( 'tr', array(), $addressOut );
		}
		foreach ( $userProjects as $project ) {
			$out .= Html::element( 'h2', array(), $project );
			$out .= $sk->link( $this->getTitle(), wfMsg( 'openstackmanager-allocateaddress' ), array(), array( 'action' => 'allocate', 'project' => $project ), array() );
			if ( isset( $projectArr["$project"] ) ) {
				$projectOut = $header;
				$projectOut .= $projectArr["$project"];
				$out .= Html::rawElement( 'table',
							  array( 'id' => 'novainstancelist', 'class' => 'wikitable' ), $projectOut );
			}
		}
		$wgOut->addHTML( $out );
	}

	function tryAllocateSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut, $wgUser;

		$address = $this->userNova->allocateAddress();
		if ( ! $address ) {
			$out = Html::element( 'p', array(), wfMsg( 'openstackmanager-allocateaddressfailed' ) );
			$wgOut->addHTML( $out );
			return false;
		}
		$ip = $address->getPublicIP();
		$out = Html::element( 'p', array(), wfMsgExt( 'openstackmanager-allocatedaddress' , array(), $ip ) );
		$out .= '<br />';
		$sk = $wgUser->getSkin();
		$out .= $sk->link( $this->getTitle(), wfMsg( 'openstackmanager-backaddresslist' ), array(), array(), array() );
		$wgOut->addHTML( $out );

		return true;
	}

	function tryReleaseSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut, $wgUser;

		$ip = $formData['ip'];
		$success = $this->userNova->releaseAddress( $ip );
		if ( $success ) {
			$out = Html::element( 'p', array(), wfMsgExt( 'openstackmanager-releasedaddress', array(), $ip ) );
		} else {
			$out = Html::element( 'p', array(), wfMsgExt( 'openstackmanager-releaseaddressfailed', array(), $ip ) );
		}
		$out .= '<br />';
		$sk = $wgUser->getSkin();
		$out .= $sk->link( $this->getTitle(), wfMsg( 'openstackmanager-backaddresslist' ), array(), array(), array() );
		$wgOut->addHTML( $out );

		return true;
	}

	function tryAssociateSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut, $wgUser;

		$instanceid = $formData['instanceid'];
		$ip = $formData['ip'];
		$address = $this->userNova->associateAddress( $instanceid, $ip );
		if ( $address ) {
			$out = Html::element( 'p', array(), wfMsgExt( 'openstackmanager-associatedaddress', array(), $ip, $instanceid ) );
		} else {
			$out = Html::element( 'p', array(), wfMsgExt( 'openstackmanager-associateaddressfailed', array(), $ip, $instanceid ) );
		}
		$out .= '<br />';
		$sk = $wgUser->getSkin();
		$out .= $sk->link( $this->getTitle(), wfMsg( 'openstackmanager-backaddresslist' ), array(), array(), array() );
		$wgOut->addHTML( $out );

		return true;
	}

	function tryDisassociateSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut, $wgUser;

		$ip = $formData['ip'];
		$address = $this->userNova->disassociateAddress( $ip );
		if ( $address ) {
			$out = Html::element( 'p', array(), wfMsgExt( 'openstackmanager-disassociatedaddress', array(), $ip ) );
		} else {
			$out = Html::element( 'p', array(), wfMsgExt( 'openstackmanager-disassociateaddressfailed', array(), $ip ) );
		}
		$out .= '<br />';
		$sk = $wgUser->getSkin();
		$out .= $sk->link( $this->getTitle(), wfMsg( 'openstackmanager-backaddresslist' ), array(), array(), array() );
		$wgOut->addHTML( $out );

		return true;
	}

}

class SpecialNovaAddressForm extends HTMLForm {
}
