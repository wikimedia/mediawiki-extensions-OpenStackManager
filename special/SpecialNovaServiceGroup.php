<?php

/**
 * todo comment me
 *
 * @file
 * @ingroup Extensions
 */

class SpecialNovaServiceGroup extends SpecialNova {
	public $userLDAP;

	function __construct() {
		parent::__construct( 'NovaServiceGroup', 'manageproject' );

		$this->userLDAP = new OpenStackNovaUser();
	}

	function execute( $par ) {
		if ( !$this->getUser()->isLoggedIn() ) {
			$this->notLoggedIn();
			return;
		}
		$this->checkTwoFactor();
		$this->userLDAP = new OpenStackNovaUser();
		$action = $this->getRequest()->getVal( 'action' );
		if ( $action === "managemembers" ) {
			$this->manageMembers();
		} elseif ( $action === "addservicegroup" ) {
			$this->addServiceGroup();
		} elseif ( $action === "removeservicegroup" ) {
			$this->removeServiceGroup();
		} else {
			$this->listServiceGroups();
		}
	}

	/**
	 * @return bool
	 */
	function manageMembers() {
		$this->setHeaders();
		$this->getOutput()->setPagetitle( $this->msg( 'openstackmanager-manageservicegroupmembers-title' ) );

		$groupInfo = array();
		$groupName = $this->getRequest()->getText( 'servicegroupname' );
		$projectname = $this->getRequest()->getText( 'projectname' );
		$project = OpenStackNovaProject::getProjectByName( $projectname );
		if ( $project ) {
			$group = OpenStackNovaServiceGroup::getServiceGroupByName( $groupName, $project );
			if ( ! $this->userLDAP->inRole( 'projectadmin', $projectname ) &&
				( !$group->isMember( $this->userLDAP->getUsername() ) ) ) {
				# We can add a member if we're an admin or if we're already in the security group.
				$this->notInServiceGroup();
				return false;
			}
			$projectmembers = $project->getMembers();
			$groupmembers = $group->getMembers();
			$member_keys = array();
			$defaults = array();
			$servicememberDefaults = array();
			foreach ( $projectmembers as $projectmember ) {
				$member_keys[$projectmember] = $projectmember;
				if ( in_array( $projectmember, $groupmembers ) ) {
					$defaults[$projectmember] = $projectmember;
				}
			}
			$servicemembers = $project->getServiceUsers();
			foreach ( $servicemembers as $servicemember ) {
				$servicemember_keys[$servicemember] = $servicemember;
				if ( in_array( $servicemember, $groupmembers ) ) {
					$servicememberDefaults[$servicemember] = $servicemember;
				}
			}
			$groupInfo['members'] = array(
				'type' => 'multiselect',
				'label-message' => 'openstackmanager-member',
				'options' => $member_keys,
				'dropdown' => true,
				'default' => $defaults,
				'name' => 'members',
			);
			$groupInfo['servicemembers'] = array(
				'type' => 'multiselect',
				'label-message' => 'openstackmanager-serviceuser',
				'options' => $servicemember_keys,
				'dropdown' => true,
				'default' => $servicememberDefaults,
				'name' => 'servicemembers',
				'help-message' => 'openstackmanager-servicegrouprecursewarning'
			);
		} else {
			//TODO: display error
		}
		$groupInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'managemembers',
			'name' => 'action',
		);
		$groupInfo['servicegroupname'] = array(
			'type' => 'hidden',
			'default' => $groupName,
			'name' => 'servicegroupname',
		);
		$groupInfo['projectname'] = array(
			'type' => 'hidden',
			'default' => $projectname,
			'name' => 'projectname',
		);
		$groupInfo['returnto'] = array(
			'type' => 'hidden',
			'default' => $this->getRequest()->getText('returnto'),
			'name' => 'returnto',
		);

		$groupForm = new HTMLForm(
			$groupInfo,
			$this->getContext(),
			'openstackmanager-novaservicegroup'
		);
		$groupForm->setSubmitID( 'novaservicegroup-form-managememberssubmit' );
		$groupForm->setSubmitCallback( array( $this, 'tryManageMembersSubmit' ) );
		$groupForm->show();

