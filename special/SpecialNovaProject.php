<?php
class SpecialNovaProject extends SpecialNova {

	var $adminNova;
	var $userLDAP;

	function __construct() {
		parent::__construct( 'NovaProject', 'manageproject' );

		global $wgOpenStackManagerNovaAdminKeys;

		$this->userLDAP = new OpenStackNovaUser();
		$adminCredentials = $wgOpenStackManagerNovaAdminKeys;
		$this->adminNova = new OpenStackNovaController( $adminCredentials );
	}

	function execute( $par ) {
		global $wgRequest, $wgUser;

		if ( !$wgUser->isLoggedIn() ) {
			$this->notLoggedIn();
			return;
		}
		$this->userLDAP = new OpenStackNovaUser();
		$action = $wgRequest->getVal( 'action' );
		if ( $action == "create" ) {
			$this->createProject();
		} elseif ( $action == "delete" ) {
			$this->deleteProject();
		} elseif ( $action == "addmember" ) {
			$this->addMember();
		} elseif ( $action == "deletemember" ) {
			$this->deleteMember();
		} else {
			$this->listProjects();
		}
	}

	/**
	 * @return bool
	 */
	function createProject() {
		global $wgOut, $wgUser;

		$this->setHeaders();
		if ( !$this->userCanExecute( $wgUser ) ) {
			$this->displayRestrictionError();
			return false;
		}
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-createproject' ) );

		$projectInfo = array();
		$projectInfo['projectname'] = array(
			'type' => 'text',
			'label-message' => 'openstackmanager-projectname',
			'validation-callback' => array( $this, 'validateText' ),
			'default' => '',
			'section' => 'project/info',
			'name' => 'projectname',
		);
		$projectInfo['member'] = array(
			'type' => 'text',
			'label-message' => 'openstackmanager-member',
			'default' => '',
			'section' => 'project/membership',
			'name' => 'member',
		);
		$role_keys = array();
		foreach ( OpenStackNovaProject::$rolenames as $rolename ) {
			$role_keys["$rolename"] = $rolename;
		}
		$projectInfo['roles'] = array(
			'type' => 'multiselect',
			'label-message' => 'openstackmanager-roles',
			'section' => 'project/membership',
			'options' => $role_keys,
			'name' => 'roles',
		);

		$projectInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'create',
			'name' => 'action',
		);

		$projectForm = new SpecialNovaProjectForm( $projectInfo, 'openstackmanager-novaproject' );
		$projectForm->setTitle( SpecialPage::getTitleFor( 'NovaProject' ) );
		$projectForm->setSubmitID( 'novaproject-form-createprojectsubmit' );
		$projectForm->setSubmitCallback( array( $this, 'tryCreateSubmit' ) );
		$projectForm->show();

