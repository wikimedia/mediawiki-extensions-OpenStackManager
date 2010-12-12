<?php
class SpecialNovaKey extends SpecialPage {

	var $userNova, $userLDAP;

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

		$project = $wgRequest->getVal('project');
		$action = $wgRequest->getVal('action');
		if ( $action == "import" ) {
			$this->importKey();
		} else if ( $action == "delete" ) {
			if ( ! $this->userLDAP->inProject( $project ) ) {
				$this->notInProject();
				return true;
			}
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

		$project = $wgRequest->getVal('project');
		if ( $project && ! $this->userLDAP->inProject( $project ) ) {
			$this->notInProject();
			return true;
		}
		$userCredentials = $this->userLDAP->getCredentials( $project );
		$this->userNova = new OpenStackNovaController( $userCredentials );

		$this->setHeaders();
		$wgOut->setPagetitle("Import Key");
 
		# TODO: Add project name field

		$keyInfo = Array(); 
		$keyInfo['keyName'] = array(
			'type' => 'text',
			'label-message' => 'keyname',
			'default' => '',
			'section' => 'key/info',
		);

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

		#TODO: Add availablity zone field

		$keyForm = new SpecialNovaKeyForm( $keyInfo, 'novakey-form' );
		$keyForm->setTitle( SpecialPage::getTitleFor( 'NovaKey' ));
		$keyForm->setSubmitID( 'novakey-form-createkeysubmit' );
		$keyForm->setSubmitCallback( array( $this, 'tryImportSubmit' ) );
		$keyForm->show();

	}

	function deleteKey() {
		global $wgOut;

		$project = $wgRequest->getVal('project');
		if ( $project && ! $this->userLDAP->inProject( $project ) ) {
			$this->notInProject();
			return true;
		}
		$this->setHeaders();
		$wgOut->setPagetitle("Confirm key deletion");
		return true;
	}

	function listKeys() {
		global $wgOut;

		$this->setHeaders();
		$wgOut->setPagetitle("Key list");

		$out = '';
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
			$out .= Html::rawElement( 'table', array( 'id' => 'novainstancelist', 'class' => 'wikitable' ), $projectOut );
		}

		$wgOut->addHTML( $out );
	}

	function tryImportSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut;

		$success = $this->userLDAP->importKeypair( $formData['key'] );
		if ( ! $success ) {
			$out = Html::element( 'p', array(), 'Failed to import keypair' );
			return false;
		}
		# OpenStack's EC2 API doesn't yet support importing keys
		//$keypair = $this->userNova->importKeypair( $formData['keyname'], $formData['key'] );

		#$out = Html::element( 'p', array(), 'Imported keypair ' . $keypair->getKeyName() . ' with fingerprint ' . $keypair->getKeyFingerprint() );
		$out = Html::element( 'p', array(), 'Imported keypair' );

		$wgOut->addHTML( $out );
		return true;
	}
}

class SpecialNovaKeyForm extends HTMLForm {
}
