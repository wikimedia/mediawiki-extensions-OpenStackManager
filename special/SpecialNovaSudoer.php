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
			if ( !$this->userLDAP->inProject( $this->projectName ) ) {
				$this->notInProject( $this->project );
				return;
			}
			$this->createSudoer();
		} elseif ( $action === "delete" ) {
			if ( !$this->userLDAP->inProject( $this->projectName ) ) {
				$this->notInProject( $this->project );
				return;
			}
			$this->deleteSudoer();
		} elseif ( $action === "modify" ) {
			if ( !$this->userLDAP->inProject( $this->projectName ) ) {
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
		$this->getOutput()->setPageTitle( $this->msg( 'openstackmanager-modifysudoer' ) );
		if ( !$this->userLDAP->inRole( 'projectadmin', $this->projectName ) ) {
			$this->notInRole( 'projectadmin', $this->projectName );
			return false;
		}

		$userArr = $this->getSudoUsers( $this->projectName );
		$user_keys = $userArr["keys"];
		$runasArr = $this->getSudoRunAsUsers( $this->projectName );
		$runas_keys = $runasArr["keys"];
		$sudoerInfo = [];
		$sudoerInfo['sudoername'] = [
			'type' => 'text',
			'label-message' => 'openstackmanager-sudoername',
			'default' => '',
			'section' => 'sudoer',
			'name' => 'sudoername',
		];
		$sudoerInfo['users'] = [
			'type' => 'multiselect',
			'label-message' => 'openstackmanager-sudoerusers',
			'options' => $user_keys,
			'section' => 'sudoer',
			'name' => 'users',
		];
		$sudoerInfo['runas'] = [
			'type' => 'multiselect',
			'label-message' => 'openstackmanager-sudoerrunas',
			'options' => $runas_keys,
			'section' => 'sudoer',
			'name' => 'runas',
		];
		$sudoerInfo['commands'] = [
			'type' => 'textarea',
			'label-message' => 'openstackmanager-sudoercommands',
			'default' => '',
			'section' => 'sudoer',
			'name' => 'commands',
		];
		$sudoerInfo['options'] = [
			'type' => 'textarea',
			'label-message' => 'openstackmanager-sudoeroptions',
			'default' => '',
			'section' => 'sudoer',
			'name' => 'options',
		];
		$sudoerInfo['project'] = [
			'type' => 'hidden',
			'default' => $this->projectName,
			'name' => 'project',
		];
		$sudoerInfo['action'] = [
			'type' => 'hidden',
			'default' => 'create',
			'name' => 'action',
		];
		$sudoerInfo['requirepassword'] = [
			'type' => 'check',
			'label-message' => 'openstackmanager-requirepassword',
			'default' => false,
			'section' => 'sudoer',
			'name' => 'requirepassword',
		];

		$sudoerForm = new HTMLForm(
			$sudoerInfo,
			$this->getContext(),
			'openstackmanager-novasudoer'
		);
		$sudoerForm->setSubmitID( 'novasudoer-form-createsudoersubmit' );
		$sudoerForm->setSubmitCallback( [ $this, 'tryCreateSubmit' ] );
		$sudoerForm->show();

		return true;
	}

	/**
	 * @return bool
	 */
	function deleteSudoer() {
		$this->setHeaders();
		$this->getOutput()->setPageTitle( $this->msg( 'openstackmanager-deletesudoer' ) );
		if ( !$this->userLDAP->inRole( 'projectadmin', $this->projectName ) ) {
			$this->notInRole( 'projectadmin', $this->projectName );
			return false;
		}
		$sudoername = $this->getRequest()->getText( 'sudoername' );
		if ( !$this->getRequest()->wasPosted() ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-deletesudoer-confirm', $sudoername );
		}
		$sudoerInfo = [];
		$sudoerInfo['sudoername'] = [
			'type' => 'hidden',
			'default' => $sudoername,
			'name' => 'sudoername',
		];
		$sudoerInfo['project'] = [
			'type' => 'hidden',
			'default' => $this->projectName,
			'name' => 'project',
		];
		$sudoerInfo['action'] = [
			'type' => 'hidden',
			'default' => 'delete',
			'name' => 'action',
		];
		$sudoerForm = new HTMLForm(
			$sudoerInfo,
			$this->getContext(),
			'openstackmanager-novasudoer'
		);
		$sudoerForm->setSubmitID( 'novasudoer-form-deletesudoersubmit' );
		$sudoerForm->setSubmitCallback( [ $this, 'tryDeleteSubmit' ] );
		$sudoerForm->show();

		return true;
	}

	/**
	 * @return bool
	 */
	function modifySudoer() {
		$this->setHeaders();
		$this->getOutput()->setPageTitle( $this->msg( 'openstackmanager-modifysudoer' ) );
		if ( !$this->userLDAP->inRole( 'projectadmin', $this->projectName ) ) {
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
		$k = array_search( '!authenticate', $optionArray );
		if ( $k !== false ) {
			unset( $optionArray[$k] );
		} else {
			$k = array_search( 'authenticate', $optionArray );
			if ( $k !== false ) {
				unset( $optionArray[$k] );
				$requirePassword = true;
			}
		}
		$options = implode( "\n", $optionArray );
		$sudoerInfo = [];
		$sudoerInfo['sudoernameinfo'] = [
			'type' => 'info',
			'label-message' => 'openstackmanager-sudoername',
			'default' => $sudoername,
			'section' => 'sudoer',
			'name' => 'sudoernameinfo',
		];
		$sudoerInfo['sudoername'] = [
			'type' => 'hidden',
			'default' => $sudoername,
			'name' => 'sudoername',
		];
		$sudoerInfo['users'] = [
			'type' => 'multiselect',
			'label-message' => 'openstackmanager-sudoerusers',
			'options' => $user_keys,
			'default' => $user_defaults,
			'section' => 'sudoer',
			'name' => 'users',
		];
		$sudoerInfo['runas'] = [
			'type' => 'multiselect',
			'label-message' => 'openstackmanager-sudoerrunas',
			'options' => $runas_keys,
			'default' => $runas_defaults,
			'section' => 'sudoer',
			'name' => 'runas',
		];
		$sudoerInfo['commands'] = [
			'type' => 'textarea',
			'label-message' => 'openstackmanager-sudoercommands',
			'default' => $commands,
			'section' => 'sudoer',
			'name' => 'commands',
		];
		$sudoerInfo['options'] = [
			'type' => 'textarea',
			'label-message' => 'openstackmanager-sudoeroptions',
			'default' => $options,
			'section' => 'sudoer',
			'name' => 'options',
		];
		$sudoerInfo['project'] = [
			'type' => 'hidden',
			'default' => $this->projectName,
			'name' => 'project',
		];
		$sudoerInfo['action'] = [
			'type' => 'hidden',
			'default' => 'modify',
			'name' => 'action',
		];
		$sudoerInfo['requirepassword'] = [
			'type' => 'check',
			'label-message' => 'openstackmanager-requirepassword',
			'default' => $requirePassword,
			'section' => 'sudoer',
			'name' => 'requirepassword',
		];

		$sudoerForm = new HTMLForm(
			$sudoerInfo,
			$this->getContext(),
			'openstackmanager-novasudoer'
		);
		$sudoerForm->setSubmitID( 'novasudoer-form-createsudoersubmit' );
		$sudoerForm->setSubmitCallback( [ $this, 'tryModifySubmit' ] );
		$sudoerForm->show();

		return true;
	}

	function getSudoUsers( $projectName, $sudoer = null ) {
		$project = OpenStackNovaProject::getProjectByName( $projectName );
		$projectuids = $project->getMemberUids();
		$projectserviceusers = $project->getServiceUsers();

		$sudomembers = [];
		if ( $sudoer ) {
			$sudomembers = $sudoer->getSudoerUsers();
		}
		$user_keys = [];
		$user_defaults = [];

		# Add the 'all project members' option to the top
		$projectGroup = "%" . $project->getProjectGroupName();
		$all_members = $this->msg( 'openstackmanager-allmembers' )->text();
		$user_keys[htmlspecialchars( $all_members )] = $all_members;
		if ( in_array( 'ALL', $sudomembers ) || in_array( $projectGroup, $sudomembers ) ) {
			$user_defaults[] = $all_members;
		}

		foreach ( $projectuids as $userUid ) {
			$projectmember = $project->memberForUid( $userUid );

			$user_keys[htmlspecialchars( $projectmember )] = $userUid;
			if ( in_array( $userUid, $sudomembers ) ) {
				$user_defaults[] = $userUid;
			}
		}

		foreach ( $projectserviceusers as $serviceuser ) {
			$user_keys[htmlspecialchars( $serviceuser )] = $serviceuser;
			if ( in_array( $serviceuser, $sudomembers ) ) {
				$user_defaults[] = $serviceuser;
			}
		}

		return [ 'keys' => $user_keys, 'defaults' => $user_defaults ];
	}

	function getSudoRunAsUsers( $projectName, $sudoer = null ) {
		$project = OpenStackNovaProject::getProjectByName( $projectName );
		$projectuids = $project->getMemberUids();
		$projectserviceusers = $project->getServiceUsers();

		$runasmembers = [];
		if ( $sudoer ) {
			$runasmembers = $sudoer->getSudoerRunAsUsers();
		}

		$runas_keys = [];
		$runas_defaults = [];

		# 'ALL' includes all possible users, including system users and service users.
		$runas_keys['ALL'] = 'ALL';
		if ( in_array( 'ALL', $runasmembers ) ) {
			$runas_defaults['ALL'] = 'ALL';
		}

		# A safer option is 'all project members'
		$projectGroup = "%" . $project->getProjectGroupName();
		$all_members = $this->msg( 'openstackmanager-allmembers' )->text();
		$runas_keys[$all_members] = $all_members;
		if ( in_array( $projectGroup, $runasmembers ) ) {
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

		return [ 'keys' => $runas_keys, 'defaults' => $runas_defaults ];
	}

	/**
	 * @return void
	 */
	function listSudoers() {
		$this->setHeaders();
		$this->getOutput()->addModuleStyles( 'ext.openstack' );
		$this->getOutput()->setPageTitle( $this->msg( 'openstackmanager-sudoerlist' ) );

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
			$actions = [ 'projectadmin' => [] ];
			$actions['projectadmin'][] = $this->createActionLink(
				'openstackmanager-createsudoer', [ 'action' => 'create', 'project' => $projectName ]
			);
			$out .= $this->createProjectSection(
				$projectName, $actions, $this->getSudoers( $project )
			);
		}

		$this->getOutput()->addHTML( $out );
	}

	function makeHumanReadableUserlist( $userList, $project ) {
		$HRList = [];

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
		$AllProjectMembers = "%" . $project->getProjectGroupName();
		foreach ( $leftovers as $leftover ) {
			if ( $leftover == $AllProjectMembers ) {
				array_unshift( $HRList, $this->msg( 'openstackmanager-allmembers' )->text() );
			} elseif ( $leftover[0] == '%' ) {
				array_unshift( $HRList,
					$this->msg( 'openstackmanager-membersofgroup', substr( $leftover, 1 ) )
				);
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
		$headers = [ 'openstackmanager-sudoername', 'openstackmanager-sudoerusers',
				'openstackmanager-sudoerrunas', 'openstackmanager-sudoercommands',
				'openstackmanager-sudoeroptions', 'openstackmanager-actions' ];
		$sudoers = OpenStackNovaSudoer::getAllSudoersByProject( $projectName );
		$sudoerRows = [];
		foreach ( $sudoers as $sudoer ) {
			$sudoerRow = [];
			$sudoerName = $sudoer->getSudoerName();
			$this->pushResourceColumn( $sudoerRow, $sudoerName );
			$userNames = [];
			$projectmembers = $project->getMembers();

			$userNames = $this->makeHumanReadableUserlist( $sudoer->getSudoerUsers(), $project );
			$sudoRunAsUsers = $this->makeHumanReadableUserlist(
				$sudoer->getSudoerRunAsUsers(), $project
			);

			$this->pushRawResourceColumn(
				$sudoerRow, $this->createResourceList( $userNames )
			);
			$this->pushRawResourceColumn(
				$sudoerRow, $this->createResourceList( $sudoRunAsUsers )
			);
			$this->pushRawResourceColumn(
				$sudoerRow, $this->createResourceList( $sudoer->getSudoerCommands() )
			);
			$this->pushRawResourceColumn(
				$sudoerRow, $this->createResourceList( $sudoer->getSudoerOptions() )
			);
			$actions = [];
			$actions[] = $this->createActionLink( 'openstackmanager-modify',
				[ 'action' => 'modify', 'sudoername' => $sudoerName, 'project' => $projectName ]
			);
			$actions[] = $this->createActionLink( 'openstackmanager-delete',
				[ 'action' => 'delete', 'sudoername' => $sudoerName, 'project' => $projectName ]
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
	 * This function replaces 'ALL' and 'All project members' with a reference
	 * to the project user group.
	 *
	 * @param array $users a list of usernames and/or 'openstackmanager-allmembers'
	 * @return array modified list of usernames
	 */
	function removeALLFromUserKeys( $users ) {
		$newusers = [];
		foreach ( $users as $user ) {
			if ( $user == 'ALL' || $user == $this->msg( 'openstackmanager-allmembers' )->text() ) {
				$newusers[] = "%" . $this->project->getProjectGroupName();
			} else {
				$newusers[] = $user;
			}
		}
		return $newusers;
	}

	/**
	 * This function replaces 'All project members' with a reference
	 * to the project user group.  It differes from Remove ALLFromUserKeys
	 * because it preserves the string 'ALL' which is useful in the 'run as'
	 * context.
	 *
	 * @param array $users a list of usernames and/or 'openstackmanager-allmembers'
	 * @return array modified list of usernames
	 */
	function removeALLFromRunAsUserKeys( $users ) {
		$newusers = [];
		foreach ( $users as $user ) {
			if ( ( $user == $this->msg( 'openstackmanager-allmembers' )->text() ) ) {
				$newusers[] = "%" . $this->project->getProjectGroupName();
			} else {
				$newusers[] = $user;
			}
		}
		return $newusers;
	}

	/**
	 * @param array $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryCreateSubmit( $formData, $entryPoint = 'internal' ) {
		if ( $formData['commands'] ) {
			$commands = explode( "\n", $formData['commands'] );
		} else {
			$commands = [];
		}
		if ( $formData['options'] ) {
			$options = explode( "\n", $formData['options'] );
		} else {
			$options = [];
		}
		if ( $formData['requirepassword'] ) {
			$options[] = 'authenticate';
		} else {
			$options[] = '!authenticate';
		}
		$runasusers = $this->removeALLFromRunAsUserKeys( $formData['runas'] );
		$success = OpenStackNovaSudoer::createSudoer(
			$formData['sudoername'],
			$formData['project'],
			$this->removeALLFromUserKeys( $formData['users'] ),
			$runasusers,
			$commands,
			$options
		);
		if ( !$success ) {
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
	 * @param array $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryDeleteSubmit( $formData, $entryPoint = 'internal' ) {
		$success = OpenStackNovaSudoer::deleteSudoer(
			$formData['sudoername'], $formData['project']
		);
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
	 * @param array $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryModifySubmit( $formData, $entryPoint = 'internal' ) {
		$sudoer = OpenStackNovaSudoer::getSudoerByName(
			$formData['sudoername'], $formData['project']
		);
		if ( $sudoer ) {
			if ( $formData['commands'] ) {
				$commands = explode( "\n", $formData['commands'] );
			} else {
				$commands = [];
			}
			if ( $formData['options'] ) {
				$options = explode( "\n", $formData['options'] );
			} else {
				$options = [];
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
			$projectGroup = "%" . $project->getProjectGroupName();

			$users = $this->removeALLFromUserKeys( $formData['users'] );
			$formerusers = $sudoer->getSudoerUsers();
			foreach ( $formerusers as $candidate ) {
				# Anything in this list that isn't a user or  ALL
				# wasn't exposed to user selection so needs to stay.
				if ( $candidate != $projectGroup ) {
					if ( !in_array( $candidate, $projectuids ) &&
						!in_array( $candidate, $projectserviceusers )
					) {
						$users[] = $candidate;
					}
				}
			}

			$runasusers = $this->removeALLFromRunAsUserKeys( $formData['runas'] );
			foreach ( $sudoer->getSudoerRunAsUsers() as $candidate ) {
				if ( ( $candidate != $projectGroup ) && ( $candidate != 'ALL' ) ) {
					if ( !in_array( $candidate, $projectuids ) &&
						!in_array( $candidate, $projectserviceusers )
					) {
						$runasusers[] = $candidate;
					}
				}
			}

			$success = $sudoer->modifySudoer( $users, $runasusers, $commands, $options );
			if ( !$success ) {
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

	protected function getGroupName() {
		return 'nova';
	}
}
