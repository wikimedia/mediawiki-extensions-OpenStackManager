<?php

namespace MediaWiki\Extension\OpenStackManager;

use Html;
use HTMLForm;
use MediaWiki\Title\Title;
use SpecialPage;

/**
 * special page for nova key
 *
 * @file
 * @ingroup Extensions
 */

class SpecialNovaKey extends SpecialPage {
	/** @var OpenStackNovaUser */
	private $userLDAP;

	public function __construct() {
		parent::__construct( 'NovaKey' );
	}

	protected function getLoginSecurityLevel() {
		return $this->getName();
	}

	public function execute( $par ) {
		if ( !$this->getUser()->isRegistered() ) {
			$this->notLoggedIn();
			return;
		}

		if ( !$this->checkLoginSecurityLevel( $this->getLoginSecurityLevel() ) ) {
			return;
		}

		$this->userLDAP = new OpenStackNovaUser( $this->getUser()->getName() );

		$action = $this->getRequest()->getVal( 'action' );
		if ( $action === "delete" ) {
			$this->deleteKey();
		} else {
			$this->addKey();
		}
	}

	/**
	 * @return bool
	 */
	private function deleteKey() {
		$this->setHeaders();
		$this->getOutput()->setPageTitle( $this->msg( 'openstackmanager-deletekey' ) );
		$returnto = $this->getRequest()->getVal( 'returnto' );

		$keyInfo = [];

		$hash = $this->getRequest()->getVal( 'hash' );
		$keypairs = $this->userLDAP->getKeypairs();
		if ( !$this->getRequest()->wasPosted() ) {
			$this->getOutput()->addHTML( Html::element( 'pre', [], $keypairs[$hash] ) );
			$this->getOutput()->addWikiMsg( 'openstackmanager-deletekeyconfirm' );
		}
		$keyInfo['hash'] = [
			'type' => 'hidden',
			'default' => $hash,
			'name' => 'hash',
		];

		$keyInfo['key'] = [
			'type' => 'hidden',
			'default' => $keypairs[$hash],
			'name' => 'key',
		];
		$keyInfo['action'] = [
			'type' => 'hidden',
			'default' => 'delete',
			'name' => 'action',
		];
		$keyInfo['returnto'] = [
			'type' => 'hidden',
			'default' => $returnto,
			'name' => 'returnto',
		];
		$keyForm = HTMLForm::factory( 'ooui',
			$keyInfo,
			$this->getContext(),
			'openstackmanager-novakey'
		);
		$keyForm
			->setSubmitID( 'novakey-form-deletekeysubmit' )
			->setSubmitCallback( [ $this, 'tryDeleteSubmit' ] )
			->setSubmitDestructive()
			->show();
		return true;
	}

	private function addKey() {
		$this->setHeaders();
		$this->getOutput()->setPageTitle( $this->msg( 'openstackmanager-addkey' ) );
		$returnto = $this->getRequest()->getVal( 'returnto' );

		$keyInfo = [];
		$keyInfo['key'] = [
			'type' => 'textarea',
			'default' => '',
			'label-message' => 'openstackmanager-novapublickey',
			'name' => 'key',
		];
		$keyInfo['action'] = [
			'type' => 'hidden',
			'default' => 'add',
			'name' => 'action',
		];
		$keyInfo['returnto'] = [
			'type' => 'hidden',
			'default' => $returnto,
			'name' => 'returnto',
		];

		$keyForm = HTMLForm::factory( 'ooui',
			$keyInfo,
			$this->getContext(),
			'openstackmanager-novakey'
		);
		$keyForm
			->setSubmitID( 'novakey-form-createkeysubmit' )
			->setSubmitCallback( [ $this, 'tryImportSubmit' ] )
			->show();
	}

