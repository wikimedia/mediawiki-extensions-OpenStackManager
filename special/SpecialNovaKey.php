<?php

/**
 * special page for nova key
 *
 * @file
 * @ingroup Extensions
 */

class SpecialNovaKey extends SpecialNova {
	public $userNova;

	/**
	 * @var OpenStackNovaUser
	 */
	public $userLDAP;

	function __construct() {
		parent::__construct( 'NovaKey' );
	}

	function execute( $par ) {
		if ( !$this->getUser()->isLoggedIn() ) {
			$this->notLoggedIn();
			return;
		}
		$this->userLDAP = new OpenStackNovaUser();

		$action = $this->getRequest()->getVal( 'action' );
		if ( $action === "import" ) {
			$this->importKey(); // FIXME: Method is undefined
		} elseif ( $action === "delete" ) {
			$this->deleteKey();
		} else {
			$this->addKey();
		}
	}

	/**
	 * @return bool
	 */
	function deleteKey() {
		global $wgOpenStackManagerNovaKeypairStorage;

		$this->setHeaders();
		$this->getOutput()->setPagetitle( $this->msg( 'openstackmanager-deletekey' ) );
		$returnto = $this->getRequest()->getVal( 'returnto' );

		$keyInfo = array();
		$hash = '';
		$keypairs = array();

		if ( $wgOpenStackManagerNovaKeypairStorage === 'nova' ) {
			$keyname = $this->getRequest()->getVal( 'keyname' );
			$project = $this->getRequest()->getVal( 'project' );
			if ( $project && ! $this->userLDAP->inProject( $project ) ) {
				$this->notInProject( $project );
				return true;
			}
			$keyInfo['keyname'] = array(
				'type' => 'hidden',
				'default' => $project,
				'name' => 'keyname',
			);
			$keyInfo['project'] = array(
				'type' => 'hidden',
				'default' => $keyname,
				'name' => 'project',
			);
		} elseif ( $wgOpenStackManagerNovaKeypairStorage === 'ldap' ) {
			$hash = $this->getRequest()->getVal( 'hash' );
			$keypairs = $this->userLDAP->getKeypairs();
			if ( ! $this->getRequest()->wasPosted() ) {
				$this->getOutput()->addHTML( Html::element( 'pre', array(), $keypairs[$hash] ) );
				$this->getOutput()->addWikiMsg( 'openstackmanager-deletekeyconfirm' );
			}
			$keyInfo['hash'] = array(
				'type' => 'hidden',
				'default' => $hash,
				'name' => 'hash',
			);
		}
		$keyInfo['key'] = array(
			'type' => 'hidden',
			'default' => $keypairs[$hash],
			'name' => 'key',
		);
		$keyInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'delete',
			'name' => 'action',
		);
		$keyInfo['returnto'] = array(
			'type' => 'hidden',
			'default' => $returnto,
			'name' => 'returnto',
		);
		$keyForm = new HTMLForm(
			$keyInfo,
			$this->getContext(),
			'openstackmanager-novakey'
		);
		$keyForm->setSubmitID( 'novakey-form-deletekeysubmit' );
		$keyForm->setSubmitCallback( array( $this, 'tryDeleteSubmit' ) );
		$keyForm->show();
		return true;
	}

	function addKey() {
		global $wgOpenStackManagerNovaKeypairStorage;

		$this->setHeaders();
		$this->getOutput()->setPagetitle( $this->msg( 'openstackmanager-addkey' ) );
		$returnto = $this->getRequest()->getVal( 'returnto' );

		$keyInfo = array();
		if ( $wgOpenStackManagerNovaKeypairStorage === 'nova' ) {
			$projects = $this->userLDAP->getProjects();
			$keyInfo['keyname'] = array(
				'type' => 'text',
				'label-message' => 'openstackmanager-novakeyname',
				'default' => '',
				'name' => 'keyname',
			);
			$project_keys = array();
			foreach ( $projects as $project ) {
				$project_keys[$project] = $project;
			}
			$keyInfo['project'] = array(
				'type' => 'select',
				'options' => $project_keys,
				'label-message' => 'openstackmanager-project',
				'name' => 'project',
			);
		}
		$keyInfo['key'] = array(
			'type' => 'textarea',
			'default' => '',
			'label-message' => 'openstackmanager-novapublickey',
			'name' => 'key',
		);
		$keyInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'add',
			'name' => 'action',
		);
		$keyInfo['returnto'] = array(
			'type' => 'hidden',
			'default' => $returnto,
			'name' => 'returnto',
		);

		$keyForm = new HTMLForm(
			$keyInfo,
			$this->getContext(),
			'openstackmanager-novakey'
		);
		$keyForm->setSubmitID( 'novakey-form-createkeysubmit' );
		$keyForm->setSubmitCallback( array( $this, 'tryImportSubmit' ) );
		$keyForm->show();
	}

	/**
	 * Converts a public ssh key to openssh format.
	 * @param $keydata string SSH public/private key in some format
	 * @return mixed Public key in openssh format or false
	 */
	static function opensshFormatKey( $keydata ) {

		$public = self::opensshFormatKeySshKeygen( $keydata );

		if ( !$public ) {
			$public = self::opensshFormatKeyPuttygen( $keydata );
		}

		return $public;
	}

	/**
	 * Converts a public ssh key to openssh format, using puttygen.
	 * @param $keydata string SSH public/private key in some format
	 * @return mixed Public key in openssh format or false
	 */
	static function opensshFormatKeyPuttygen( $keydata ) {
		global $wgPuttygen;

		if ( wfIsWindows() || !$wgPuttygen ) {
			return false;
		}

		// We need to store the key in a file, as puttygen opens it several times.
		$tmpfile = tmpfile();
		if (!$tmpfile)
			return false;

		fwrite( $tmpfile, $keydata );

		$descriptorspec = array(
		   0 => $tmpfile,
		   1 => array("pipe", "w"),
		   2 => array("file", wfGetNull(), "a")
		);

		$process = proc_open( escapeshellcmd( $wgPuttygen ) . ' -O public-openssh -o /dev/stdout /dev/stdin', $descriptorspec, $pipes );
		if ( $process === false )
			return false;

		$data = stream_get_contents( $pipes[1] );
		fclose( $pipes[1] );
		proc_close( $process );

		/* Overwrite the file with nulls, padded to the next 4KB boundary.
		 * This shouldn't be needed, as it is a public key material, and
		 * it's going to be stored in a place from which it's probably
		 * easier to retrieve than a deleted file.
		 * However, there's no reason to have it innecesary copies, in
		 * some cases (certain DSA keys) the private key can be extracted
		 * from public one, and there could be worse attacks in the future.
		 * Moreover, if someone provided the private key to Special:NovaKey,
		 * this function would strip it to the public part, but we'd still
		 * need not to keep such information we should have never been given.
		 */
		rewind( $tmpfile );
		fwrite( $tmpfile, str_repeat( "\0", strlen( $keydata ) + 4096 - strlen( $keydata ) % 4096 ) );
		fclose( $tmpfile );

		if ( $data === false || !preg_match( '/(^| )ssh-(rsa|dss) /', $data ) )
			return false;

		return $data;
	}
	 /**
	 * Converts a public ssh key to openssh format, using ssh-keygen.
	 * @param $keydata string SSH public/private key in some format
	 * @return mixed Public key in openssh format or false
	 */
	static function opensshFormatKeySshKeygen( $keydata ) {
		global $wgSshKeygen;

		if ( wfIsWindows() || !$wgSshKeygen )
			return false;

		if ( substr_compare( $keydata, 'PuTTY-User-Key-File-2:', 0, 22 ) == 0 ) {
			$keydata = explode( "\nPrivate-Lines:", $keydata, 2 );
			$keydata = $keydata[0] . "\n";
		}

		$descriptorspec = array(
		   0 => array("pipe", "r"),
		   1 => array("pipe", "w"),
		   2 => array("file", wfGetNull(), "a")
		);

		$process = proc_open( escapeshellcmd( $wgSshKeygen ) . ' -i -f /dev/stdin', $descriptorspec, $pipes );
		if ( $process === false )
			return false;

		fwrite( $pipes[0], $keydata );
		fclose( $pipes[0] );
		$data = stream_get_contents( $pipes[1] );

		fclose( $pipes[1] );
		proc_close( $process );

		if ( $data === false || !preg_match( '/(^| )ssh-(rsa|dss) /', $data ) )
			return false;

		return $data;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryImportSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOpenStackManagerNovaKeypairStorage;

		$key = trim( $formData['key'] ); # Because people copy paste it with an accidental newline
		$returnto = Title::newFromText( $formData['returnto'] );
		if ( !preg_match( '/(^| )ssh-(rsa|dss) /', $key ) ) {
			# This doesn't look like openssh format, it's probably a
			# Windows user providing it in PuTTY format.
			$key = self::opensshFormatKey( $key );
			if ( $key === false ) {
				$this->getOutput()->addWikiMsg( 'openstackmanager-keypairformatwrong' );
				if ( $returnto ) {
					$this->getOutput()->addReturnTo( $returnto );
				}
				return false;
			}
			$this->getOutput()->addWikiMsg( 'openstackmanager-keypairformatconverted' );
		}

		if ( $wgOpenStackManagerNovaKeypairStorage === 'ldap' ) {
			$success = $this->userLDAP->importKeypair( $key );
			if ( $success ) {
				$this->getOutput()->addWikiMsg( 'openstackmanager-keypairimported' );
			} else {
				$this->getOutput()->addWikiMsg( 'openstackmanager-keypairimportfailed' );
				if ( $returnto ) {
					$this->getOutput()->addReturnTo( $returnto );
				}
				return false;
			}
		} elseif ( $wgOpenStackManagerNovaKeypairStorage === 'nova' ) {
			$keypair = $this->userNova->importKeypair( $formData['keyname'], $key );

			$this->getOutput()->addWikiMsg( 'openstackmanager-keypairimportedfingerprint', $keypair->getKeyName(), $keypair->getKeyFingerprint() );
		} else {
			$this->getOutput()->addWikiMsg( 'openstackmanager-invalidkeypair' );
		}

		if ( $returnto ) {
			$this->getOutput()->addReturnTo( $returnto );
		}
		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryDeleteSubmit( $formData, $entryPoint = 'internal' ) {
		$success = $this->userLDAP->deleteKeypair( $formData['key'] );
		if ( $success ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-deletedkey' );
		} else {
			$this->getOutput()->addWikiMsg( 'openstackmanager-deletedkeyfailed' );
		}

		$returnto = Title::newFromText( $formData['returnto'] );
		if ( $returnto ) {
			$this->getOutput()->addReturnTo( $returnto );
		}
		return true;
	}
}
