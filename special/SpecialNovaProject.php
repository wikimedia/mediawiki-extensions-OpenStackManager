<?php

/**
 *  To do: comment me
 *
 * @file
 * @ingroup Extensions
 */

class SpecialNovaProject extends SpecialNova {
		/**
	 * @var OpenStackNovaUser
	 */
	var $userLDAP;

	/**
	 * @var OpenStackNovaController
	 */
	var $userNova;

	function __construct() {
		parent::__construct( 'NovaProject', 'manageproject' );
	}

	function execute( $par ) {
		if ( !$this->getUser()->isLoggedIn() ) {
			$this->notLoggedIn();
			return;
		}
		$this->userLDAP = new OpenStackNovaUser();
		if ( ! $this->userLDAP->exists() ) {
			$this->noCredentials();
			return;
		}
		$this->checkTwoFactor();
		$this->userNova = OpenStackNovaController::newFromUser( $this->userLDAP );
		$action = $this->getRequest()->getVal( 'action' );
		if ( $action === "delete" ) {
			$this->deleteProject();
		} elseif ( $action === "addmember" ) {
			$this->addMember();
		} elseif ( $action === "deletemember" ) {
			$this->deleteMember();
		} elseif ( $action === "configureproject" ) {
			$this->configureProject();
		} elseif ( $action === "addservicegroup" ) {
			$this->addServiceGroup();
		} elseif ( $action === "removeservicegroup" ) {
			$this->removeServiceGroup();
		} else {
			$this->listProjects();
		}
	}

	/**
	 * @return bool
	 */
	function addMember() {
		$this->setHeaders();
		$this->getOutput()->setPagetitle( $this->msg( 'openstackmanager-addmember' ) );

		$project = $this->getRequest()->getText( 'projectname' );
		if ( !$this->userCanExecute( $this->getUser() ) && !$this->userLDAP->inRole( 'projectadmin', $project ) ) {
			$this->notInRole( 'projectadmin' );
			return false;
		}
		$projectInfo = array();
		$projectInfo['member'] = array(
			'type' => 'text',
			'label-message' => 'openstackmanager-member',
			'default' => '',
			'name' => 'member',
		);
		$projectInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'addmember',
			'name' => 'action',
		);
		$projectInfo['projectname'] = array(
			'type' => 'hidden',
			'default' => $project,
			'name' => 'projectname',
		);

		$projectForm = new HTMLForm( $projectInfo, 'openstackmanager-novaproject' );
		$projectForm->setTitle( SpecialPage::getTitleFor( 'NovaProject' ) );
		$projectForm->setSubmitID( 'novaproject-form-addmembersubmit' );
		$projectForm->setSubmitCallback( array( $this, 'tryAddMemberSubmit' ) );
		$projectForm->show();