		return true;
	}

	/**
	 * @return bool
	 */
	function addServiceGroup() {
		$this->setHeaders();
		$this->getOutput()->setPagetitle( $this->msg( 'openstackmanager-addservicegroup' ) );

		$project = $this->getRequest()->getText( 'projectname' );
		if ( !$this->userLDAP->inProject( $project ) ) {
			$this->notInProject( $project );
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

		$projectForm = new HTMLForm(
			$projectInfo,
			$this->getContext(),
			'openstackmanager-addservicegroup'
		);
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
			$this->notInRole( 'projectadmin', $project );
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
		$projectForm = new HTMLForm(
			$projectInfo,
			$this->getContext(),
			'openstackmanager-novaproject'
		);
		$projectForm->setSubmitID( 'novaproject-form-removeservicegroupsubmit' );
		$projectForm->setSubmitCallback( array( $this, 'tryRemoveServiceGroupSubmit' ) );
		$projectForm->show();

		return true;
	}

	/**
	 * @return void
	 */
	function listServiceGroups() {
		$this->setHeaders();
		$this->getOutput()->setPageTitle( $this->msg( 'openstackmanager-servicegrouplist' ) );
		$this->getOutput()->addModuleStyles( 'ext.openstack' );

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
			$actions = array( "" => array( $this->createActionLink( 'openstackmanager-addservicegroup', array( 'action' => 'addservicegroup', 'projectname' => $projectName ) ) ) );
			$out .= $this->createProjectSection( $projectName, $actions, $this->getServiceGroups( $project ) );
		}

		$this->getOutput()->addHTML( $out );
	}

	function getServiceGroups( $project ) {
		$project->fetchProjectInfo();
		$projectName = $project->getProjectName();
		$serviceGroups =  $project->getServiceGroups();
		if ( $serviceGroups ) {
			$headers = array( 'openstackmanager-servicegroupname', 'openstackmanager-members', 'openstackmanager-actions' );
			foreach ( $serviceGroups as $group) {
				$groupName = $group->getGroupName();
				$groupRow = array();
				$this->pushResourceColumn( $groupRow, $groupName );
				$this->pushRawResourceColumn( $groupRow, $this->createResourceList( $group->getMembers() ) );
				$actions = array();
				$specialGroupTitle = Title::newFromText( 'Special:NovaServiceGroup' );
				$actions[] = $this->createActionLink( 'openstackmanager-manageservicegroupmembers',
					array( 'action' => 'managemembers', 'projectname' => $projectName, 'servicegroupname' => $groupName, 'returnto' => 'Special:NovaServiceGroup' ),
					$specialGroupTitle
				);
				$actions[] = $this->createActionLink( 'openstackmanager-removeservicegroup', array( 'action' => 'removeservicegroup', 'projectname' => $projectName, 'groupname' => $groupName ) );
				$this->pushRawResourceColumn( $groupRow,  $this->createResourceList( $actions ) );
				$servicegroupRows[] = $groupRow;
			}

			return $this->createResourceTable( $headers, $servicegroupRows );
		} else {
			return "";
		}
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
			$this->getPageTitle(),
			$this->msg( 'openstackmanager-backservicegrouplist' )->escaped()
		);
		$this->getOutput()->addHTML( $out );

		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryManageMembersSubmit( $formData, $entryPoint = 'internal' ) {
		$projectname = $formData['projectname'];
		if ( $projectname ) {
			$project = OpenStackNovaProject::getProjectByName( $projectname );
			if ( ! $project ) {
				$this->getOutput()->addWikiMsg( 'openstackmanager-nonexistentproject' );
				return true;
			}
			$group = OpenStackNovaServiceGroup::getServiceGroupByName( $formData['servicegroupname'], $project );
			$members = $formData['members'];
			$servicemembers = $formData['servicemembers'];
		} else {
			//TODO: display error
		}
		if ( ! $group ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-nonexistentgroup' );
			return true;
		}
		$success = $group->setMembers( $members, $servicemembers );
		if ( $success ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-setgroupmembers', $formData['servicegroupname'] );
		} else {
			$this->getOutput()->addWikiMsg( 'openstackmanager-failedtosetgroupmembers', $formData['servicegroupname'] );
		}

		$out = '<br />';
		$returnto = Title::newFromText( $formData['returnto'] );
		if ( !$returnto ) {
			$returnto = SpecialPage::getTitleFor( 'NovaServiceGroup' );
		}
		$out .= Linker::link(
			$returnto,
			$this->msg( 'openstackmanager-backservicegrouplist' )->escaped()
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

		$success = $project->deleteServiceGroup( $formData['groupname'], $project );
		if ( $success ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-removedservicegroup' );
		} else {
			$this->getOutput()->addWikiMsg( 'openstackmanager-removeservicegroupfailed' );
		}

		$out = '<br />';
		$out .= Linker::link(
			$this->getPageTitle(),
			$this->msg( 'openstackmanager-backservicegrouplist' )->escaped()
		);
		$this->getOutput()->addHTML( $out );

		return true;
	}

	protected function getGroupName() {
		return 'nova';
	}
}
