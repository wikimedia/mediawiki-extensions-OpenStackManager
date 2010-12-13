<?php
class SpecialNovaKey extends SpecialPage {

	var $userNova, $userLDAP;
	var $keypairs;

	function __construct() {
		parent::__construct( 'NovaKey' );
	}
 
	function execute( $par ) {
		global $wgRequest;

		wfLoadExtensionMessages('OpenStackManager');
		$this->userLDAP = new OpenStackNovaUser();
		if ( ! $this->userLDAP->exists() ) {
			$this->noCredentials();
			return true;
		}

		$action = $wgRequest->getVal('action');
		if ( $action == "import" ) {
			$this->importKey();
		} else if ( $action == "delete" ) {
			$this->deleteKey();
		} else {
			$this->listKeys();
		}
	}

	function noCredentials() {
		global $wgOut;

		$this->setHeaders();
		$wgOut->setPagetitle("No Nova credentials found for your account");
		$wgOut->addHTML('<p>There were no Nova credentials found for your user account. Please ask a Nova administrator to create credentials for you.</p>');
	}

	function notInProject() {
		global $wgOut;

		$this->setHeaders();
		$wgOut->setPagetitle("Your account is not in the project requested");
		$wgOut->addHTML('<p>You can not complete the action requested as your user account is not in the project requested.</p>');
	}

	function importKey() { 
		global $wgRequest, $wgOut;
		global $wgOpenStackManagerNovaKeypairStorage;

		if ( $wgOpenStackManagerNovaKeypairStorage == 'nova' ) {
			$project = $wgRequest->getVal('project');
			if ( $project && ! $this->userLDAP->inProject( $project ) ) {
				$this->notInProject();
				return true;
			}
			$userCredentials = $this->userLDAP->getCredentials( $project );
			$this->userNova = new OpenStackNovaController( $userCredentials );
		}

		$this->setHeaders();
		$wgOut->setPagetitle("Import Key");
 
		$keyInfo = Array(); 

		if ( $wgOpenStackManagerNovaKeypairStorage == 'nova' ) {
			$keyInfo['keyName'] = array(
				'type' => 'text',
				'label-message' => 'keyname',
				'default' => '',
				'section' => 'key/info',
			);
		}

		$keyInfo['key'] = array(
			'type' => 'textarea',
			'section' => 'key/info',
			'default' => '',
			'label-message' => 'key',
		);

		$keyInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'import',
		);

		$keyInfo['project'] = array(
			'type' => 'hidden',
			'default' => htmlentities( $project ),
		);

