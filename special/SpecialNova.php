<?php

/**
 * Special page for nova
 *
 * @file
 * @ingroup Extensions
 */

abstract class SpecialNova extends SpecialPage {
	public function doesWrites() {
		return true;
	}

	/**
	 * @return void
	 */
	function notLoggedIn() {
		$this->setHeaders();
		$this->getOutput()->setPageTitle( $this->msg( 'openstackmanager-notloggedin' ) );
		$this->getOutput()->addWikiMsg( 'openstackmanager-mustbeloggedin' );
	}

	/**
	 * @return void
	 */
	function noCredentials() {
		$this->setHeaders();
		$this->getOutput()->setPageTitle( $this->msg( 'openstackmanager-nonovacred' ) );
		$this->getOutput()->addWikiMsg( 'openstackmanager-nonovacred-admincreate' );
	}

	/**
	 * @return void
	 */
	function notInProject( $project ) {
		$this->setHeaders();
		$this->getOutput()->setPageTitle( $this->msg( 'openstackmanager-noaccount', $project ) );
		$this->getOutput()->addWikiMsg( 'openstackmanager-noaccount2', $project );
	}

	/**
	 * @return void
	 */
	function notInServiceGroup() {
		$this->setHeaders();
		$this->getOutput()->setPageTitle( $this->msg( 'openstackmanager-notinservicegroup' ) );
		$this->getOutput()->addWikiMsg( 'openstackmanager-needservicegroup' );
	}

	/**
	 * @param  $role
	 * @return void
	 */
	function notInRole( $role, $project ) {
		$this->setHeaders();
		$this->getOutput()->setPageTitle( $this->msg( 'openstackmanager-needrole', $role, $project ) );
		$this->getOutput()->addWikiMsg( 'openstackmanager-needrole2', $role, $project );
	}

	function checkTwoFactor() {
		if ( $this->getUser()->isAllowed( 'userrights' ) ) {
			$isEnabled = false;
			Hooks::run( 'TwoFactorIsEnabled', [ &$isEnabled ] );
			if ( !$isEnabled ) {
				throw new ErrorPageError( 'openstackmanager-twofactorrequired', 'openstackmanager-twofactorrequired2' );
			}
		}
	}

