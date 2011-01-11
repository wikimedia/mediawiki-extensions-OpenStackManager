<?php
class SpecialNovaKey extends SpecialNova {

	var $userNova, $userLDAP;

	function __construct() {
		parent::__construct( 'NovaKey' );
	}

	function execute( $par ) {
		global $wgRequest, $wgUser;

		if ( ! $wgUser->isLoggedIn() ) {
			$this->notLoggedIn();
			return true;
		}
		$this->userLDAP = new OpenStackNovaUser();
		if ( ! $this->userLDAP->exists() ) {
			$this->noCredentials();
			return true;
		}

		$action = $wgRequest->getVal( 'action' );
		if ( $action == "import" ) {
			$this->importKey();
		} else if ( $action == "delete" ) {
			$this->deleteKey();
		} else {
			$this->listKeys();
		}
	}

	function importKey() {
		global $wgRequest, $wgOut;
		global $wgOpenStackManagerNovaKeypairStorage;

		if ( $wgOpenStackManagerNovaKeypairStorage == 'nova' ) {
			$project = $wgRequest->getVal( 'project' );
			if ( $project && ! $this->userLDAP->inProject( $project ) ) {
				$this->notInProject();
				return true;
			}
			$userCredentials = $this->userLDAP->getCredentials( $project );
			$this->userNova = new OpenStackNovaController( $userCredentials );
		}

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-importkey' ) );

		$keyInfo = Array();

		if ( $wgOpenStackManagerNovaKeypairStorage == 'nova' ) {
			$keyInfo['keyname'] = array(
				'type' => 'text',
				'label-message' => 'openstackmanager-keyname',
				'default' => '',
				'section' => 'key/info',
			);
		}

		$keyInfo['key'] = array(
			'type' => 'textarea',
			'section' => 'key/info',
			'default' => '',
			'label-message' => 'openstackmanager-key',
		);

		$keyInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'import',
		);

		$keyInfo['project'] = array(
			'type' => 'hidden',
			'default' => htmlentities( $project ),
		);