		return true;
	}

	/**
	 * @return bool
	 */
	function deleteMember() {
		$this->setHeaders();
		$this->getOutput()->setPagetitle( $this->msg( 'openstackmanager-removemember' ) );

		$projectname = $this->getRequest()->getText( 'projectname' );
		if ( !$this->userCanExecute( $this->getUser() ) && !$this->userLDAP->inRole( 'projectadmin', $project ) ) {
			$this->notInRole( 'projectadmin' );
			return false;
		}
		$project = OpenStackNovaProject::getProjectByName( $projectname );
		$projectmembers = $project->getMembers();
		$member_keys = array();
		foreach ( $projectmembers as $projectmember ) {
			$member_keys[$projectmember] = $projectmember;
		}
		$projectInfo = array();
		$projectInfo['members'] = array(
			'type' => 'multiselect',
			'label-message' => 'openstackmanager-member',
			'options' => $member_keys,
			'name' => 'members',
		);
		$projectInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'deletemember',
			'name' => 'action',
		);
		$projectInfo['projectname'] = array(
			'type' => 'hidden',
			'default' => $projectname,
			'name' => 'projectname',
		);

		$projectForm = new HTMLForm( $projectInfo, 'openstackmanager-novaproject' );
		$projectForm->setTitle( SpecialPage::getTitleFor( 'NovaProject' ) );
		$projectForm->setSubmitID( 'novaproject-form-deletemembersubmit' );
		$projectForm->setSubmitCallback( array( $this, 'tryDeleteMemberSubmit' ) );
		$projectForm->show();

		return true;
	}

	/**
	 * @return bool
	 */
	function addServiceGroup() {
		$this->setHeaders();
		$this->getOutput()->setPagetitle( $this->msg( 'openstackmanager-addservicegroup' ) );

		$project = $this->getRequest()->getText( 'projectname' );
		if ( !$this->userLDAP->inProject( $projectname ) ) {
			$this->notInProject();
			return false;
		}

		$projectInfo = array();
		$projectInfo['servicegroupname'] = array(
			'type' => 'text',
			'label-message' => 'openstackmanager-servicegroupname',
			'validation-callback' => array( $this, 'validateText' ),
			'default' => '',
			'name' => 'servicegroupname',
		);
		$projectInfo['projectname'] = array(
			'type' => 'hidden',
			'default' => $project,
			'name' => 'projectname',
		);
		$projectInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'addservicegroup',
			'name' => 'action',
		);

		$projectForm = new HTMLForm( $projectInfo, 'openstackmanager-addservicegroup' );
		$projectForm->setTitle( SpecialPage::getTitleFor( 'NovaProject' ) );
		$projectForm->setSubmitID( 'novaproject-form-createservicegroupsubmit' );
		$projectForm->setSubmitCallback( array( $this, 'tryCreateServiceGroupSubmit' ) );
		$projectForm->show();

		return true;
	}

	/**
	 * @return bool
	 */
	function removeServiceGroup() {
		$this->setHeaders();
		$project = $this->getRequest()->getText( 'projectname' );

		if ( !$this->userCanExecute( $this->getUser() ) && !$this->userLDAP->inRole( 'projectadmin', $project ) ) {
			$this->notInRole( 'projectadmin' );
			return false;
		}
		$this->getOutput()->setPagetitle( $this->msg( 'openstackmanager-removeservicegroup' ) );

		$groupName = $this->getRequest()->getText( 'groupname' );
		if ( ! $this->getRequest()->wasPosted() ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-removeservicegroupconfirm', $groupName );
		}
		$projectInfo = array();
		$projectInfo['projectname'] = array(
			'type' => 'hidden',
			'default' => $project,
			'name' => 'projectname',
		);
		$projectInfo['groupname'] = array(
			'type' => 'hidden',
			'default' => $groupName,
			'name' => 'groupname',
		);
		$projectInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'removeservicegroup',
			'name' => 'action',
		);
		$projectForm = new HTMLForm( $projectInfo, 'openstackmanager-novaproject' );
		$projectForm->setTitle( SpecialPage::getTitleFor( 'NovaProject' ) );
		$projectForm->setSubmitID( 'novaproject-form-removeservicegroupsubmit' );
		$projectForm->setSubmitCallback( array( $this, 'tryRemoveServiceGroupSubmit' ) );
		$projectForm->show();

		return true;
	}

	/**
	 * @return bool
	 */
	function deleteProject() {
		$this->setHeaders();
		if ( !$this->userCanExecute( $this->getUser() ) ) {
			$this->displayRestrictionError();
			return false;
		}
		$this->getOutput()->setPagetitle( $this->msg( 'openstackmanager-deleteproject' ) );

		$project = $this->getRequest()->getText( 'projectname' );
		if ( ! $this->getRequest()->wasPosted() ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-removeprojectconfirm', $project );
		}
		$projectInfo = array();
		$projectInfo['projectname'] = array(
			'type' => 'hidden',
			'default' => $project,
			'name' => 'projectname',
		);
		$projectInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'delete',
			'name' => 'action',
		);
		$projectForm = new HTMLForm( $projectInfo, 'openstackmanager-novaproject' );
		$projectForm->setTitle( SpecialPage::getTitleFor( 'NovaProject' ) );
		$projectForm->setSubmitID( 'novaproject-form-deleteprojectsubmit' );
		$projectForm->setSubmitCallback( array( $this, 'tryDeleteSubmit' ) );
		$projectForm->show();

		return true;
	}

	/**
	 * @return void
	 */
	function listProjects() {
		$this->setHeaders();
		$this->getOutput()->setPageTitle( $this->msg( 'openstackmanager-projectlist' ) );
		$this->getOutput()->addModuleStyles( 'ext.openstack' );

		if ( $this->getUser()->isAllowed( 'listall' ) ) {
			$projects = OpenStackNovaProject::getAllProjects();
			$this->showCreateProject();
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
			$actions = array();
			$out .= $this->createProjectSection( $projectName, $actions, $this->getProject( $project ) );
		}

		$this->getOutput()->addHTML( $out );
	}

	function getProject( $project ) {
		$project->fetchProjectInfo();
		$headers = array( 'openstackmanager-members', 'openstackmanager-roles', 'openstackmanager-actions' );
		$projectRows = array();
		$projectRow = array();
		$this->pushRawResourceColumn( $projectRow, $this->createResourceList( $project->getMembers() ) );
		$roleRows = array();
		$projectName = $project->getProjectName();
		foreach ( $project->getRoles() as $role ) {
			$roleRow = array();
			$roleName = $role->getRoleName();
			$this->pushResourceColumn( $roleRow, $roleName );
			$this->pushRawResourceColumn( $roleRow, $this->createResourceList( $role->getMembers() ) );
			$actions = array();
			$specialRoleTitle = Title::newFromText( 'Special:NovaRole' );
			$actions[] = $this->createActionLink( 'openstackmanager-addrolemember',
				array( 'action' => 'addmember', 'projectname' => $projectName, 'rolename' => $roleName, 'returnto' => 'Special:NovaProject' ),
				$specialRoleTitle
			);
			$actions[] = $this->createActionLink( 'openstackmanager-removerolemember',
				array( 'action' => 'deletemember', 'projectname' => $projectName, 'rolename' => $roleName, 'returnto' => 'Special:NovaProject' ),
				$specialRoleTitle
			);
			$this->pushRawResourceColumn( $roleRow,  $this->createResourceList( $actions ) );
			$roleRows[] = $roleRow;
		}
		$roleheaders = array( 'openstackmanager-rolename', 'openstackmanager-members', 'openstackmanager-actions' );
		$this->pushRawResourceColumn( $projectRow, $this->createResourceTable( $roleheaders, $roleRows ) );

		$serviceGroups =  $project->getServiceGroups();
		if ( $serviceGroups ) {
			$servicegroupRows = array();
			foreach ( $serviceGroups as $group) {
				$groupName = $group->groupName;
				$groupRow = array();
				$this->pushResourceColumn( $groupRow, $groupName );
				$this->pushRawResourceColumn( $groupRow, $this->createResourceList( $group->getMembers() ) );
				$actions = array();
				$specialGroupTitle = Title::newFromText( 'Special:NovaServiceGroup' );
				$actions[] = $this->createActionLink( 'openstackmanager-addservicegroupmember',
					array( 'action' => 'addmember', 'projectname' => $projectName, 'servicegroupname' => $groupName, 'returnto' => 'Special:NovaProject' ),
					$specialGroupTitle
				);
				$actions[] = $this->createActionLink( 'openstackmanager-removeservicegroupmember',
					array( 'action' => 'deletemember', 'projectname' => $projectName, 'servicegroupname' => $groupName, 'returnto' => 'Special:NovaProject' ),
					$specialGroupTitle
				);
				$actions[] = $this->createActionLink( 'openstackmanager-removeservicegroup', array( 'action' => 'removeservicegroup', 'projectname' => $projectName, 'groupname' => $groupName ) );
				$this->pushRawResourceColumn( $groupRow,  $this->createResourceList( $actions ) );
				$servicegroupRows[] = $groupRow;
			}
			$headers = array( 'openstackmanager-members', 'openstackmanager-roles', 'openstackmanager-servicegroups', 'openstackmanager-actions' );
			$servicegroupheaders = array( 'openstackmanager-servicegroupname', 'openstackmanager-members', 'openstackmanager-actions' );
			$this->pushRawResourceColumn( $projectRow, $this->createResourceTable( $servicegroupheaders, $servicegroupRows ) );
		}

		$actions = array();
		$actions[] = $this->createActionLink( 'openstackmanager-deleteproject', array( 'action' => 'delete', 'projectname' => $projectName ) );
		$actions[] = $this->createActionLink( 'openstackmanager-addmember', array( 'action' => 'addmember', 'projectname' => $projectName ) );
		$actions[] = $this->createActionLink( 'openstackmanager-removemember', array( 'action' => 'deletemember', 'projectname' => $projectName ) );
		$actions[] = $this->createActionLink( 'openstackmanager-addservicegroup', array( 'action' => 'addservicegroup', 'projectname' => $projectName ) );
		$actions[] = $this->createActionLink( 'openstackmanager-configure', array( 'action' => 'configureproject', 'projectname' => $projectName ) );
		$this->pushRawResourceColumn( $projectRow,  $this->createResourceList( $actions ) );
		$projectRows[] = $projectRow;
		return $this->createResourceTable( $headers, $projectRows );
	}

	function showCreateProject() {
		global $wgRequest;

		if ( $wgRequest->wasPosted() && $wgRequest->getVal( 'action' ) !== 'create' ) {
			return null;
		}
		$projectInfo = array();
		$projectInfo['projectname'] = array(
			'type' => 'text',
			'label-message' => 'openstackmanager-projectname',
			'validation-callback' => array( $this, 'validateText' ),
			'default' => '',
			'section' => 'project',
			'name' => 'projectname',
		);
		$projectInfo['member'] = array(
			'type' => 'text',
			'label-message' => 'openstackmanager-member',
			'default' => '',
			'section' => 'project',
			'name' => 'member',
		);
		$role_keys = array();
		foreach ( OpenStackNovaProject::$rolenames as $rolename ) {
			$role_keys[$rolename] = $rolename;
		}
		$projectInfo['roles'] = array(
			'type' => 'multiselect',
			'label-message' => 'openstackmanager-roles',
			'section' => 'project',
			'options' => $role_keys,
			'name' => 'roles',
		);
		$projectInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'create',
			'name' => 'action',
		);

		$projectForm = new HTMLForm( $projectInfo, 'openstackmanager-novaproject' );
		$projectForm->setTitle( SpecialPage::getTitleFor( 'NovaProject' ) );
		$projectForm->setSubmitID( 'novaproject-form-createprojectsubmit' );
		$projectForm->setSubmitCallback( array( $this, 'tryCreateSubmit' ) );
		$projectForm->show();
	}

	/**
	 * @return bool
	 */
	function configureProject() {
		global $wgOpenStackManagerServiceGroupPrefix;

		$this->setHeaders();
		$projectName = $this->getRequest()->getText( 'projectname' );
		$this->getOutput()->setPagetitle( $this->msg( 'openstackmanager-configureproject', $projectName ) );
		if ( !$this->userCanExecute( $this->getUser() ) && !$this->userLDAP->inRole( 'projectadmin', $project ) ) {
			$this->notInRole( 'projectadmin' );
			return false;
		}
		$project = OpenStackNovaProject::getProjectByName( $projectName );

		$volumes = $project->getVolumeSettings();
		$defaultHomedirs = in_array( "home", $volumes );
		$defaultProject = in_array( "project", $volumes );
		$homePattern = $project->getServiceGroupHomedirPattern();

		$formInfo = array();
		$formInfo['homedirs'] = array(
			'type' => 'check',
			'label-message' => 'openstackmanager-configureproject-sharedhomedirs',
			'default' => $defaultHomedirs,
			'section' => 'volume',
			'name' => 'sharedhomedirs',
		);
		$formInfo['storage'] = array(
			'type' => 'check',
			'label-message' => 'openstackmanager-configureproject-sharedstorage',
			'default' => $defaultProject,
			'section' => 'volume',
			'name' => 'sharedstorage',
		);
		$formInfo['serviceuserhome'] = array(
			'type' => 'text',
			'label-message' => 'openstackmanager-configureproject-serviceuserhome',
			'default' => $homePattern,
			'section' => 'servicegroup',
			'name' => 'serviceuserhome',
		);
		$msg = $this->msg( 'openstackmanager-configureproject-serviceuserinfo', $wgOpenStackManagerServiceGroupPrefix );
		$formInfo['serviceuserhomeinfo'] = array(
			'type' => 'info',
			'section' => 'servicegroup',
			'label' => $msg,
		);
		$formInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'configureproject',
			'name' => 'action',
		);
		$formInfo['projectname'] = array(
			'type' => 'hidden',
			'default' => $projectName,
			'name' => 'projectname',
		);

		$projectForm = new HTMLForm( $formInfo, 'openstackmanager-configureproject' );
		$projectForm->setTitle( SpecialPage::getTitleFor( 'NovaProject' ) );
		$projectForm->setSubmitID( 'novaproject-form-configuresubmit' );
		$projectForm->setSubmitCallback( array( $this, 'tryConfigureProjectSubmit' ) );
		$projectForm->show();

		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryCreateServiceGroupSubmit( $formData, $entryPoint = 'internal' ) {
		$project = OpenStackNovaProject::getProjectByName( $formData['projectname'] );
		$username = $this->userLDAP->getUsername();

		$success = $project->addServiceGroup( $formData['servicegroupname'], $username );
		if ( ! $success ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-createservicegroupfailed' );
			return false;
		}

		$this->getOutput()->addWikiMsg( 'openstackmanager-createdservicegroup' );

		$out = '<br />';
		$out .= Linker::link(
			$this->getTitle(),
			$this->msg( 'openstackmanager-backprojectlist' )->escaped()
		);
		$this->getOutput()->addHTML( $out );

		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryRemoveServiceGroupSubmit( $formData, $entryPoint = 'internal' ) {
		$project = OpenStackNovaProject::getProjectByName( $formData['projectname'] );

		$success = $project->deleteServiceGroup( $formData['groupname'] );
		if ( $success ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-removedservicegroup' );
		} else {
			$this->getOutput()->addWikiMsg( 'openstackmanager-removeservicegroupfailed' );
		}

		$out = '<br />';
		$out .= Linker::link(
			$this->getTitle(),
			$this->msg( 'openstackmanager-backprojectlist' )->escaped()
		);
		$this->getOutput()->addHTML( $out );

		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryCreateSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOpenStackManagerDefaultSecurityGroupRules;

		$success = OpenStackNovaProject::createProject( $formData['projectname'] );
		if ( ! $success ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-createprojectfailed' );
			return false;
		}
		$project = OpenStackNovaProject::getProjectByName( $formData['projectname'] );
		$username = $this->userLDAP->getUsername();
		$project->addMember( $username );
		$members = explode( ',', $formData['member'] );
		foreach ( $members as $member ) {
			$project->addMember( $member );
		}
		$roles = $project->getRoles();
		foreach ( $roles as $role ) {
			if ( in_array( $role->getRoleName(), $formData['roles'] ) ) {
				foreach ( $members as $member ) {
					$role->addMember( $member );
				}
			}
			// We also need to ensure the project creator is in all roles
			$role->addMember( $username );
		}
		# Change the connection to reference this project
		$this->userNova->setProject( $formData['projectname'] );
		$regions = $this->userNova->getRegions( 'compute' );
		foreach ( $regions as $region ) {
			$this->userNova->setRegion( $region );
			$securityGroups = $this->userNova->getSecurityGroups();
			$groupid = '';
			foreach ( $securityGroups as $securityGroup ) {
				if ( $securityGroup->getGroupName() === 'default' ) {
					$groupid = $securityGroup->getGroupId();
				}
			}
			if ( !$groupid ) {
				continue;
			}
			foreach ( $wgOpenStackManagerDefaultSecurityGroupRules as $rule ) {
				$fromport = '';
				$toport = '';
				$protocol = '';
				$range = '';
				$sourcegroupid = '';
				if ( array_key_exists( 'fromport', $rule ) ) {
					$fromport = $rule['fromport'];
				}
				if ( array_key_exists( 'toport', $rule ) ) {
					$toport = $rule['toport'];
				}
				if ( array_key_exists( 'protocol', $rule ) ) {
					$protocol = $rule['protocol'];
				}
				if ( array_key_exists( 'range', $rule ) ) {
					$range = $rule['range'];
				}
				if ( array_key_exists( 'group', $rule ) ) {
					foreach ( $securityGroups as $securityGroup ) {
						if ( $rule['group'] === $securityGroup->getGroupName() ) {
							$sourcegroupid = $securityGroup->getGroupId();
						}
					}
				}
				$this->userNova->addSecurityGroupRule( $groupid, $fromport, $toport, $protocol, $range, $sourcegroupid );
			}
		}
		$project->editArticle();
		$this->getOutput()->addWikiMsg( 'openstackmanager-createdproject' );

		$out = '<br />';
		$out .= Linker::link(
			$this->getTitle(),
			$this->msg( 'openstackmanager-addadditionalproject' )->escaped()
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
		$success = OpenStackNovaProject::deleteProject( $formData['projectname'] );
		if ( $success ) {
			$project = OpenStackNovaProject::getProjectByName( $formData['projectname'] );
			$project->deleteArticle();
			$this->getOutput()->addWikiMsg( 'openstackmanager-deletedproject' );
		} else {
			$this->getOutput()->addWikiMsg( 'openstackmanager-deleteprojectfailed' );
		}

		$out = '<br />';
		$out .= Linker::link(
			$this->getTitle(),
			$this->msg( 'openstackmanager-backprojectlist' )->escaped()
		);
		$this->getOutput()->addHTML( $out );

		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryAddMemberSubmit( $formData, $entryPoint = 'internal' ) {
		$project = new OpenStackNovaProject( $formData['projectname'] );
		$members = explode( ',', $formData['member'] );
		foreach ( $members as $member ) {
			$user = User::newFromName( $member, 'usable' );
			if ( !$user ) {
				$this->getOutput()->addWikiMsg( 'openstackmanager-failedtoadd', $formData['member'], $formData['projectname'] );
				continue;
			}
			if ( !$user->isAllowed( 'loginviashell' ) ) {
				$this->getOutput()->addWikiMsg( 'openstackmanager-failedtoaddneedsloginright', $formData['member'], $formData['projectname'] );
				continue;
			}
			$success = $project->addMember( $member );
			if ( $success ) {
				$this->getOutput()->addWikiMsg( 'openstackmanager-addedto', $formData['member'], $formData['projectname'] );
			} else {
				$this->getOutput()->addWikiMsg( 'openstackmanager-failedtoadd', $formData['member'], $formData['projectname'] );
			}
		}

		$out = '<br />';
		$out .= Linker::link(
			$this->getTitle(),
			$this->msg( 'openstackmanager-backprojectlist' )->escaped()
		);
		$this->getOutput()->addHTML( $out );

		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryDeleteMemberSubmit( $formData, $entryPoint = 'internal' ) {
		$project = OpenStackNovaProject::getProjectByName( $formData['projectname'] );
		if ( ! $project ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-nonexistentproject' );
			return true;
		}
		foreach ( $formData['members'] as $member ) {
			$success = $project->deleteMember( $member );
			if ( $success ) {
				$this->getOutput()->addWikiMsg( 'openstackmanager-removedfrom', $member, $formData['projectname'] );
			} else {
				$this->getOutput()->addWikiMsg( 'openstackmanager-failedtoremove', $member, $formData['projectname'] );
			}
		}
		$out = '<br />';

		$out .= Linker::link(
			$this->getTitle(),
			$this->msg( 'openstackmanager-backprojectlist' )->escaped()
		);
		$this->getOutput()->addHTML( $out );

		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryConfigureProjectSubmit( $formData, $entryPoint = 'internal' ) {
		$project = OpenStackNovaProject::getProjectByName( $formData['projectname'] );
		if ( ! $project ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-nonexistentproject' );
			return true;
		}

		$vols = array();

		if ( $formData['homedirs'] ) {
			$vols[] = "home";
		}

		if ( $formData['storage'] ) {
			$vols[] = "project";
		}

		$homedirPattern = $formData['serviceuserhome'];

		if ( $project->setVolumeSettings( $vols ) && $project->setServiceGroupHomedirPattern( $homedirPattern) ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-configureproject-success' );
		} else {
			$this->getOutput()->addWikiMsg( 'openstackmanager-configureproject-failed' );
		}

		$out = Linker::link(
			$this->getTitle(),
			$this->msg( 'openstackmanager-backprojectlist' )->escaped()
		);
		$this->getOutput()->addHTML( $out );

		return true;
	}
}
