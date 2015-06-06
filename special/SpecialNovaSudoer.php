<?php

/**
 * todo comment me
 *
 * @file
 * @ingroup Extensions
 */

class SpecialNovaSudoer extends SpecialNova {
	public $userLDAP;

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

		# 'ALL' includes all possible users, including system users and service users.
		$runas_keys['ALL'] = 'ALL';
		if ( in_array( 'ALL', $runasmembers ) ) {
			$runas_defaults['ALL'] = 'ALL';
		}

		# A safer option is 'all project members'
		$projectGroup = "%" . $project->getProjectGroup()->getProjectGroupName();
		$all_members = $this->msg( 'openstackmanager-allmembers' )->text();
		$runas_keys[$all_members] = $all_members;
		if ( in_array ( $projectGroup, $runasmembers ) ) {
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
		$headers = array( 'openstackmanager-sudoername', 'openstackmanager-sudoerusers',
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

			$this->pushRawResourceColumn( $sudoerRow, $this->createResourceList( $userNames ) );
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
	 *  This function replaces 'ALL' and 'All project members' with a reference
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
	 *
	 *  @ param $users: a list of usernames and/or 'openstackmanager-allmembers'
	 *  @ return modified list of usernames
	 *
	 *  This function replaces 'All project members' with a reference
	 *   to the project user group.  It differes from Remove ALLFromUserKeys
	 *   because it preserves the string 'ALL' which is useful in the 'run as'
	 *   context.
	 *
	 */
	function removeALLFromRunAsUserKeys( $users ) {
		$newusers = array();
		foreach ( $users as $user ) {
			if ( ( $user == $this->msg( 'openstackmanager-allmembers' )->text() ) ) {
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
		$runasusers = $this->removeALLFromRunAsUserKeys($formData['runas']);
		$success = OpenStackNovaSudoer::createSudoer( $formData['sudoername'], $formData['project'], $this->removeALLFromUserKeys($formData['users']), $runasusers, $commands, $options );
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

			$runasusers = $this->removeALLFromRunAsUserKeys($formData['runas']);
			foreach ( $sudoer->getSudoerRunAsUsers() as $candidate ) {
				if ( ( $candidate != $projectGroup ) && ( $candidate != 'ALL' ) ) {
					if ( ( ! in_array( $candidate, $projectuids ) ) && ( ! in_array( $candidate, $projectserviceusers ) ) ) {
						$runasusers[] = $candidate;
					}
				}
			}

			$success = $sudoer->modifySudoer( $users, $runasusers, $commands, $options );
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