		$keyForm = new SpecialNovaKeyForm( $keyInfo, 'openstackmanager-novakey' );
		$keyForm->setTitle( SpecialPage::getTitleFor( 'NovaKey' ) );
		$keyForm->setSubmitID( 'novakey-form-createkeysubmit' );
		$keyForm->setSubmitCallback( array( $this, 'tryImportSubmit' ) );
		$keyForm->show();

	}

	function deleteKey() {
		global $wgOut, $wgRequest;
		global $wgOpenStackManagerNovaKeypairStorage;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-deletekey' ) );

		$keyInfo = Array();

		if ( $wgOpenStackManagerNovaKeypairStorage == 'nova' ) {
			$keyname = $wgRequest->getVal( 'keyname' );
			$project = $wgRequest->getVal( 'project' );
			if ( $project && ! $this->userLDAP->inProject( $project ) ) {
				$this->notInProject();
				return true;
			}
			$keyInfo['keyname'] = array(
				'type' => 'hidden',
				'default' => $project,
			);
			$keyInfo['project'] = array(
				'type' => 'hidden',
				'default' => $keyname,
			);
		} else if ( $wgOpenStackManagerNovaKeypairStorage == 'ldap' ) {
			$hash = $wgRequest->getVal( 'hash' );
			$keypairs = $this->userLDAP->getKeypairs();
			if ( ! $wgRequest->wasPosted() ) {
				$out = Html::element( 'pre', array(), $keypairs[$hash] );
				$out .= Html::element( 'p', array(), wfMsg( 'openstackmanager-deletekeyconfirm' ) );
				$wgOut->addHTML( $out );
			}
			$keyInfo['hash'] = array(
				'type' => 'hidden',
				'default' => $hash,
			);
		}
		$keyInfo['key'] = array(
			'type' => 'hidden',
			'default' => $keypairs[$hash],
		);
		$keyInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'delete',
		);
		$keyForm = new SpecialNovaKeyForm( $keyInfo, 'openstackmanager-novakey' );
		$keyForm->setTitle( SpecialPage::getTitleFor( 'NovaKey' ) );
		$keyForm->setSubmitID( 'novakey-form-deletekeysubmit' );
		$keyForm->setSubmitCallback( array( $this, 'tryDeleteSubmit' ) );
		$keyForm->setSubmitText( 'confirm' );
		$keyForm->show();
		return true;
	}

	function listKeys() {
		global $wgOut, $wgUser;
		global $wgOpenStackManagerNovaKeypairStorage;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-keylist' ) );

		$out = '';
		$sk = $wgUser->getSkin();
		if ( $wgOpenStackManagerNovaKeypairStorage == 'nova' ) {
			$out .= $sk->link( $this->getTitle(), wfMsg( 'openstackmanager-importkey' ), array(), array( 'action' => 'import' ), array() );
			$projects = $this->userLDAP->getProjects();
			foreach ( $projects as $project ) {
				$userCredentials = $this->userLDAP->getCredentials( $project );
				$this->userNova = new OpenStackNovaController( $userCredentials );
				$keypairs = $this->userNova->getKeypairs();
				if ( ! $keypairs ) {
					continue;
				}
				$out .= Html::element( 'h2', array(), $project );
				$projectOut = Html::element( 'th', array(), wfMsg( 'openstackmanager-name' ) );
				$projectOut .= Html::element( 'th', array(), wfMsg( 'openstackmanager-fingerprint' ) );
				foreach ( $keypairs as $keypair ) {
					$keyOut = Html::element( 'td', array(), $keypair->getKeyName() );
					$keyOut .= Html::element( 'td', array(), $keypair->getKeyFingerprint() );
					$projectOut .= Html::rawElement( 'tr', array(), $keyOut );
				}
				$out .= Html::rawElement( 'table', array( 'id' => 'novakeylist', 'class' => 'wikitable' ), $projectOut );
			}
		} else if ( $wgOpenStackManagerNovaKeypairStorage == 'ldap' ) {
			$out .= $sk->link( $this->getTitle(), wfMsg( 'openstackmanager-importkey' ), array(), array( 'action' => 'import' ), array() );
			$keypairs = $this->userLDAP->getKeypairs();
			$keysOut = '';
			foreach ( $keypairs as $hash => $key ) {
				$keyOut = Html::element( 'td', array(), $key );
				$msg = wfMsg( 'openstackmanager-delete' );
				$link = $sk->link( $this->getTitle(), $msg, array(), array( 'action' => 'delete', 'hash' => $hash ), array() );
				$keyOut .= Html::rawElement( 'td', array(), $link );
				$keysOut .= Html::rawElement( 'tr', array(), $keyOut );
			}
			$out .= Html::rawElement( 'table', array(), $keysOut );
		} else {
			$out = Html::element( 'p', array(), wfMsg( 'openstackmanager-invalidkeypair' ) );
		}

		$wgOut->addHTML( $out );
	}

	function tryImportSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut, $wgUser;
		global $wgOpenStackManagerNovaKeypairStorage;

		if ( $wgOpenStackManagerNovaKeypairStorage == 'ldap' ) {
			$success = $this->userLDAP->importKeypair( $formData['key'] );
			if ( ! $success ) {
				$out = Html::element( 'p', array(), wfMsg( 'openstackmanager-keypairimportfailed' ) );
				$wgOut->addHTML( $out );
				return false;
			}
			$out = Html::element( 'p', array(), wfMsg( 'openstackmanager-keypairimported' ) );
		} else if ( $wgOpenStackManagerNovaKeypairStorage == 'nova' ) {
			# wgOpenStackManagerNovaKeypairStorage == 'nova'
			# OpenStack's EC2 API doesn't yet support importing keys, use
			# of this option isn't currently recommended
			$keypair = $this->userNova->importKeypair( $formData['keyname'], $formData['key'] );

			$out = Html::element( 'p', array(), wfMsgExt( 'openstackmanager-keypairimportedfingerprint', array(),
			                                              $keypair->getKeyName(), $keypair->getKeyFingerprint() ) );
		} else {
			$out = Html::element( 'p', array(), wfMsg( 'openstackmanager-invalidkeypair' ) );
		}
		$out .= '<br />';
		$sk = $wgUser->getSkin();
		$out .= $sk->link( $this->getTitle(), wfMsg( 'openstackmanager-backkeylist' ), array(), array(), array() );
		$wgOut->addHTML( $out );
		return true;
	}

	function tryDeleteSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut, $wgUser;
		global $wgOpenStackManagerNovaKeypairStorage;

		$success = $this->userLDAP->deleteKeypair( $formData['key'] );
		if ( $success ) {
			$out = Html::element( 'p', array(), wfMsg( 'openstackmanager-deletedkey' ) );
		} else {
			$out = Html::element( 'p', array(), wfMsg( 'openstackmanager-deletedkeyfailed' ) );
		}
		$out .= '<br />';
		$sk = $wgUser->getSkin();
		$out .= $sk->link( $this->getTitle(), wfMsg( 'openstackmanager-backkeylist' ), array(), array(), array() );
		$wgOut->addHTML( $out );
		return true;
	}
}

class SpecialNovaKeyForm extends HTMLForm {
}