		return true;
	}

	/**
	 * @return bool
	 */
	function addMember() {
		global $wgRequest, $wgOut;
		global $wgUser;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-addmember' ) );

		$project = $wgRequest->getText( 'projectname' );
		if ( !$this->userCanExecute( $wgUser ) && !$this->userLDAP->inProject( $project ) ) {
			$this->notInProject();
			return false;
		}
		$projectInfo = array();
		$projectInfo['member'] = array(
			'type' => 'text',
			'label-message' => 'openstackmanager-member',
			'default' => '',
			'section' => 'project/info',
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

		$projectForm = new SpecialNovaProjectForm( $projectInfo, 'openstackmanager-novaproject' );
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
		global $wgRequest, $wgOut;
		global $wgUser;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-removemember' ) );

		$projectname = $wgRequest->getText( 'projectname' );
		if ( !$this->userCanExecute( $wgUser ) && !$this->userLDAP->inProject( $projectname ) ) {
			$this->notInProject();
			return false;
		}
		$project = OpenStackNovaProject::getProjectByName( $projectname );
		$projectmembers = $project->getMembers();
		$member_keys = array();
		foreach ( $projectmembers as $projectmember ) {
			$member_keys["$projectmember"] = $projectmember;
		}
		$projectInfo = array();
		$projectInfo['members'] = array(
			'type' => 'multiselect',
			'label-message' => 'openstackmanager-member',
			'section' => 'project/info',
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

		$projectForm = new SpecialNovaProjectForm( $projectInfo, 'openstackmanager-novaproject' );
		$projectForm->setTitle( SpecialPage::getTitleFor( 'NovaProject' ) );
		$projectForm->setSubmitID( 'novaproject-form-deletemembersubmit' );
		$projectForm->setSubmitCallback( array( $this, 'tryDeleteMemberSubmit' ) );
		$projectForm->show();

		return true;
	}

	/**
	 * @return bool
	 */
	function deleteProject() {
		global $wgOut, $wgRequest;
		global $wgUser;

		$this->setHeaders();
		if ( !$this->userCanExecute( $wgUser ) ) {
			$this->displayRestrictionError();
			return false;
		}
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-deleteproject' ) );

		$project = $wgRequest->getText( 'projectname' );
		if ( ! $wgRequest->wasPosted() ) {
			$wgOut->addWikiMsg( 'openstackmanager-removeprojectconfirm', $project );
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
		$projectForm = new SpecialNovaProjectForm( $projectInfo, 'openstackmanager-novaproject' );
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
		global $wgOut;

		$this->setHeaders();
		$wgOut->setPagetitle( wfMsg( 'openstackmanager-projectlist' ) );
		$wgOut->addModuleStyles( 'ext.openstack' );

		$out = '';

		$out .= Linker::( $this->getTitle(), wfMsgHtml( 'openstackmanager-createproject' ), array(), array( 'action' => 'create' ) );
		$projectsOut = Html::element( 'th', array(), wfMsg( 'openstackmanager-projectname' ) );
		$projectsOut .= Html::element( 'th', array(),  wfMsg( 'openstackmanager-members' ) );
		$projectsOut .= Html::element( 'th', array(),  wfMsg( 'openstackmanager-roles' ) );
		$projectsOut .= Html::element( 'th', array(), wfMsg( 'openstackmanager-actions' ) );
		$projects = OpenStackNovaProject::getAllProjects();
		if ( ! $projects ) {
			$projectsOut = '';
		}
		foreach ( $projects as $project ) {
			$projectName = $project->getProjectName();
			$projectName = htmlentities( $projectName );
			$title = Title::newFromText( $projectName, NS_NOVA_RESOURCE );
			$projectNameLink = Linker::( $title, $projectName );
			$projectOut = Html::rawElement( 'td', array(), $projectNameLink );
			$projectMembers = $project->getMembers();
			$memberOut = '';
			foreach ( $projectMembers as $projectMember ) {
				$memberOut .= Html::element( 'li', array(), $projectMember );
			}
			if ( $memberOut ) {
				$memberOut = Html::rawElement( 'ul', array(), $memberOut );
			}
			$projectOut .= Html::rawElement( 'td', array(), $memberOut );
			$rolesOut = Html::element( 'th', array(), wfMsg( 'openstackmanager-rolename' ) );
			$rolesOut .= Html::element( 'th', array(),  wfMsg( 'openstackmanager-members' ) );
			$rolesOut .= Html::element( 'th', array(), wfMsg( 'openstackmanager-actions' ) );
			foreach ( $project->getRoles() as $role ) {
				$roleOut = Html::element( 'td', array(), $role->getRoleName() );
				$roleMembers = '';
				$specialRoleTitle = Title::newFromText( 'Special:NovaRole' );
				foreach ( $role->getMembers() as $member ) {
					$roleMembers .= Html::element( 'li', array(), $member );
				}
				$roleMembers = Html::rawElement( 'ul', array(), $roleMembers );
				$roleOut .= Html::rawElement( 'td', array(), $roleMembers );
				$link = Linker::( $specialRoleTitle, wfMsgHtml( 'openstackmanager-addrolemember' ), array(),
								   array( 'action' => 'addmember', 'projectname' => $projectName, 'rolename' => $role->getRoleName(), 'returnto' => 'Special:NovaProject' ) );
				$actions = Html::rawElement( 'li', array(), $link );
				$link = Linker::( $specialRoleTitle, wfMsgHtml( 'openstackmanager-removerolemember' ), array(),
								   array( 'action' => 'deletemember', 'projectname' => $projectName, 'rolename' => $role->getRoleName(), 'returnto' => 'Special:NovaProject' ) );
				$actions .= Html::rawElement( 'li', array(), $link );
				$actions = Html::rawElement( 'ul', array(), $actions );
				$roleOut .= Html::rawElement( 'td', array(), $actions );
				$rolesOut .= Html::rawElement( 'tr', array(), $roleOut );
			}
			$rolesOut = Html::rawElement( 'table', array( 'class' => 'wikitable sortable collapsible' ), $rolesOut );
			$projectOut .= Html::rawElement( 'td', array( 'class' => 'Nova_cell' ), $rolesOut );
			$link = Linker::( $this->getTitle(), wfMsgHtml( 'openstackmanager-deleteproject' ), array(),
							   array( 'action' => 'delete', 'projectname' => $projectName ) );
			$actions = Html::rawElement( 'li', array(), $link );
			$link = Linker::( $this->getTitle(), wfMsgHtml( 'openstackmanager-addmember' ), array(),
									 array( 'action' => 'addmember', 'projectname' => $projectName ) );
			$actions .= Html::rawElement( 'li', array(), $link );
			$link = Linker::( $this->getTitle(), wfMsgHtml( 'openstackmanager-removemember' ), array(),
							   array( 'action' => 'deletemember', 'projectname' => $projectName ) );
			$actions .= Html::rawElement( 'li', array(), $link );
			$actions = Html::rawElement( 'ul', array(), $actions );
			$projectOut .= Html::rawElement( 'td', array(), $actions );
			$projectsOut .= Html::rawElement( 'tr', array(), $projectOut );
		}
		if ( $projectsOut ) {
			$out .= Html::rawElement( 'table', array( 'class' => 'wikitable sortable collapsible' ), $projectsOut );
		}

		$wgOut->addHTML( $out );
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryCreateSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut;
		global $wgOpenStackManagerDefaultSecurityGroupRules;

		$success = OpenStackNovaProject::createProject( $formData['projectname'] );
		if ( ! $success ) {
			$wgOut->addWikiMsg( 'openstackmanager-createprojectfailed' );
			return true;
		}
		$project = OpenStackNovaProject::getProjectByName( $formData['projectname'] );
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
		}
		# Create a default security group for this project, and add configured default rules
		$groupname = 'default';
		# Change the connection to reference this project
		$this->adminNova->configureConnection( $formData['projectname'] );
		$this->adminNova->createSecurityGroup( $groupname, '' );
		foreach ( $wgOpenStackManagerDefaultSecurityGroupRules as $rule ) {
			$fromport = '';
			$toport = '';
			$protocol = '';
			$ranges = array();
			$groups = array();
			if ( array_key_exists( 'fromport', $rule ) ) {
				$fromport = $rule['fromport'];
			}
			if ( array_key_exists( 'toport', $rule ) ) {
				$toport = $rule['toport'];
			}
			if ( array_key_exists( 'protocol', $rule ) ) {
				$protocol = $rule['protocol'];
			}
			if ( array_key_exists( 'ranges', $rule ) ) {
				$ranges = $rule['ranges'];
			}
			if ( array_key_exists( 'groups', $rule ) ) {
				foreach ( $rule['groups'] as $group ) {
					if ( !array_key_exists( 'groupname', $group ) ) {
						# TODO: log an error here
						continue;
					}
					if ( array_key_exists( 'project', $group ) ) {
						$groupproject = $group['project'];
					} else {
						# Assume groups with no project defined are
						# referencing this project's group
						$groupproject = $formData['projectname'];
					}
					$groups[] = array( 'groupname' => $group['groupname'], 'project' => $groupproject );
				}
			}
			$this->adminNova->addSecurityGroupRule( $groupname, $fromport, $toport, $protocol, $ranges, $groups );
		}
		# Reset connection to default
		$this->adminNova->configureConnection();
		$project->editArticle();
		$wgOut->addWikiMsg( 'openstackmanager-createdproject' );

		$out = '<br />';
		$out .= Linker::( $this->getTitle(), wfMsgHtml( 'openstackmanager-backprojectlist' ) );
		$wgOut->addHTML( $out );

		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryDeleteSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut;

		$success = OpenStackNovaProject::deleteProject( $formData['projectname'] );
		if ( $success ) {
			$project = OpenStackNovaProject::getProjectByName( $formData['projectname'] );
			$project->deleteArticle();
			$wgOut->addWikiMsg( 'openstackmanager-deletedproject' );
		} else {
			$wgOut->addWikiMsg( 'openstackmanager-deleteprojectfailed' );
		}

		$out = '<br />';
		$out .= Linker::( $this->getTitle(), wfMsgHtml( 'openstackmanager-backprojectlist' ) );
		$wgOut->addHTML( $out );

		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryAddMemberSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut;

		$project = new OpenStackNovaProject( $formData['projectname'] );
		$members = explode( ',', $formData['member'] );
		foreach ( $members as $member ) {
			$success = $project->addMember( $member );
			if ( $success ) {
				$project->editArticle();
				$wgOut->addWikiMsg( 'openstackmanager-addedto', $formData['member'], $formData['projectname'] );
			} else {
				$wgOut->addWikiMsg( 'openstackmanager-failedtoadd', $formData['member'], $formData['projectname'] );
			}
		}

		$out = '<br />';
		$out .= Linker::( $this->getTitle(), wfMsgHtml( 'openstackmanager-backprojectlist' ) );
		$wgOut->addHTML( $out );

		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryDeleteMemberSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOut;

		$project = OpenStackNovaProject::getProjectByName( $formData['projectname'] );
		if ( ! $project ) {
			$wgOut->addWikiMsg( 'openstackmanager-nonexistentproject' );
			return true;
		}
		foreach ( $formData['members'] as $member ) {
			$success = $project->deleteMember( $member );
			if ( $success ) {
				$project->editArticle();
				$wgOut->addWikiMsg( 'openstackmanager-removedfrom', $member, $formData['projectname'] );
			} else {
				$wgOut->addWikiMsg( 'openstackmanager-failedtoremove', $member, $formData['projectname'] );
			}
		}
		$out = '<br />';

		$out .= Linker::( $this->getTitle(), wfMsgHtml( 'openstackmanager-backprojectlist' ) );
		$wgOut->addHTML( $out );

		return true;
	}

}

class SpecialNovaProjectForm extends HTMLForm {
}
