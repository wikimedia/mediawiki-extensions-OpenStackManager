<?php

/**
 * todo comment me
 *
 * @file
 * @ingroup Extensions
 */

class SpecialNovaSudoer extends SpecialNova {

	var $userLDAP;
	var $adminNova;

	function __construct() {
		parent::__construct( 'NovaSudoer', 'manageproject' );

		$this->userLDAP = new OpenStackNovaUser();
	}

	function execute( $par ) {
		global $wgOpenStackManagerNovaAdminKeys;

		if ( !$this->getUser()->isLoggedIn() ) {
			$this->notLoggedIn();
			return;
		}
		if ( !$this->userLDAP->exists() ) {
			$this->noCredentials();
			return;
		}

		$adminCredentials = $wgOpenStackManagerNovaAdminKeys;
		$this->adminNova = new OpenStackNovaController( $adminCredentials );
		$action = $this->getRequest()->getVal( 'action' );
		$project = $this->getRequest()->getText( 'project' );
		if ( $action == "create" ) {
			if ( ! $this->userLDAP->inProject( $project ) ) {
				$this->notInProject();
				return;
			}
			$this->createSudoer();
		} elseif ( $action == "delete" ) {
			if ( ! $this->userLDAP->inProject( $project ) ) {
				$this->notInProject();
				return;
			}
			$this->deleteSudoer();
		} elseif ( $action == "modify" ) {
			if ( ! $this->userLDAP->inProject( $project ) ) {
				$this->notInProject();
				return;
			}
			$this->modifySudoer();
		} else {
			$this->listSudoers();
		}
	}

	/**
	 * @return bool
	 */
	function createSudoer() {
		$this->setHeaders();
		$this->getOutput()->setPagetitle( wfMsg( 'openstackmanager-modifysudoer' ) );
		$projectName = $this->getRequest()->getText( 'project' );
		if ( ! $this->userLDAP->inRole( 'sysadmin', $projectName ) ) {
			$this->notInRole( 'sysadmin' );
			return false;
		}

		$userArr = $this->getSudoUsers( $projectName );
		$user_keys = $userArr["keys"];
		$hostArr = $this->getSudoHosts( $projectName );
		$host_keys = $hostArr["keys"];
		$sudoerInfo = array();
		$sudoerInfo['sudoername'] = array(
			'type' => 'text',
			'label-message' => 'openstackmanager-sudoername',
			'default' => '',
			'section' => 'sudoer',
			'name' => 'sudoername',
		);
		$sudoerInfo['users'] = array(
			'type' => 'multiselect',
			'label-message' => 'openstackmanager-sudoerusers',
			'options' => $user_keys,
			'section' => 'sudoer',
			'name' => 'users',
		);
		$sudoerInfo['hosts'] = array(
			'type' => 'multiselect',
			'label-message' => 'openstackmanager-sudoerhosts',
			'options' => $host_keys,
			'section' => 'sudoer',
			'name' => 'hosts',
		);
		$sudoerInfo['commands'] = array(
			'type' => 'text',
			'label-message' => 'openstackmanager-sudoercommands',
			'default' => '',
			'section' => 'sudoer',
			'name' => 'commands',
		);
		$sudoerInfo['options'] = array(
			'type' => 'text',
			'label-message' => 'openstackmanager-sudoeroptions',
			'default' => '',
			'section' => 'sudoer',
			'name' => 'options',
		);
		$sudoerInfo['project'] = array(
			'type' => 'hidden',
			'default' => $projectName,
			'name' => 'project',
		);
		$sudoerInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'create',
			'name' => 'action',
		);

		$sudoerForm = new SpecialNovaSudoerForm( $sudoerInfo, 'openstackmanager-novasudoer' );
		$sudoerForm->setTitle( SpecialPage::getTitleFor( 'NovaSudoer' ) );
		$sudoerForm->setSubmitID( 'novasudoer-form-createsudoersubmit' );
		$sudoerForm->setSubmitCallback( array( $this, 'tryCreateSubmit' ) );
		$sudoerForm->show();

