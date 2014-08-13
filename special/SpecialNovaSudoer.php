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
				$this->notInProject( $this->project );
				return;
			}
			$this->createSudoer();
		} elseif ( $action === "delete" ) {
			if ( ! $this->userLDAP->inProject( $this->projectName ) ) {
				$this->notInProject( $this->project );
				return;
			}
			$this->deleteSudoer();
		} elseif ( $action === "modify" ) {
			if ( ! $this->userLDAP->inProject( $this->projectName ) ) {
				$this->notInProject( $this->project );
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
			$this->notInRole( 'projectadmin', $this->projectName );
			return false;
		}

		$userArr = $this->getSudoUsers( $this->projectName );
		$user_keys = $userArr["keys"];
		$hostArr = $this->getSudoHosts( $this->projectName );
		$host_keys = $hostArr["keys"];
		$runasArr = $this->getSudoRunAsUsers( $this->projectName );
		$runas_keys = $runasArr["keys"];
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
		$sudoerInfo['runas'] = array(
			'type' => 'multiselect',
			'label-message' => 'openstackmanager-sudoerrunas',
			'options' => $runas_keys,
			'section' => 'sudoer',
			'name' => 'runas',
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

		$sudoerForm = new HTMLForm(
			$sudoerInfo,
			$this->getContext(),
			'openstackmanager-novasudoer'
		);
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
			$this->notInRole( 'projectadmin', $this->projectName );
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
		$sudoerForm = new HTMLForm(
			$sudoerInfo,
			$this->getContext(),
			'openstackmanager-novasudoer'
		);
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
			$this->notInRole( 'projectadmin', $this->projectName );
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
		$runasArr = $this->getSudoRunAsUsers( $this->projectName, $sudoer );
		$runas_keys = $runasArr["keys"];
		$runas_defaults = $runasArr["defaults"];
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
		$sudoerInfo['runas'] = array(
			'type' => 'multiselect',
			'label-message' => 'openstackmanager-sudoerrunas',
			'options' => $runas_keys,
			'default' => $runas_defaults,
			'section' => 'sudoer',
			'name' => 'runas',
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

		$sudoerForm = new HTMLForm(
			$sudoerInfo,
			$this->getContext(),
			'openstackmanager-novasudoer'
		);
		$sudoerForm->setSubmitID( 'novasudoer-form-createsudoersubmit' );
		$sudoerForm->setSubmitCallback( array( $this, 'tryModifySubmit' ) );
		$sudoerForm->show();

		return true;
	}

	function getSudoUsers( $projectName, $sudoer=null ) {
		$project = OpenStackNovaProject::getProjectByName( $projectName );
		$projectuids = $project->getMemberUids();
		$projectserviceusers = $project->getServiceUsers();

		$sudomembers = array();
		if ( $sudoer ) {
			$sudomembers = $sudoer->getSudoerUsers();
		}
		$user_keys = array();
		$user_defaults = array();

		# Add the 'all project members' option to the top
		$projectGroup = "%" . $project->getProjectGroup()->getProjectGroupName();
		$all_members = $this->msg( 'openstackmanager-allmembers' )->text();
		$user_keys[$all_members] = $all_members;
		if ( in_array( 'ALL', $sudomembers ) || in_array ( $projectGroup, $sudomembers ) ) {
			$user_defaults[$all_members] = $all_members;
		}

		foreach ( $projectuids as $userUid ) {
			$projectmember = $project->memberForUid( $userUid );

			$user_keys[$projectmember] = $userUid;
			if ( in_array( $userUid, $sudomembers ) ) {
				$user_defaults[$projectmember] = $userUid;
			}
		}

		foreach ( $projectserviceusers as $serviceuser ) {
			$user_keys[$serviceuser] = $serviceuser;
			if ( in_array( $serviceuser, $sudomembers ) ) {
				$user_defaults[$serviceuser] = $serviceuser;
			}
		}

		return array( 'keys' => $user_keys, 'defaults' => $user_defaults );
	}

	function getSudoRunAsUsers( $projectName, $sudoer=null ) {
		$project = OpenStackNovaProject::getProjectByName( $projectName );
		$projectuids = $project->getMemberUids();
		$projectserviceusers = $project->getServiceUsers();

		$runasmembers = array();
		if ( $sudoer ) {
			$runasmembers = $sudoer->getSudoerRunAsUsers();
		}

		$runas_keys = array();
		$runas_defaults = array();

		# Add the 'all project members' option to the top
		$projectGroup = "%" . $project->getProjectGroup()->getProjectGroupName();
		$all_members = $this->msg( 'openstackmanager-allmembers' )->text();
		$runas_keys[$all_members] = $all_members;
		if ( in_array( 'ALL', $runasmembers ) || in_array ( $projectGroup, $runasmembers ) ) {
			$runas_defaults[$all_members] = $all_members;
		}

		foreach ( $projectuids as $userUid ) {
			$projectmember = $project->memberForUid( $userUid );

			$runas_keys[$projectmember] = $userUid;
			if ( in_array( $userUid, $runasmembers ) ) {
				$runas_defaults[$projectmember] = $userUid;
			}
		}

		foreach ( $projectserviceusers as $serviceuser ) {
			$runas_keys[$serviceuser] = $serviceuser;
			if ( in_array( $serviceuser, $runasmembers ) ) {
				$runas_defaults[$serviceuser] = $serviceuser;
			}
		}

		return array( 'keys' => $runas_keys, 'defaults' => $runas_defaults );
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
		$this->showProjectFilter( $projects );
		$projectfilter = $this->getProjectFilter();
		if ( !$projectfilter ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-setprojectfilter' );
			return null;
		}

		$out = '';

		foreach ( $projects as $project ) {
			$projectName = $project->getProjectName();
			if ( !in_array( $projectName, $projectfilter ) ) {
				continue;
			}
			$actions = array( 'projectadmin' => array() );
			$actions['projectadmin'][] = $this->createActionLink( 'openstackmanager-createsudoer', array( 'action' => 'create', 'project' => $projectName ) );
			$out .= $this->createProjectSection( $projectName, $actions, $this->getSudoers( $project ) );
		}

		$this->getOutput()->addHTML( $out );
	}

	function makeHumanReadableUserlist( $userList, $project ) {
		$HRList = array();

		$projectuids = $project->getMemberUids();
		$leftovers = $userList;
		foreach ( $projectuids as $uid ) {
			$userIndex = array_search( $uid, $userList );
			if ( $userIndex !== false ) {
				$HRList[] = $project->memberForUid( $uid );
				unset( $leftovers[$userIndex] );
			}
		}

		# Now $leftovers contains anything in the sudo users list that wasn't
		# a user.  We still want to display them to the user, but some are special
		# cases which we'll dress up a bit.
		$AllProjectMembers = "%" . $project->getProjectGroup()->getProjectGroupName();
		foreach ( $leftovers as $leftover ) {
			if ( $leftover == $AllProjectMembers ) {
				array_unshift( $HRList, $this->msg( 'openstackmanager-allmembers' )->text() );
			} elseif ( $leftover == 'ALL' ) {
				array_unshift( $HRList, $this->msg( 'openstackmanager-allmembers' )->text() );
			} elseif ( $leftover[0] == '%' ) {
				array_unshift( $HRList, $this->msg( 'openstackmanager-membersofgroup', substr( $leftover, 1 ) ) );
			} else {
				array_unshift( $HRList, $leftover );
			}
		}

		return $HRList;
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
					// $instanceNames will be output later with no change of escaping
					$fqdn = $host->getFullyQualifiedHostName();
					$instanceNames[$fqdn] = htmlentities( $instance->getInstanceName() . ' (' . $region . ')' );

					// We might have stored this as a display rather than as i-xxxxx:
					$displayfqdn = $host->getFullyQualifiedDisplayName();
					$instanceNames[$displayfqdn] = htmlentities( $instance->getInstanceName() . ' (' . $region . ')' );
				}
			}
		}
		$headers = array( 'openstackmanager-sudoername', 'openstackmanager-sudoerusers', 'openstackmanager-sudoerhosts',
				'openstackmanager-sudoerrunas', 'openstackmanager-sudoercommands',
				'openstackmanager-sudoeroptions', 'openstackmanager-actions' );
		$sudoers = OpenStackNovaSudoer::getAllSudoersByProject( $projectName );
		$sudoerRows = array();
		foreach ( $sudoers as $sudoer ) {
			$sudoerRow = array();
			$sudoerName = $sudoer->getSudoerName();
			$this->pushResourceColumn( $sudoerRow, $sudoerName );
			$userNames = array();
			$projectmembers = $project->getMembers();

			$userNames = $this->makeHumanReadableUserlist( $sudoer->getSudoerUsers(), $project );
			$sudoRunAsUsers = $this->makeHumanReadableUserlist( $sudoer->getSudoerRunAsUsers(), $project );

			$sudoHosts = $sudoer->getSudoerHosts();
			$sudoHostNames = array();
			foreach ( $sudoHosts as $sudoHost ) {
				if ( array_key_exists( $sudoHost, $instanceNames ) ) {
					if ( ! in_array( $instanceNames[$sudoHost], $sudoHostNames ) ) {
						$sudoHostNames[] = $instanceNames[$sudoHost];
					}
				}
			}
			if ( in_array( 'ALL', $sudoHosts ) ) {
				array_unshift( $sudoHostNames, 'ALL' );
			}
			$this->pushRawResourceColumn( $sudoerRow, $this->createResourceList( $userNames ) );
			$this->pushRawResourceColumn( $sudoerRow, $this->createResourceList( $sudoHostNames ) );
			$this->pushRawResourceColumn( $sudoerRow, $this->createResourceList( $sudoRunAsUsers ) );
			$this->pushRawResourceColumn( $sudoerRow, $this->createResourceList( $sudoer->getSudoerCommands() ) );
			$this->pushRawResourceColumn( $sudoerRow, $this->createResourceList( $sudoer->getSudoerOptions() ) );
			$actions = array();
			$actions[] = $this->createActionLink( 'openstackmanager-modify',
				array( 'action' => 'modify', 'sudoername' => $sudoerName, 'project' => $projectName )
			);
			$actions[] = $this->createActionLink( 'openstackmanager-delete',
				array( 'action' => 'delete', 'sudoername' => $sudoerName, 'project' => $projectName )
			);
			$this->pushRawResourceColumn( $sudoerRow, $this->createResourceList( $actions ) );
			$sudoerRows[] = $sudoerRow;
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
		$newusers = array();
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
		$runasusers = $this->removeALLFromUserKeys($formData['runas']);
		$success = OpenStackNovaSudoer::createSudoer( $formData['sudoername'], $formData['project'], $this->removeALLFromUserKeys($formData['users']), $formData['hosts'], $runasusers, $commands, $options );
		if ( ! $success ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-createsudoerfailed' );
			return false;
		}
		$this->getOutput()->addWikiMsg( 'openstackmanager-createdsudoer' );

		$out = '<br />';
		$out .= Linker::link(
			$this->getPageTitle(),
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
			$this->getPageTitle(),
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

			$projectName = $formData['project'];
			$project = OpenStackNovaProject::getProjectByName( $projectName );
			$projectuids = $project->getMemberUids();
			$projectserviceusers = $project->getServiceUsers();
			$projectGroup = "%" . $project->getProjectGroup()->getProjectGroupName();

			$users = $this->removeALLFromUserKeys($formData['users']);
			$formerusers = $sudoer->getSudoerUsers();
			foreach ( $formerusers as $candidate ) {
				# Anything in this list that isn't a user or  ALL
				# wasn't exposed to user selection so needs to stay.
				if ( $candidate != $projectGroup ) {
					if ( ( ! in_array( $candidate, $projectuids ) ) && ( ! in_array( $candidate, $projectserviceusers ) ) ) {
						$users[] = $candidate;
					}
				}
			}

			$runasusers = $this->removeALLFromUserKeys($formData['runas']);
			foreach ( $sudoer->getSudoerRunAsUsers() as $candidate ) {
				if ( $candidate != $projectGroup ) {
					if ( ( ! in_array( $candidate, $projectuids ) ) && ( ! in_array( $candidate, $projectserviceusers ) ) ) {
						$runasusers[] = $candidate;
					}
				}
			}

			$success = $sudoer->modifySudoer( $users, $formData['hosts'], $runasusers, $commands, $options );
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
			$this->getPageTitle(),
			$this->msg( 'openstackmanager-backsudoerlist' )->escaped()
		);
		$this->getOutput()->addHTML( $out );

		return true;
	}
}
