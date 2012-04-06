<?php

/**
 * Special page for nova
 *
 * @file
 * @ingroup Extensions
 */

abstract class SpecialNova extends SpecialPage {

	/**
	 * @return void
	 */
	function notLoggedIn() {
		$this->setHeaders();
		$this->getOutput()->setPagetitle( wfMsg( 'openstackmanager-notloggedin' ) );
		$this->getOutput()->addWikiMsg( 'openstackmanager-mustbeloggedin' );
	}

	/**
	 * @return void
	 */
	function noCredentials() {
		$this->setHeaders();
		$this->getOutput()->setPagetitle( wfMsg( 'openstackmanager-nonovacred' ) );
		$this->getOutput()->addWikiMsg( 'openstackmanager-nonovacred-admincreate' );
	}

	/**
	 * @return void
	 */
	function notInProject() {
		$this->setHeaders();
		$this->getOutput()->setPagetitle( wfMsg( 'openstackmanager-noaccount' ) );
		$this->getOutput()->addWikiMsg( 'openstackmanager-noaccount2' );
	}

	/**
	 * @param  $role
	 * @return void
	 */
	function notInRole( $role ) {
		$this->setHeaders();
		if ( $role == 'sysadmin' ) {
			$this->getOutput()->setPagetitle( wfMsg( 'openstackmanager-needsysadminrole' ) );
			$this->getOutput()->addWikiMsg( 'openstackmanager-needsysadminrole2' );
		} elseif ( $role == 'netadmin' ) {
			$this->getOutput()->setPagetitle( wfMsg( 'openstackmanager-neednetadminrole' ) );
			$this->getOutput()->addWikiMsg( 'openstackmanager-neednetadminrole2' );
		} elseif ( $role == 'cloudadmin' ) {
			$this->getOutput()->setPagetitle( wfMsg( 'openstackmanager-needcloudadminrole' ) );
			$this->getOutput()->addWikiMsg( 'openstackmanager-needcloudadminrole2' );
		}
	}

	/**
	 * @param  $resourcename
	 * @param  $error
	 * @param  $alldata
	 * @return bool|string
	 */
	function validateText( $resourcename, $alldata ) {
		if ( ! preg_match( "/^[a-z][a-z0-9-]*$/", $resourcename ) ) {
			return Xml::element( 'span', array( 'class' => 'error' ), wfMsg( 'openstackmanager-badresourcename' ) );
		} else {
			return true;
		}
	}

	/**
	 * @param  $resourcename
	 * @param  $error
	 * @param  $alldata
	 * @return bool|string
	 */
	function validateDomain( $resourcename, $alldata ) {
		if ( ! preg_match( "/^[a-z\*][a-z0-9\-]*$/", $resourcename ) ) {
			return Xml::element( 'span', array( 'class' => 'error' ), wfMsg( 'openstackmanager-badresourcename' ) );
		} else {
			return true;
		}
	}

	function getProjectFilter() {
		global $wgRequest;

		if ( $wgRequest->getCookie( 'projectfilter' ) ) {
			return explode( ',', urldecode( $wgRequest->getCookie( 'projectfilter' ) ) );
		}
		return array();
	}

	function showProjectFilter( $projects, $showbydefault=false ) {
		global $wgRequest;

		if ( $this->getRequest()->wasPosted() && $this->getRequest()->getVal( 'action' ) != 'setprojectfilter' ) {
			return null;
		}
		$showmsg = $this->getRequest()->getText( 'showmsg' );
		if ( $showmsg == "setfilter" ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-setprojects' );
		}
		$currentProjects = $this->getProjectFilter();
		$project_keys = array();
		$defaults = array();
		foreach ( $projects as $project ) {
			$projectName = $project->getProjectName();
			$project_keys["$projectName"] = $projectName;
			if ( in_array( $projectName, $currentProjects ) ) {
				$defaults["$projectName"] = $projectName;
			}
		}
		$projectFilter = array();
		$projectFilter['projects'] = array(
			'type' => 'multiselect',
			'label-message' => 'openstackmanager-projects',
			'section' => 'projectfilter',
			'options' => $project_keys,
			'default' => $defaults,
			'name' => 'projects',
		);
		$projectFilter['action'] = array(
			'type' => 'hidden',
			'default' => 'setprojectfilter',
			'name' => 'action',
		);
		$projectFilterForm = new HTMLForm( $projectFilter, 'openstackmanager-novaprojectfilter' );
		$projectFilterForm->setTitle( $this->getTitle() );
		if ( $showbydefault ) {
			$classes = "mw-collapsible";
		} else {
			$classes = "mw-collapsible mw-collapsed";
		}
		$projectFilterForm->addHeaderText( '<div class="' . $classes .'" data-expandtext="Show project filter" data-collapsetext="Hide project filter">' );
		$projectFilterForm->addFooterText( '</div>' );
		$projectFilterForm->setSubmitID( 'novaproject-form-setprojectfiltersubmit' );
		$projectFilterForm->setSubmitCallback( array( $this, 'trySetProjectFilter' ) );
		$projectFilterForm->show();
	}