		return true;
	}

	/**
	 * @return bool
	 */
	function deleteSudoer() {
		$this->setHeaders();
		$this->getOutput()->setPagetitle( wfMsg( 'openstackmanager-deletesudoer' ) );
		$project = $this->getRequest()->getText( 'project' );
		if ( ! $this->userLDAP->inRole( 'sysadmin', $project ) ) {
			$this->notInRole( 'sysadmin' );
			return false;
		}
		$sudoername = $this->getRequest()->getText( 'sudoername' );
		if ( ! $this->getRequest()->wasPosted() ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-deletesudoer-confirm', $sudoername );
		}
		$sudoerInfo = array();
		$sudoerInfo['sudoername'] = array(
			'type' => 'hidden',
			'default' => $sudoername,
			'name' => 'sudoername',
		);
		$sudoerInfo['project'] = array(
			'type' => 'hidden',
			'default' => $project,
			'name' => 'project',
		);
		$sudoerInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'delete',
			'name' => 'action',
		);
		$sudoerForm = new SpecialNovaSudoerForm( $sudoerInfo, 'openstackmanager-novasudoer' );
		$sudoerForm->setTitle( SpecialPage::getTitleFor( 'NovaSudoer' ) );
		$sudoerForm->setSubmitID( 'novasudoer-form-deletesudoersubmit' );
		$sudoerForm->setSubmitCallback( array( $this, 'tryDeleteSubmit' ) );
		$sudoerForm->show();

		return true;
	}

	/**
	 * @return bool
	 */
	function modifySudoer() {
		$this->setHeaders();
		$this->getOutput()->setPagetitle( wfMsg( 'openstackmanager-modifysudoer' ) );
		$projectName = $this->getRequest()->getText( 'project' );
		if ( ! $this->userLDAP->inRole( 'sysadmin', $projectName ) ) {
			$this->notInRole( 'sysadmin' );
			return false;
		}
		$sudoername = $this->getRequest()->getText( 'sudoername' );
		$sudoer = OpenStackNovaSudoer::getSudoerByName( $sudoername, $projectName );
		$userArr = $this->getSudoUsers( $projectName, $sudoer );
		$user_keys = $userArr["keys"];
		$user_defaults = $userArr["defaults"];
		$hostArr = $this->getSudoHosts( $projectName, $sudoer );
		$host_keys = $hostArr["keys"];
		$host_defaults = $hostArr["defaults"];
		$commands = implode( ',', $sudoer->getSudoerCommands() );
		$options = implode( ',', $sudoer->getSudoerOptions() );
		$sudoerInfo = array();
		$sudoerInfo['sudoernameinfo'] = array(
			'type' => 'info',
			'label-message' => 'openstackmanager-sudoername',
			'default' => $sudoername,
			'section' => 'sudoer',
			'name' => 'sudoernameinfo',
		);
		$sudoerInfo['sudoername'] = array(
			'type' => 'hidden',
			'default' => $sudoername,
			'name' => 'sudoername',
		);
		$sudoerInfo['users'] = array(
			'type' => 'multiselect',
			'label-message' => 'openstackmanager-sudoerusers',
			'options' => $user_keys,
			'default' => $user_defaults,
			'section' => 'sudoer',
			'name' => 'users',
		);
		$sudoerInfo['hosts'] = array(
			'type' => 'multiselect',
			'label-message' => 'openstackmanager-sudoerhosts',
			'options' => $host_keys,
			'default' => $host_defaults,
			'section' => 'sudoer',
			'name' => 'hosts',
		);
		$sudoerInfo['commands'] = array(
			'type' => 'textarea',
			'label-message' => 'openstackmanager-sudoercommands',
			'default' => $commands,
			'section' => 'sudoer',
			'name' => 'commands',
		);
		$sudoerInfo['options'] = array(
			'type' => 'text',
			'label-message' => 'openstackmanager-sudoeroptions',
			'default' => $options,
			'section' => 'sudoer',
			'help-message' => 'openstackmanager-commadelimiter',
			'name' => 'options',
		);
		$sudoerInfo['project'] = array(
			'type' => 'hidden',
			'default' => $projectName,
			'name' => 'project',
		);
		$sudoerInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'modify',
			'name' => 'action',
		);

		$sudoerForm = new SpecialNovaSudoerForm( $sudoerInfo, 'openstackmanager-novasudoer' );
		$sudoerForm->setTitle( SpecialPage::getTitleFor( 'NovaSudoer' ) );
		$sudoerForm->setSubmitID( 'novasudoer-form-createsudoersubmit' );
		$sudoerForm->setSubmitCallback( array( $this, 'tryModifySubmit' ) );
		$sudoerForm->show();

		return true;
	}

	function getSudoUsers( $projectName, $sudoer=null ) {
		$project = OpenStackNovaProject::getProjectByName( $projectName );
		$projectmembers = $project->getMembers();
		array_unshift( $projectmembers, 'ALL' );
		$sudomembers = array();
		if ( $sudoer ) {
			$sudomembers = $sudoer->getSudoerUsers();
		}
		$user_keys = array();
		$user_defaults = array();
		foreach ( $projectmembers as $projectmember ) {
			if ( $projectmember != 'ALL' ) {
				$user = new OpenStackNovaUser( $projectmember );
				$userUid = $user->getUid();
			} else {
				$userUid = 'ALL';
			}
			$user_keys["$projectmember"] = $userUid;
			if ( in_array( $userUid, $sudomembers ) ) {
				$user_defaults["$projectmember"] = $userUid;
			}
		}
		return array( 'keys' => $user_keys, 'defaults' => $user_defaults );
	}

	function getSudoHosts( $projectName, $sudoer=null ) {
		$sudohosts = array();
		if ( $sudoer ) {
			$sudohosts = $sudoer->getSudoerHosts();
		}
		$host_keys = array( 'ALL' => 'ALL' );
		$host_defaults = array();
		$instances = $this->adminNova->getInstances();
		$instances = $this->getResourcesGroupedByProject( $instances );
		$projectinstances = $this->getResourceByProject( $instances, $projectName );
		foreach ( $projectinstances as $projectinstance ) {
			$instanceName = $projectinstance->getInstanceName();
			$instanceHost = $projectinstance->getHost();
			$instanceHostname = $instanceHost->getFullyQualifiedHostName();
			$host_keys["$instanceName"] = $instanceHostname;
			if ( in_array( $instanceHostname, $sudohosts ) ) {
				$host_defaults["$instanceName"] = $instanceHostname;
			}
		}
		if ( in_array( "ALL", $sudohosts ) ) {
			$host_defaults["ALL"] = "ALL";
		}
		return array( 'keys' => $host_keys, 'defaults' => $host_defaults );
	}

	/**
	 * @return void
	 */
	function listSudoers() {
		$this->setHeaders();
		$this->getOutput()->addModuleStyles( 'ext.openstack' );
		$this->getOutput()->setPagetitle( wfMsg( 'openstackmanager-sudoerlist' ) );

		if ( $this->userLDAP->inGlobalRole( 'cloudadmin' ) ) {
			$projects = OpenStackNovaProject::getAllProjects();
		} else {
			$projects = OpenStackNovaProject::getProjectsByName( $this->userLDAP->getProjects() );
		}
		$projectfilter = $this->getProjectFilter();
		if ( !$projectfilter ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-setprojectfilter' );
			$this->showProjectFilter( $projects, true );
			return null;
		}
		$this->showProjectFilter( $projects );

		$out = '';

		$instances = $this->adminNova->getInstances();
		$instances = $this->getResourcesGroupedByProject( $instances );
		foreach ( $projects as $project ) {
			$projectName = $project->getProjectName();
			if ( !in_array( $projectName, $projectfilter ) ) {
				continue;
			}
			$actions = Array( 'sysadmin' => Array() );
			$actions['sysadmin'][] = $this->createActionLink( 'openstackmanager-createsudoer', array( 'action' => 'create', 'project' => $projectName ) );
			$out .= $this->createProjectSection( $projectName, $actions, $this->getSudoers( $project, $instances ) );
		}

		$this->getOutput()->addHTML( $out );
	}

	function getSudoers( $project, $instances ) {
		$projectName = $project->getProjectName();
		$projectinstances = $this->getResourceByProject( $instances, $projectName );
		$instanceNames = array();
		foreach ( $projectinstances as $instance ) {
			$fqdn = $instance->getHost()->getFullyQualifiedHostName();
			$instanceNames["$fqdn"] = $instance->getInstanceName();
		}
		$headers = Array( 'openstackmanager-sudoername', 'openstackmanager-sudoerusers', 'openstackmanager-sudoerhosts',
				'openstackmanager-sudoercommands', 'openstackmanager-sudoeroptions', 'openstackmanager-actions' );
		$sudoers = OpenStackNovaSudoer::getAllSudoersByProject( $projectName );
		$sudoerRows = Array();
		foreach ( $sudoers as $sudoer ) {
			$sudoerRow = Array();
			$sudoerName = $sudoer->getSudoerName();
			$this->pushResourceColumn( $sudoerRow, $sudoerName );
			$userNames = array();
			$projectmembers = $project->getMembers();
			$sudoUsers = $sudoer->getSudoerUsers();
			foreach ( $projectmembers as $member ) {
				$user = new OpenStackNovaUser( $member );
				if ( in_array( $user->getUid(), $sudoUsers ) ) {
					array_push( $userNames, $member );
				}
			}
			if ( in_array( 'ALL', $sudoUsers ) ) {
				array_unshift( $userNames, 'ALL' );
			}
			$sudoHosts = $sudoer->getSudoerHosts();
			$sudoHostNames = array();
			foreach ( $sudoHosts as $sudoHost ) {
				if ( array_key_exists( $sudoHost, $instanceNames ) ) {
					array_push( $sudoHostNames, $instanceNames["$sudoHost"] );
				}
			}
			if ( in_array( 'ALL', $sudoHosts ) ) {
				array_unshift( $sudoHostNames, 'ALL' );
			}
			$this->pushRawResourceColumn( $sudoerRow, $this->createResourceList( $userNames ) );
			$this->pushRawResourceColumn( $sudoerRow, $this->createResourceList( $sudoHostNames ) );
			$this->pushRawResourceColumn( $sudoerRow, $this->createResourceList( $sudoer->getSudoerCommands() ) );
			$this->pushRawResourceColumn( $sudoerRow, $this->createResourceList( $sudoer->getSudoerOptions() ) );
			$actions = Array();
			array_push( $actions, $this->createActionLink( 'openstackmanager-modify', array( 'action' => 'modify', 'sudoername' => $sudoerName, 'project' => $projectName ) ) );
			array_push( $actions, $this->createActionLink( 'openstackmanager-delete', array( 'action' => 'delete', 'sudoername' => $sudoerName, 'project' => $projectName ) ) );
			$this->pushRawResourceColumn( $sudoerRow, $this->createResourceList( $actions ) );
			array_push( $sudoerRows, $sudoerRow );
		}
		if ( $sudoerRows ) {
			$out = $this->createResourceTable( $headers, $sudoerRows );
		} else {
			$out = '';
		}

		return $out;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryCreateSubmit( $formData, $entryPoint = 'internal' ) {
		if ( $formData['commands'] ) {
			$commands = explode( ',', $formData['commands'] );
		} else {
			$commands = array();
		}
		if ( $formData['options'] ) {
			$options = explode( ',', $formData['options'] );
		} else {
			$options = array();
		}
		$success = OpenStackNovaSudoer::createSudoer( $formData['sudoername'], $formData['project'], $formData['users'], $formData['hosts'], $commands, $options );
		if ( ! $success ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-createsudoerfailed' );
			return false;
		}
		$this->getOutput()->addWikiMsg( 'openstackmanager-createdsudoer' );

		$out = '<br />';
		$out .= Linker::link( $this->getTitle(), wfMsgHtml( 'openstackmanager-backsudoerlist' ) );
		$this->getOutput()->addHTML( $out );

		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryDeleteSubmit( $formData, $entryPoint = 'internal' ) {

		$success = OpenStackNovaSudoer::deleteSudoer( $formData['sudoername'], $formData['project'] );
		if ( $success ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-deletedsudoer' );
		} else {
			$this->getOutput()->addWikiMsg( 'openstackmanager-failedeletedsudoer' );
		}

		$out = '<br />';
		$out .= Linker::link( $this->getTitle(), wfMsgHtml( 'openstackmanager-backsudoerlist' ) );
		$this->getOutput()->addHTML( $out );

		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryModifySubmit( $formData, $entryPoint = 'internal' ) {
		$sudoer = OpenStackNovaSudoer::getSudoerByName( $formData['sudoername'], $formData['project'] );
		if ( $sudoer ) {
			if ( $formData['commands'] ) {
				$commands = explode( "\n", $formData['commands'] );
			} else {
				$commands = array();
			}
			if ( $formData['options'] ) {
				$options = explode( ',', $formData['options'] );
			} else {
				$options = array();
			}
			$success = $sudoer->modifySudoer( $formData['users'], $formData['hosts'], $commands, $options );
			if ( ! $success ) {
				$this->getOutput()->addWikiMsg( 'openstackmanager-modifysudoerfailed' );
				return true;
			}
			$this->getOutput()->addWikiMsg( 'openstackmanager-modifiedsudoer' );
		} else {
			$this->getOutput()->addWikiMsg( 'openstackmanager-nonexistantsudoer' );
		}

		$out = '<br />';
		$out .= Linker::link( $this->getTitle(), wfMsgHtml( 'openstackmanager-backsudoerlist' ) );
		$this->getOutput()->addHTML( $out );

		return true;
	}

}

class SpecialNovaSudoerForm extends HTMLForm {
}
