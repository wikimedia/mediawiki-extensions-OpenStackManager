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
	public $userLDAP;

	/**
	 * @var OpenStackNovaController
	 */
	public $userNova;

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
		} elseif ( $action === "displayquotas" ) {
			$this->displayQuotas();
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

		$projectid = $this->getRequest()->getText( 'projectid' );
		$projectname = $this->getRequest()->getText( 'projectname' );
		if ( !$this->userCanExecute( $this->getUser() ) && !$this->userLDAP->inRole( 'projectadmin', $projectname ) ) {
			$this->notInRole( 'projectadmin', $projectname );
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
		$projectInfo['projectid'] = array(
			'type' => 'hidden',
			'default' => $projectid,
			'name' => 'projectid',
		);

		$projectForm = new HTMLForm(
			$projectInfo,
			$this->getContext(),
			'openstackmanager-novaproject'
		);
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

		$projectid = $this->getRequest()->getText( 'projectid' );
		$projectname = $this->getRequest()->getText( 'projectname' );
		if ( !$this->userCanExecute( $this->getUser() ) && !$this->userLDAP->inRole( 'projectadmin', $projectname ) ) {
			$this->notInRole( 'projectadmin', $projectname );
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
		$projectInfo['projectid'] = array(
			'type' => 'hidden',
			'default' => $projectid,
			'name' => 'projectid',
		);

		$projectForm = new HTMLForm(
			$projectInfo,
			$this->getContext(),
			'openstackmanager-novaproject'
		);
		$projectForm->setSubmitID( 'novaproject-form-deletemembersubmit' );
		$projectForm->setSubmitCallback( array( $this, 'tryDeleteMemberSubmit' ) );
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

		$project = $this->getRequest()->getText( 'projectid' );
		if ( ! $this->getRequest()->wasPosted() ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-removeprojectconfirm', $project );
		}
		$projectInfo = array();
		$projectInfo['projectid'] = array(
			'type' => 'hidden',
			'default' => $project,
			'name' => 'projectid',
		);
		$projectInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'delete',
			'name' => 'action',
		);
		$projectForm = new HTMLForm(
			$projectInfo,
			$this->getContext(),
			'openstackmanager-novaproject'
		);
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
		$projectId = $project->getId();
		$projectName = $project->getProjectName();
		foreach ( $project->getRoles() as $role ) {
			$roleRow = array();
			$roleName = $role->getRoleName();
			$roleId = $role->getRoleId();
			$this->pushResourceColumn( $roleRow, $roleName );
			$roleMembers = $role->getMembers();
			natcasesort( $roleMembers );
			$this->pushRawResourceColumn( $roleRow, $this->createResourceList( $roleMembers ) );
			$actions = array();
			$specialRoleTitle = Title::newFromText( 'Special:NovaRole' );
			$actions[] = $this->createActionLink( 'openstackmanager-addrolemember',
				array( 'action' => 'addmember', 'projectid' => $projectId, 'projectname' => $projectName, 'roleid' => $roleId, 'returnto' => 'Special:NovaProject' ),
				$specialRoleTitle
			);
			$actions[] = $this->createActionLink( 'openstackmanager-removerolemember',
				array( 'action' => 'deletemember', 'projectid' => $projectId, 'projectname' => $projectName, 'roleid' => $roleId, 'returnto' => 'Special:NovaProject' ),
				$specialRoleTitle
			);
			$this->pushRawResourceColumn( $roleRow,  $this->createResourceList( $actions ) );
			$roleRows[] = $roleRow;
		}
		$roleheaders = array( 'openstackmanager-rolename', 'openstackmanager-members', 'openstackmanager-actions' );
		$this->pushRawResourceColumn( $projectRow, $this->createResourceTable( $roleheaders, $roleRows ) );

		$actions = array();
		$actions[] = $this->createActionLink( 'openstackmanager-deleteproject', array( 'action' => 'delete', 'projectid' => $projectId, 'projectname' => $projectName ) );
		$actions[] = $this->createActionLink( 'openstackmanager-addmember', array( 'action' => 'addmember', 'projectid' => $projectId, 'projectname' => $projectName ) );
		$actions[] = $this->createActionLink( 'openstackmanager-removemember', array( 'action' => 'deletemember', 'projectid' => $projectId, 'projectname' => $projectName ) );
		$actions[] = $this->createActionLink( 'openstackmanager-displayquotas-action', array( 'action' => 'displayquotas', 'projectid' => $projectId, 'projectname' => $projectName ) );

		$hieraTitle = Title::makeTitleSafe( NS_HIERA, $projectName );

		$actions[] = $this->createActionLink( 'openstackmanager-hieraconfig', array(), $hieraTitle );
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
		$projectInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'create',
			'name' => 'action',
		);

		$projectForm = new HTMLForm(
			$projectInfo,
			$this->getContext(),
			'openstackmanager-novaproject'
		);
		$projectForm->setSubmitID( 'novaproject-form-createprojectsubmit' );
		$projectForm->setSubmitCallback( array( $this, 'tryCreateSubmit' ) );
		$projectForm->show();
	}

	/**
	 * @return bool
	 */
	function displayQuotas() {
		$this->setHeaders();
		$projectId = $this->getRequest()->getText( 'projectid' );
		$projectname = $this->getRequest()->getText( 'projectname' );
		$this->getOutput()->setPagetitle( $this->msg( 'openstackmanager-displayquotas', $projectId ) );
		if ( !$this->userCanExecute( $this->getUser() ) && !$this->userLDAP->inRole( 'projectadmin', $projectname ) ) {
			$this->notInRole( 'projectadmin', $projectname );
			return false;
		}
		# Change the connection to reference this project
		$this->userNova->setProject( $projectId );
		$regions = $this->userNova->getRegions( 'compute' );
		foreach ( $regions as $region ) {
			$this->userNova->setRegion( $region );
			$limits = $this->userNova->getLimits();
			$ram = $this->msg( 'openstackmanager-displayquotas-ram', $limits->getRamUsed(), $limits->getRamAvailable() );
			$floatingIps = $this->msg( 'openstackmanager-displayquotas-floatingips', $limits->getFloatingIpsUsed(), $limits->getFloatingIpsAvailable() );
			$cores = $this->msg( 'openstackmanager-displayquotas-cores', $limits->getCoresUsed(), $limits->getCoresAvailable() );
			$instances = $this->msg( 'openstackmanager-displayquotas-instances', $limits->getInstancesUsed(), $limits->getInstancesAvailable() );
			$secGroups = $this->msg( 'openstackmanager-displayquotas-securitygroups', $limits->getSecurityGroupsUsed(), $limits->getSecurityGroupsAvailable() );
			$limitsOut = Html::element( 'li', array(), $cores );
			$limitsOut .= Html::element( 'li', array(), $ram );
			$limitsOut .= Html::element( 'li', array(), $floatingIps );
			$limitsOut .= Html::element( 'li', array(), $instances );
			$limitsOut .= Html::element( 'li', array(), $secGroups );
			$limitsOut = Html::rawElement( 'ul', array(), $limitsOut );
			$limitsOut = Html::element( 'h2', array(), $region ) . $limitsOut;
			$this->getOutput()->addHTML( $limitsOut );
		}
		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryCreateSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOpenStackManagerDefaultSecurityGroupRules;

		$project = OpenStackNovaProject::createProject( $formData['projectname'] );
		if ( ! $project ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-createprojectfailed' );
			return false;
		}
		$username = $this->userLDAP->getUsername();
		$project->addMember( $username );
		$projectId = $project->getId();
		$members = explode( ',', $formData['member'] );
		foreach ( $members as $member ) {
			$project->addMember( $member );
		}
		$roles = $project->getRoles();
		foreach ( $roles as $role ) {
			foreach ( $members as $member ) {
				$role->addMember( $member );
			}
			// We also need to ensure the project creator is in all roles
			$role->addMember( $username );
		}
		# Change the connection to reference this project
		$this->userNova->setProject( $projectId );
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
			$this->getPageTitle(),
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
		$project = OpenStackNovaProject::getProjectById( $formData['projectid'] );
		$success = OpenStackNovaProject::deleteProject( $formData['projectid'] );
		if ( $success ) {
			$project->deleteArticle();
			$this->getOutput()->addWikiMsg( 'openstackmanager-deletedproject' );
		} else {
			$this->getOutput()->addWikiMsg( 'openstackmanager-deleteprojectfailed' );
		}

		$out = '<br />';
		$out .= Linker::link(
			$this->getPageTitle(),
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
		$project = new OpenStackNovaProject( $formData['projectid'] );
		$projectName = $project->getName();
		$members = explode( ',', $formData['member'] );
		foreach ( $members as $member ) {
			$user = User::newFromName( $member, 'usable' );
			if ( !$user ) {
				$this->getOutput()->addWikiMsg( 'openstackmanager-failedtoadd', $formData['member'], $projectName );
				continue;
			}
			$success = $project->addMember( $member );
			if ( $success ) {
				if ( !$user->isAllowed( 'loginviashell' ) ) {
					# Grant user the shell right if they have
					# successfully been added to a project
					$user->addGroup( 'shell' );
				}
				$this->getOutput()->addWikiMsg( 'openstackmanager-addedto', $formData['member'], $projectName );
				if ( class_exists( 'EchoEvent' ) ) {
					EchoEvent::create( array(
						'type' => 'osm-projectmembers-add',
						'title' => Title::newFromText( $projectName, NS_NOVA_RESOURCE ),
						'agent' => $this->getUser(),
						'extra' => array( 'userAdded' => $user->getId() ),
					) );
				}
			} else {
				$this->getOutput()->addWikiMsg( 'openstackmanager-failedtoadd', $formData['member'], $projectName );
			}
		}

		$out = '<br />';
		$out .= Linker::link(
			$this->getPageTitle(),
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
		$project = OpenStackNovaProject::getProjectById( $formData['projectid'] );
		$projectName = $project->getName();
		if ( ! $project ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-nonexistentproject' );
			return true;
		}
		foreach ( $formData['members'] as $member ) {
			$success = $project->deleteMember( $member );
			if ( $success ) {
				$this->getOutput()->addWikiMsg( 'openstackmanager-removedfrom', $member, $projectName );
			} else {
				$this->getOutput()->addWikiMsg( 'openstackmanager-failedtoremove', $member, $projectName );
			}
		}
		$out = '<br />';

		$out .= Linker::link(
			$this->getPageTitle(),
			$this->msg( 'openstackmanager-backprojectlist' )->escaped()
		);
		$this->getOutput()->addHTML( $out );

		return true;
	}

	protected function getGroupName() {
		return 'nova';
	}
}