	function getResourcesGroupedByProject( $resources ) {
		$groupResources = Array();
		foreach ( $resources as $resource ) {
			$project = $resource->getProject();
			if ( array_key_exists( $project, $groupResources ) ) {
				$groupResources["$project"][] = $resource;
			} else {
				$groupResources["$project"] = Array( $resource );
			}
		}
		return $groupResources;
	}

	function getResourceByProject( $resources, $projectName ) {
		if ( in_array( $projectName, array_keys( $resources ) ) ) {
			return $resources["$projectName"];
		} else {
			return Array();
		}
	}
			
	function createResourceLink( $resource ) {
		$resource = htmlentities( $resource );
		$title = Title::newFromText( $resource, NS_NOVA_RESOURCE );
		return Linker::link( $title, $resource );
	}

	function createActionLink( $msg, $params, $title = Null ) {
		if ( !$title ) {
			$title = $this->getTitle();
		}
		return Linker::link( $title, wfMsgHtml( $msg ), array(), $params );
	}

	function createResourceList( $resources ) {
		$resourceList = '';
		foreach ( $resources as $resource ) {
			$resourceList .= Html::rawElement( 'li', array(), $resource );
		}
		return Html::rawElement( 'ul', array(), $resourceList );
	}

	function pushResourceColumn( &$row, $value, $attribs=array() ) {
		if ( array_key_exists( 'class', $attribs ) ) {
			$attribs['class'] = $attribs['class'] . ' Nova_cell';
		} else {
			$attribs['class'] = 'Nova_cell';
		}
		array_push( $row, Html::element( 'td', $attribs, $value ) );
	}

	function pushRawResourceColumn( &$row, $value, $attribs=array() ) {
		if ( array_key_exists( 'class', $attribs ) ) {
			$attribs['class'] = $attribs['class'] . ' Nova_cell';
		} else {
			$attribs['class'] = 'Nova_cell';
		}
		array_push( $row, Html::rawElement( 'td', $attribs, $value ) );
	}

	function createResourceTable( $headers, $rows ) {
		$table = '';
		foreach ( $headers as $header ) {
			$table .= Html::element( 'th', array(), wfMsg( $header ) );
		}
		foreach ( $rows as $row ) {
			$rowOut = '';
			foreach ( $row as $column ) {
				$rowOut .= $column;
			}
			$table .= Html::rawElement( 'tr', array(), $rowOut );
		}
		return Html::rawElement( 'table', array( 'class' => 'wikitable sortable collapsible' ), $table );
	}

	function createProjectSection( $projectName, $actionsByRole, $data ) {
		$actions = Array();
		foreach ( $actionsByRole as $role => $roleActions ) {
			foreach ( $roleActions as $action ) {
				if ( $this->userLDAP->inRole( $role, $projectName ) ) {
					array_push( $actions, $action );
				}
			}
		}
		if ( $actions ) {
			$actions = implode( ', ', $actions );
			$actions = '<a class="mw-customtoggle-' . $projectName . ' osm-remotetoggle">' . wfMsgHtml( 'openstackmanager-toggleproject' ) . '</a>, ' . $actions;
			$actionOut = Html::rawElement( 'span', array( 'id' => 'novaaction' ), "[$actions]" );
		} else {
			$actionOut = '';
		}
		$projectNameOut = $this->createResourceLink( $projectName );
		$out = Html::rawElement( 'h2', array(), "$projectNameOut $actionOut" );
		$out .= Html::rawElement( 'div', array( 'class' => 'mw-collapsible', 'id' => 'mw-customcollapsible-' . $projectName ), $data );

		return $out;
	}

	function trySetProjectFilter( $formData, $entryPoint = 'internal' ) {
		global $wgRequest;

		if ( !$formData['projects'] ) {
			$wgRequest->response()->setCookie( 'projectfilter', '', time() - 86400 );
			$this->getOutput()->redirect( $this->getTitle()->getFullUrl() );
		} else {
			$projects = implode( ',', $formData['projects'] );
			$wgRequest->response()->setCookie( 'projectfilter', $projects );
			$this->getOutput()->redirect( $this->getTitle()->getFullUrl( 'showmsg=setfilter' ) );
		}

		return true;
	}

}