		$keyForm = new SpecialNovaKeyForm( $keyInfo, 'novakey-form' );
		$keyForm->setTitle( SpecialPage::getTitleFor( 'NovaKey' ));
		$keyForm->setSubmitID( 'novakey-form-createkeysubmit' );
		$keyForm->setSubmitCallback( array( $this, 'tryImportSubmit' ) );
		$keyForm->show();

	}

	function deleteKey() {
		global $wgOut, $wgRequest;
		global $wgOpenStackManagerNovaKeypairStorage;

		$this->setHeaders();
		$wgOut->setPagetitle("Delete key");
		if ( $wgOpenStackManagerNovaKeypairStorage == 'nova' ) {
			$project = $wgRequest->getVal('project');
			if ( $project && ! $this->userLDAP->inProject( $project ) ) {
				$this->notInProject();
				return true;
			}
		} else if ( $wgOpenStackManagerNovaKeypairStorage == 'ldap' ) {
			$hash = $wgRequest->getVal( 'hash' );
			$keypairs = $this->userLDAP->getKeypairs();
			if ( ! $wgRequest->wasPosted() ) {
				$out = Html::element( 'pre', array(), $keypairs[$hash] );
				$out .= Html::element( 'p', array(), 'Are you sure you wish to delete the above key?' );
				$wgOut->addHTML( $out );
			}
	
			$keyInfo = Array(); 

			$keyInfo['hash'] = array(
				'type' => 'hidden',
				'default' => $hash,
			);
			$keyInfo['key'] = array(
				'type' => 'hidden',
				'default' => $keypairs[$hash],
			);
			$keyInfo['action'] = array(
				'type' => 'hidden',
				'default' => 'delete',
			);
			$keyForm = new SpecialNovaKeyForm( $keyInfo, 'novakey-form' );
			$keyForm->setTitle( SpecialPage::getTitleFor( 'NovaKey' ));
			$keyForm->setSubmitID( 'novakey-form-deletekeysubmit' );
			$keyForm->setSubmitCallback( array( $this, 'tryDeleteSubmit' ) );
			$keyForm->setSubmitText( 'confirm' );
			$keyForm->show();
		}
		return true;
	}

	function listKeys() {
		global $wgOut, $wgUser;
		global $wgOpenStackManagerNovaKeypairStorage;

		$this->setHeaders();
		$wgOut->setPagetitle("Key list");

		$out = '';
		if ( $wgOpenStackManagerNovaKeypairStorage == 'nova' ) {
			$projects = $this->userLDAP->getProjects();
			foreach( $projects as $project ) {
				$userCredentials = $this->userLDAP->getCredentials( $project );
				$this->userNova = new OpenStackNovaController( $userCredentials );
				$keypairs = $this->userNova->getKeypairs();
				if ( ! $keypairs ) {
					continue;
				}
				$out .= Html::element( 'h2', array(), $project );
				$projectOut = Html::element( 'th', array(), 'Name' );
				$projectOut .= Html::element( 'th', array(), 'Fingerprint' );
				foreach ( $keypairs as $keypair ) {
					$keyOut = Html::element( 'td', array(), $keypair->getKeyName() );
					$keyOut .= Html::element( 'td', array(), $keypair->getKeyFingerprint() );
					$projectOut .= Html::rawElement( 'tr', array(), $keyOut );
				}
				$out .= Html::rawElement( 'table', array( 'id' => 'novakeylist', 'class' => 'wikitable' ), $projectOut );
			}
		} else if ( $wgOpenStackManagerNovaKeypairStorage == 'ldap' ) {
			$keypairs = $this->userLDAP->getKeypairs();
			$keysOut = '';
			foreach ( $keypairs as $hash => $key ) {
				$keyOut = Html::element( 'td', array(), $key );
				$sk = $wgUser->getSkin();
				$link = $sk->link( $this->getTitle(), 'delete', array(), array( 'action' => 'delete', 'hash' => $hash ), array() );
				$keyOut .= Html::rawElement( 'td', array(), $link );
				$keysOut .= Html::rawElement( 'tr', array(), $keyOut );
			}
			$out .= Html::rawElement( 'table', array(), $keysOut );
		} else {
			$out = Html::element( 'p', array(), 'Invalid keypair location configured' );
		}

		$wgOut->addHTML( $out );
	}

	function tryImportSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut;
		global $wgOpenStackManagerNovaKeypairStorage;

		if ( $wgOpenStackManagerNovaKeypairStorage == 'ldap' ) {
			$success = $this->userLDAP->importKeypair( $formData['key'] );
			if ( ! $success ) {
				$out = Html::element( 'p', array(), 'Failed to import keypair' );
				return false;
			}
			$out = Html::element( 'p', array(), 'Imported keypair' );
		} else if ( $wgOpenStackManagerNovaKeypairStorage == 'nova' ) {
			#wgOpenStackManagerNovaKeypairStorage == 'nova'
			# OpenStack's EC2 API doesn't yet support importing keys, use
			# of this option isn't currently recommended
			$keypair = $this->userNova->importKeypair( $formData['keyname'], $formData['key'] );

			$out = Html::element( 'p', array(), 'Imported keypair ' . $keypair->getKeyName() . ' with fingerprint ' . $keypair->getKeyFingerprint() );
		} else {
			$out = Html::element( 'p', array(), 'Invalid keypair location configured' );
		}

		$wgOut->addHTML( $out );
		return true;
	}

	function tryDeleteSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut;
		global $wgOpenStackManagerNovaKeypairStorage;

		$success = $this->userLDAP->deleteKeypair( $formData['key'] );
		if ( $success ) {
			$out = Html::element( 'p', array(), 'Successfully deleted key' );
		} else {
			$out = Html::element( 'p', array(), 'Failed to delete key' );
		}
		$wgOut->addHTML( $out );
	}
}

class SpecialNovaKeyForm extends HTMLForm {
}
