<?php

/**
 * todo comment me
 *
 * @file
 * @ingroup Extensions
 */

class SpecialNovaSudoer extends SpecialNova {
	var $userLDAP;

	function __construct() {
		parent::__construct( 'NovaSudoer' );
	}

	function execute( $par ) {
		if ( !$this->getUser()->isLoggedIn() ) {
			$this->notLoggedIn();
			return;
		}
		$this->userLDAP = new OpenStackNovaUser();
		if ( !$this->userLDAP->exists() ) {
			$this->noCredentials();
			return;
		}
		$this->checkTwoFactor();
		$this->userNova = OpenStackNovaController::newFromUser( $this->userLDAP );
		$action = $this->getRequest()->getVal( 'action' );
		$this->projectName = $this->getRequest()->getText( 'project' );
		$this->project = OpenStackNovaProject::getProjectByName( $this->projectName );
		if ( $action === "create" ) {
			if ( ! $this->userLDAP->inProject( $this->projectName ) ) {
				$this->notInProject();
				return;
			}
			$this->createSudoer();
		} elseif ( $action === "delete" ) {
			if ( ! $this->userLDAP->inProject( $this->projectName ) ) {
				$this->notInProject();
				return;
			}
			$this->deleteSudoer();
		} elseif ( $action === "modify" ) {
			if ( ! $this->userLDAP->inProject( $this->projectName ) ) {
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
		$this->getOutput()->setPagetitle( $this->msg( 'openstackmanager-modifysudoer' ) );
		if ( ! $this->userLDAP->inRole( 'projectadmin', $this->projectName ) ) {
			$this->notInRole( 'projectadmin' );
			return false;
		}

		$userArr = $this->getSudoUsers( $this->projectName );
		$user_keys = $userArr["keys"];
		$hostArr = $this->getSudoHosts( $this->projectName );
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
			'type' => 'textarea',
			'label-message' => 'openstackmanager-sudoercommands',
			'default' => '',
			'section' => 'sudoer',
			'name' => 'commands',
		);
		$sudoerInfo['options'] = array(
			'type' => 'textarea',
			'label-message' => 'openstackmanager-sudoeroptions',
			'default' => '',
			'section' => 'sudoer',
			'name' => 'options',
		);
		$sudoerInfo['project'] = array(
			'type' => 'hidden',
			'default' => $this->projectName,
			'name' => 'project',
		);
		$sudoerInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'create',
			'name' => 'action',
		);
		$sudoerInfo['requirepassword'] = array(
			'type' => 'check',
			'label-message' => 'openstackmanager-requirepassword',
			'default' => false,
			'section' => 'sudoer',
			'name' => 'requirepassword',
		);

		$sudoerForm = new HTMLForm( $sudoerInfo, 'openstackmanager-novasudoer' );
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
		$this->getOutput()->setPagetitle( $this->msg( 'openstackmanager-deletesudoer' ) );
		if ( ! $this->userLDAP->inRole( 'projectadmin', $this->projectName ) ) {
			$this->notInRole( 'projectadmin' );
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
			'default' => $this->projectName,
			'name' => 'project',
		);
		$sudoerInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'delete',
			'name' => 'action',
		);
		$sudoerForm = new HTMLForm( $sudoerInfo, 'openstackmanager-novasudoer' );
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
		$this->getOutput()->setPagetitle( $this->msg( 'openstackmanager-modifysudoer' ) );
		if ( ! $this->userLDAP->inRole( 'projectadmin', $this->projectName ) ) {
			$this->notInRole( 'projectadmin' );
			return false;
		}
		$sudoername = $this->getRequest()->getText( 'sudoername' );
		$sudoer = OpenStackNovaSudoer::getSudoerByName( $sudoername, $this->projectName );
		$userArr = $this->getSudoUsers( $this->projectName, $sudoer );
		$user_keys = $userArr["keys"];
		$user_defaults = $userArr["defaults"];
		$hostArr = $this->getSudoHosts( $this->projectName, $sudoer );
		$host_keys = $hostArr["keys"];
		$host_defaults = $hostArr["defaults"];
		$commands = implode( "\n", $sudoer->getSudoerCommands() );
		$optionArray = $sudoer->getSudoerOptions();
		$requirePassword = false;
		if ( ( $k = array_search( '!authenticate', $optionArray )) !== false ) {
			unset( $optionArray[$k] );
		} elseif ( ( $k = array_search( 'authenticate', $optionArray )) !== false) {
			unset( $optionArray[$k] );
			$requirePassword = true;
		}
		$options = implode( "\n", $optionArray );
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
			'type' => 'textarea',
			'label-message' => 'openstackmanager-sudoeroptions',
			'default' => $options,
			'section' => 'sudoer',
			'name' => 'options',
		);
		$sudoerInfo['project'] = array(
			'type' => 'hidden',
			'default' => $this->projectName,
			'name' => 'project',
		);
		$sudoerInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'modify',
			'name' => 'action',
		);
		$sudoerInfo['requirepassword'] = array(
			'type' => 'check',
			'label-message' => 'openstackmanager-requirepassword',
			'default' => $requirePassword,
			'section' => 'sudoer',
			'name' => 'requirepassword',
		);

		$sudoerForm = new HTMLForm( $sudoerInfo, 'openstackmanager-novasudoer' );
		$sudoerForm->setTitle( SpecialPage::getTitleFor( 'NovaSudoer' ) );
		$sudoerForm->setSubmitID( 'novasudoer-form-createsudoersubmit' );
		$sudoerForm->setSubmitCallback( array( $this, 'tryModifySubmit' ) );
		$sudoerForm->show();

		return true;
	}

	function getSudoUsers( $projectName, $sudoer=null ) {
		$project = OpenStackNovaProject::getProjectByName( $projectName );
		$projectmembers = $project->getMembers();

		array_unshift( $projectmembers, $this->msg( 'openstackmanager-allmembers' )->text() );
		$sudomembers = array();
		if ( $sudoer ) {
			$sudomembers = $sudoer->getSudoerUsers();
		}
		$user_keys = array();
		$user_defaults = array();
		foreach ( $projectmembers as $projectmember ) {

			$projectGroup = "%" . $project->getProjectGroup()->getProjectGroupName();

			if ( $projectmember == $this->msg( 'openstackmanager-allmembers' )->text() ) {
				$userUid = $this->msg( 'openstackmanager-allmembers' )->text();
				if ( in_array( 'ALL', $sudomembers ) || in_array ( $projectGroup, $sudomembers ) ) {
					$user_defaults[$projectmember] = $userUid;
				}
			} else {
				$user = new OpenStackNovaUser( $projectmember );
				$userUid = $user->getUid();
				if ( in_array( $userUid, $sudomembers ) ) {
					$user_defaults[$projectmember] = $userUid;
				}
			}

			$user_keys[$projectmember] = $userUid;
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
		$this->userNova->setProject( $projectName );
		$regions = $this->userNova->getRegions( 'compute' );
		foreach ( $regions as $region ) {
			$this->userNova->setRegion( $region );
			$instances = $this->userNova->getInstances();
			foreach ( $instances as $instance ) {
				$instanceName = $instance->getInstanceName();
				// instanceName will be output later, without a change to escape.
				$instanceName = htmlentities( $instanceName . ' (' . $region . ')' );
				$instanceHost = $instance->getHost();
				if ( !$instanceHost ) {
					continue;
				}
				$instanceHostname = $instanceHost->getFullyQualifiedHostName();
				$host_keys[$instanceName] = $instanceHostname;
				if ( in_array( $instanceHostname, $sudohosts ) ) {
					$host_defaults[$instanceName] = $instanceHostname;
				}
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
		$this->getOutput()->setPagetitle( $this->msg( 'openstackmanager-sudoerlist' ) );

		if ( $this->getUser()->isAllowed( 'listall' ) ) {
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

		foreach ( $projects as $project ) {
			$projectName = $project->getProjectName();
			if ( !in_array( $projectName, $projectfilter ) ) {
				continue;
			}
			$actions = Array( 'projectadmin' => Array() );
			$actions['projectadmin'][] = $this->createActionLink( 'openstackmanager-createsudoer', array( 'action' => 'create', 'project' => $projectName ) );
			$out .= $this->createProjectSection( $projectName, $actions, $this->getSudoers( $project ) );
		}

		$this->getOutput()->addHTML( $out );
	}

	function getSudoers( $project ) {
		$project->fetchProjectInfo();
		$projectName = $project->getProjectName();
		$this->userNova->setProject( $projectName );
		$regions = $this->userNova->getRegions( 'compute' );
		$instanceNames = array();
		foreach ( $regions as $region ) {
			$this->userNova->setRegion( $region );
			$instances = $this->userNova->getInstances();
			foreach ( $instances as $instance ) {
				$host = $instance->getHost();
				if ( $host ) {
					$fqdn = $host->getFullyQualifiedHostName();
					// $instanceNames will be output later with no change of escaping
					$instanceNames[$fqdn] = htmlentities( $instance->getInstanceName() . ' (' . $region . ')' );
				}
			}
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
			// Display the cryptic %project-<projectname> as 'openstackmanager-allmembers'
			$AllProjectMembers = "%" . $project->getProjectGroup()->getProjectGroupName();
			if ( in_array( $AllProjectMembers, $sudoUsers ) ) {
				array_unshift( $userNames, $this->msg( 'openstackmanager-allmembers' )->text() );
			}
			// This bit is for reverse-compatibility:
			if ( in_array( 'ALL', $sudoUsers ) ) {
				array_unshift( $userNames, $this->msg( 'openstackmanager-allmembers' )->text() );
			}
			$sudoHosts = $sudoer->getSudoerHosts();
			$sudoHostNames = array();
			foreach ( $sudoHosts as $sudoHost ) {
				if ( array_key_exists( $sudoHost, $instanceNames ) ) {
					array_push( $sudoHostNames, $instanceNames[$sudoHost] );
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
	 *
	 *  @ param $users: a list of usernames and/or 'openstackmanager-allmembers'
	 *  @ return modified list of usernames
	 *
	 *  This function replaces the problematic 'ALL' with a reference
	 *   to the project user group.
	 *
	 */
	function removeALLFromUserKeys( $users ) {
		foreach ( $users as $user ) {
			if ( ( $user == 'ALL' ) || ( $user == $this->msg( 'openstackmanager-allmembers' )->text() )) {
				$newusers[] = "%" . $this->project->getProjectGroup()->getProjectGroupName();
			} else {
				$newusers[] = $user;
			}
		}
		return $newusers;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryCreateSubmit( $formData, $entryPoint = 'internal' ) {
		if ( $formData['commands'] ) {
			$commands = explode( "\n", $formData['commands'] );
		} else {
			$commands = array();
		}
		if ( $formData['options'] ) {
			$options = explode( "\n", $formData['options'] );
		} else {
			$options = array();
		}
		if ( $formData['requirepassword'] ) {
			$options[] = 'authenticate';
		} else {
			$options[] = '!authenticate';
		}
		$success = OpenStackNovaSudoer::createSudoer( $formData['sudoername'], $formData['project'], $this->removeALLFromUserKeys($formData['users']), $formData['hosts'], $commands, $options );
		if ( ! $success ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-createsudoerfailed' );
			return false;
		}
		$this->getOutput()->addWikiMsg( 'openstackmanager-createdsudoer' );

		$out = '<br />';
		$out .= Linker::link(
			$this->getTitle(),
			$this->msg( 'openstackmanager-backsudoerlist' )->escaped()
		);
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
		$out .= Linker::link(
			$this->getTitle(),
			$this->msg( 'openstackmanager-backsudoerlist' )->escaped()
		);
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
				$options = explode( "\n", $formData['options'] );
			} else {
				$options = array();
			}
			if ( $formData['requirepassword'] ) {
				$options[] = 'authenticate';
			} else {
				$options[] = '!authenticate';
			}
			$success = $sudoer->modifySudoer( $this->removeALLFromUserKeys($formData['users']), $formData['hosts'], $commands, $options );
			if ( ! $success ) {
				$this->getOutput()->addWikiMsg( 'openstackmanager-modifysudoerfailed' );
				return true;
			}
			$this->getOutput()->addWikiMsg( 'openstackmanager-modifiedsudoer' );
		} else {
			$this->getOutput()->addWikiMsg( 'openstackmanager-nonexistantsudoer' );
		}

		$out = '<br />';
		$out .= Linker::link(
			$this->getTitle(),
			$this->msg( 'openstackmanager-backsudoerlist' )->escaped()
		);
		$this->getOutput()->addHTML( $out );

		return true;
	}
}