	/**
	 * @param $resourcename
	 * @param $alldata
	 * @internal param $error
	 * @return bool|string
	 */
	function validateText( $resourcename, $alldata ) {
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
	 * @param $resourcename
	 * @param $alldata
	 * @return bool|string
	 */
	function validateDomain( $resourcename, $alldata ) {
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

	function getProjectFilter() {
		global $wgRequest;

		if ( $wgRequest->getCookie( 'projectfilter' ) ) {
			return explode( ',', urldecode( $wgRequest->getCookie( 'projectfilter' ) ) );
		}
		return [];
	}

	function showProjectFilter( $projects ) {
		if ( $this->getRequest()->wasPosted() && $this->getRequest()->getVal( 'action' ) !== 'setprojectfilter' ) {
			return null;
		}
		$showmsg = $this->getRequest()->getText( 'showmsg' );
		if ( $showmsg === "setfilter" ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-setprojects' );
		}
		$currentProjects = $this->getProjectFilter();
		$project_keys = [];
		$defaults = [];
		foreach ( $projects as $project ) {
			$projectName = $project->getProjectName();
			$project_keys[$projectName] = $projectName;
			if ( in_array( $projectName, $currentProjects ) ) {
				$defaults[$projectName] = $projectName;
			}
		}
		$projectFilter = [];
		$projectFilter['projects'] = [
			'type' => 'multiselect',
			'label-message' => 'openstackmanager-projects',
			'section' => 'projectfilter',
			'options' => $project_keys,
			'default' => $defaults,
			'dropdown' => true,
			'name' => 'projects',
		];
		$projectFilter['action'] = [
			'type' => 'hidden',
			'default' => 'setprojectfilter',
			'name' => 'action',
		];
		$projectFilterForm = new HTMLForm(
			$projectFilter,
			$this->getContext(),
			'openstackmanager-novaprojectfilter'
		);
		$projectFilterForm->setSubmitID( 'novaproject-form-setprojectfiltersubmit' );
		$projectFilterForm->setSubmitCallback( [ $this, 'trySetProjectFilter' ] );
		$projectFilterForm->setSubmitTextMsg( 'openstackmanager-projectfiltersubmit' );
		$projectFilterForm->show();
	}

	public static function createResourceLink( $resource ) {
		$resource = htmlentities( $resource );
		$title = Title::newFromText( $resource, NS_NOVA_RESOURCE );
		return Linker::link( $title, $resource );
	}

	function createActionLink( $msg, $params, $title = null, $attribs = [] ) {
		if ( !$title ) {
			$title = $this->getPageTitle();
		}
		return Linker::link( $title, $this->msg( $msg )->escaped(), $attribs, $params );
	}

	public static function createNovaKeyActionLink( $msg, $params ) {
		return Linker::link( SpecialPage::getTitleFor( 'NovaKey' ), wfMessage( $msg )->escaped(), [], $params );
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
	 * @param $headers
	 * @param $rows
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

	/**
	 * Create a project section to be displayed in a list page. Warning: neither $actionsByRole nor
	 * $data escaped in this function and must be escaped prior to this call.
	 *
	 * @param $projectName
	 * @param $actionsByRole
	 * @param $data
	 *
	 * @return string
	 */
	function createProjectSection( $projectName, $actionsByRole, $data ) {
		$actions = [];
		foreach ( $actionsByRole as $role => $roleActions ) {
			foreach ( $roleActions as $action ) {
				if ( !$role || $this->userLDAP->inRole( $role, $projectName ) ) {
					$actions[] = $action;
				}
			}
		}
		if ( $actions ) {
			$actions = implode( ', ', $actions );
			$actions = "[$actions]";
		} else {
			$actions = "";
		}
		$actionOut = Html::rawElement( 'span', [ 'id' => 'novaaction' ], $actions );
		$projectNameOut = $this->createResourceLink( $projectName );
		# Mark this element with an id so that we can #link to it from elsewhere.
		$elementWithId = "h2 id=\"$projectName\"";
		$out = Html::rawElement( $elementWithId, [], "$projectNameOut $actionOut" );
		$out .= Html::rawElement( 'div', [], $data );

		return $out;
	}

	/**
	 * Create a region section to be displayed in a list page. Warning: neither $actionsByRole nor
	 * $data are escaped in this function and must be escaped prior to this call.
	 *
	 * @param $region
	 * @param $projectName
	 * @param $actionsByRole
	 * @param $data
	 *
	 * @return string
	 */
	function createRegionSection( $region, $projectName, $actionsByRole, $data ) {
		$actions = [];
		foreach ( $actionsByRole as $role => $roleActions ) {
			foreach ( $roleActions as $action ) {
				if ( $this->userLDAP->inRole( $role, $projectName ) ) {
					$actions[] = $action;
				}
			}
		}
		$escapedregion = htmlentities( $region );
		if ( $actions ) {
			$actions = implode( ', ', $actions );
			$actionOut = Html::rawElement( 'span', [ 'id' => 'novaaction' ], "[$actions]" );
		} else {
			$actionOut = '';
		}
		$out = Html::rawElement( 'h3', [], "$escapedregion $actionOut" );
		$out .= Html::rawElement( 'div', [], $data );

		return $out;
	}

	function trySetProjectFilter( $formData, $entryPoint = 'internal' ) {
		global $wgRequest;

		if ( !$formData['projects'] ) {
			$wgRequest->response()->setCookie( 'projectfilter', '', time() - 86400 );
		} else {
			$projects = implode( ',', $formData['projects'] );
			$wgRequest->response()->setCookie( 'projectfilter', $projects );
		}
		$this->getOutput()->redirect( $this->getPageTitle()->getFullUrl() );

		return true;
	}
}
