<?php

/**
 * Special page for nova
 *
 * @file
 * @ingroup Extensions
 */

abstract class SpecialNova extends SpecialPage {

	/** @var OpenStackNovaUser */
	protected $userLDAP;

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

	/**
	 * @return void
	 */
	protected function noCredentials() {
		$this->setHeaders();
		$this->getOutput()->setPageTitle( $this->msg( 'openstackmanager-nonovacred' ) );
		$this->getOutput()->addWikiMsg( 'openstackmanager-nonovacred-admincreate' );
	}

	/**
	 * @return void
	 */
	protected function notInServiceGroup() {
		$this->setHeaders();
		$this->getOutput()->setPageTitle( $this->msg( 'openstackmanager-notinservicegroup' ) );
		$this->getOutput()->addWikiMsg( 'openstackmanager-needservicegroup' );
	}

	protected function checkTwoFactor() {
		if ( $this->getUser()->isAllowed( 'userrights' ) ) {
			$isEnabled = false;
			Hooks::run( 'TwoFactorIsEnabled', [ &$isEnabled ] );
			// @phan-suppress-next-line PhanRedundantCondition
			if ( !$isEnabled ) {
				throw new ErrorPageError(
					'openstackmanager-twofactorrequired', 'openstackmanager-twofactorrequired2'
				);
			}
		}
	}

	/**
	 * @param string $resourcename
	 * @param string $alldata
	 * @internal param $error
	 * @return bool|string
	 */
	public function validateText( $resourcename, $alldata ) {
		if ( !preg_match( "/^[a-z][a-z0-9-]*$/", $resourcename ) ) {
			return Xml::element(
				'span',
				[ 'class' => 'error' ],
				$this->msg( 'openstackmanager-badresourcename' )->text()
			);
		} else {
			return true;
		}
	}

	/**
	 * @param string $resourcename
	 * @param string $alldata
	 * @return bool|string
	 */
	public function validateDomain( $resourcename, $alldata ) {
		if ( !preg_match( "/^[a-z\*][a-z0-9\-]*$/", $resourcename ) ) {
			return Xml::element(
				'span',
				[ 'class' => 'error' ],
				$this->msg( 'openstackmanager-badresourcename' )->text()
			);
		} else {
			return true;
		}
	}

	public static function createResourceLink( $resource ) {
		$res = htmlentities( $resource );
		$title = Title::newFromText( $res, NS_NOVA_RESOURCE );
		return Linker::link( $title, $res );
	}

	protected function createActionLink( $msg, $params, $title = null, $attribs = [] ) {
		if ( !$title ) {
			$title = $this->getPageTitle();
		}
		return Linker::link( $title, $this->msg( $msg )->escaped(), $attribs, $params );
	}

	public static function createNovaKeyActionLink( $msg, $params ) {
		return Linker::link(
			SpecialPage::getTitleFor( 'NovaKey' ),
			wfMessage( $msg )->escaped(),
			[],
			$params
		);
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
			$attribs['class'] = $attribs['class'] . ' Nova_cell';
		} else {
			$attribs['class'] = 'Nova_cell';
		}
		$row[] = Html::element( 'td', $attribs, $value );
	}

	public static function pushRawResourceColumn( &$row, $value, $attribs = [] ) {
		if ( array_key_exists( 'class', $attribs ) ) {
			$attribs['class'] = $attribs['class'] . ' Nova_cell';
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