	/**
	 * Converts a public ssh key to openssh format using ssh-keygen.
	 * @param string $keydata SSH public/private key in some format
	 * @return mixed Public key in openssh format or false
	 */
	private static function opensshFormatKey( $keydata ) {
		global $wgSshKeygen;

		if ( wfIsWindows() || !$wgSshKeygen ) {
			return false;
		}

		if ( substr_compare( $keydata, 'PuTTY-User-Key-File-2:', 0, 22 ) == 0 ) {
			$keydata = explode( "\nPrivate-Lines:", $keydata, 2 );
			$keydata = $keydata[0] . "\n";
		}

		$descriptorspec = [
			0 => [ "pipe", "r" ],
			1 => [ "pipe", "w" ],
			2 => [ "file", wfGetNull(), "a" ]
		];

		$process = proc_open(
			escapeshellcmd( $wgSshKeygen ) . ' -i -f /dev/stdin', $descriptorspec, $pipes
		);
		if ( $process === false ) {
			return false;
		}

		fwrite( $pipes[0], $keydata );
		fclose( $pipes[0] );
		$data = stream_get_contents( $pipes[1] );

		fclose( $pipes[1] );
		proc_close( $process );

		if ( $data === false || !preg_match( '/(^| )ssh-(rsa|dss) /', $data ) ) {
			return false;
		}

		return $data;
	}

	/**
	 * @param array $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	public function tryImportSubmit( $formData, $entryPoint = 'internal' ) {
		$key = trim( $formData['key'] ); # Because people copy paste it with an accidental newline
		$returnto = Title::newFromText( $formData['returnto'] );
		$out = $this->getOutput();
		if ( !preg_match( '/(^| )(ssh-(rsa|dss|ed25519)|ecdsa-sha2-nistp(256|384|521)) /', $key ) ) {
			# This doesn't look like openssh format, it's probably a
			# Windows user providing it in PuTTY format.
			$key = self::opensshFormatKey( $key );
			if ( $key === false ) {
				$out->addWikiMsg( 'openstackmanager-keypairformatwrong' );
				if ( $returnto ) {
					$out->addReturnTo( $returnto );
				}
				return false;
			}
			$out->addWikiMsg( 'openstackmanager-keypairformatconverted' );
		}

		$success = $this->userLDAP->importKeypair( $key );
		if ( $success ) {
			$out->addWikiMsg( 'openstackmanager-keypairimported' );
		} else {
			$out->addWikiMsg( 'openstackmanager-keypairimportfailed' );
			if ( $returnto ) {
				$out->addReturnTo( $returnto );
			}
			return false;
		}

		if ( $returnto ) {
			$out->addReturnTo( $returnto );
		}
		return true;
	}

	/**
	 * @param array $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	public function tryDeleteSubmit( $formData, $entryPoint = 'internal' ) {
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

	protected function getGroupName() {
		return 'nova';
	}

	public function doesWrites() {
		return true;
	}

	/**
	 * @return void
	 */
	protected function notLoggedIn() {
		$this->setHeaders();
		$this->getOutput()->setPageTitle( $this->msg( 'openstackmanager-notloggedin' ) );
		$this->getOutput()->addWikiMsg( 'openstackmanager-mustbeloggedin' );
	}

	public static function createResourceList( $resources ) {
		$resourceList = '';
		foreach ( $resources as $resource ) {
			$resourceList .= Html::rawElement( 'li', [], $resource );
		}
		return Html::rawElement( 'ul', [], $resourceList );
	}

	public static function pushResourceColumn( &$row, $value, $attribs = [] ) {
		if ( array_key_exists( 'class', $attribs ) ) {
			$attribs['class'] .= ' Nova_cell';
		} else {
			$attribs['class'] = 'Nova_cell';
		}
		$row[] = Html::element( 'td', $attribs, $value );
	}

	public static function pushRawResourceColumn( &$row, $value, $attribs = [] ) {
		if ( array_key_exists( 'class', $attribs ) ) {
			$attribs['class'] .= ' Nova_cell';
		} else {
			$attribs['class'] = 'Nova_cell';
		}
		$row[] = Html::rawElement( 'td', $attribs, $value );
	}

	/**
	 * Create a table of resources based on headers and rows. Warning: $rows is not
	 * escaped in this function and must be escaped prior to this call.
	 *
	 * @param string[] $headers
	 * @param array[] $rows
	 *
	 * @return string
	 */
	public static function createResourceTable( $headers, $rows ) {
		$table = '';
		foreach ( $headers as $header ) {
			$table .= Html::element( 'th', [], wfMessage( $header )->text() );
		}
		foreach ( $rows as $row ) {
			$rowOut = '';
			foreach ( $row as $column ) {
				$rowOut .= $column;
			}
			$table .= Html::rawElement( 'tr', [], $rowOut );
		}
		return Html::rawElement( 'table', [ 'class' => 'wikitable sortable' ], $table );
	}
}
