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
		if ( !$this->userLDAP->exists() ) {
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
		$this->getOutput()->setPageTitle( $this->msg( 'openstackmanager-addmember' ) );

		$projectid = $this->getRequest()->getText( 'projectid' );
		$project = new OpenStackNovaProject( $projectid );
		$projectname = $project->getProjectName();
		if ( !$this->userCanExecute( $this->getUser() ) &&
			!$this->userLDAP->inRole( 'projectadmin', $projectname )
		) {
			$this->notInRole( 'projectadmin', $projectname );
			return false;
		}
		$projectInfo = [];
		$projectInfo['member'] = [
			'type' => 'text',
			'label-message' => 'openstackmanager-member',
			'default' => '',
			'name' => 'member',
		];
		$projectInfo['action'] = [
			'type' => 'hidden',
			'default' => 'addmember',
			'name' => 'action',
		];
		$projectInfo['projectid'] = [
			'type' => 'hidden',
			'default' => $projectid,
			'name' => 'projectid',
		];

		$projectForm = new HTMLForm(
			$projectInfo,
			$this->getContext(),
			'openstackmanager-novaproject'
		);
		$projectForm->setSubmitID( 'novaproject-form-addmembersubmit' );
		$projectForm->setSubmitCallback( [ $this, 'tryAddMemberSubmit' ] );
		$projectForm->show();

		return true;
	}

	/**
	 * @return bool
	 */
	function deleteMember() {
		$this->setHeaders();
		$this->getOutput()->setPageTitle( $this->msg( 'openstackmanager-removemember' ) );

		$projectid = $this->getRequest()->getText( 'projectid' );
		$project = new OpenStackNovaProject( $projectid );
		$projectname = $project->getProjectName();
		if ( !$this->userCanExecute( $this->getUser() ) &&
			!$this->userLDAP->inRole( 'projectadmin', $projectname )
		) {
			$this->notInRole( 'projectadmin', $projectname );
			return false;
		}
		$project = OpenStackNovaProject::getProjectByName( $projectname );
		$projectmembers = $project->getMembers();
		$member_keys = [];
		foreach ( $projectmembers as $projectmember ) {
			$member_keys[$projectmember] = $projectmember;
		}
		$projectInfo = [];
		$projectInfo['members'] = [
			'type' => 'multiselect',
			'label-message' => 'openstackmanager-member',
			'options' => $member_keys,
			'name' => 'members',
		];
		$projectInfo['action'] = [
			'type' => 'hidden',
			'default' => 'deletemember',
			'name' => 'action',
		];
		$projectInfo['projectid'] = [
			'type' => 'hidden',
			'default' => $projectid,
			'name' => 'projectid',
		];

		$projectForm = new HTMLForm(
			$projectInfo,
			$this->getContext(),
			'openstackmanager-novaproject'
		);
		$projectForm->setSubmitID( 'novaproject-form-deletemembersubmit' );
		$projectForm->setSubmitCallback( [ $this, 'tryDeleteMemberSubmit' ] );
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
		$this->getOutput()->setPageTitle( $this->msg( 'openstackmanager-deleteproject' ) );

		$project = $this->getRequest()->getText( 'projectid' );
		if ( !$this->getRequest()->wasPosted() ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-removeprojectconfirm', $project );
		}
		$projectInfo = [];
		$projectInfo['projectid'] = [
			'type' => 'hidden',
			'default' => $project,
			'name' => 'projectid',
		];
		$projectInfo['action'] = [
			'type' => 'hidden',
			'default' => 'delete',
			'name' => 'action',
		];
		$projectForm = new HTMLForm(
			$projectInfo,
			$this->getContext(),
			'openstackmanager-novaproject'
		);
		$projectForm->setSubmitID( 'novaproject-form-deleteprojectsubmit' );
		$projectForm->setSubmitCallback( [ $this, 'tryDeleteSubmit' ] );
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
			$actions = [];
			$out .= $this->createProjectSection(
				$projectName, $actions, $this->getProject( $project )
			);
		}

		$this->getOutput()->addHTML( $out );
	}

	function getProject( $project ) {
		$project->fetchProjectInfo();
		$headers = [
			'openstackmanager-members',
			'openstackmanager-roles',
			'openstackmanager-actions'
		];
		$projectRows = [];
		$projectRow = [];
		$this->pushRawResourceColumn(
			$projectRow, $this->createResourceList( $project->getMembers() )
		);
		$roleRows = [];
		$projectId = $project->getId();
		$projectName = $project->getProjectName();
		foreach ( $project->getRoles() as $role ) {
			$roleRow = [];
			$roleName = $role->getRoleName();
			$roleId = $role->getRoleId();
			$this->pushResourceColumn( $roleRow, $roleName );
			$roleMembers = $role->getMembers();
			natcasesort( $roleMembers );
			$this->pushRawResourceColumn( $roleRow, $this->createResourceList( $roleMembers ) );
			$actions = [];
			$specialRoleTitle = Title::newFromText( 'Special:NovaRole' );
			$actions[] = $this->createActionLink( 'openstackmanager-addrolemember',
				[
					'action' => 'addmember',
					'projectid' => $projectId,
					'roleid' => $roleId,
					'returnto' => 'Special:NovaProject'
				],
				$specialRoleTitle
			);
			$actions[] = $this->createActionLink( 'openstackmanager-removerolemember',
				[
					'action' => 'deletemember',
					'projectid' => $projectId,
					'roleid' => $roleId,
					'returnto' => 'Special:NovaProject'
				],
				$specialRoleTitle
			);
			$this->pushRawResourceColumn( $roleRow,  $this->createResourceList( $actions ) );
			$roleRows[] = $roleRow;
		}
		$roleheaders = [
			'openstackmanager-rolename',
			'openstackmanager-members',
			'openstackmanager-actions'
		];
		$this->pushRawResourceColumn(
			$projectRow, $this->createResourceTable( $roleheaders, $roleRows )
		);

		$actions = [];
		$actions[] = $this->createActionLink(
			'openstackmanager-deleteproject',
			[ 'action' => 'delete', 'projectid' => $projectId ]
		);
		$actions[] = $this->createActionLink(
			'openstackmanager-addmember',
			[ 'action' => 'addmember', 'projectid' => $projectId ]
		);
		$actions[] = $this->createActionLink(
			'openstackmanager-removemember',
			[ 'action' => 'deletemember', 'projectid' => $projectId ]
		);
		$actions[] = $this->createActionLink(
			'openstackmanager-displayquotas-action',
			[ 'action' => 'displayquotas', 'projectid' => $projectId ]
		);

		$hieraTitle = Title::makeTitleSafe( NS_HIERA, $projectName );

		$actions[] = $this->createActionLink( 'openstackmanager-hieraconfig', [], $hieraTitle );
		$this->pushRawResourceColumn( $projectRow,  $this->createResourceList( $actions ) );
		$projectRows[] = $projectRow;
		return $this->createResourceTable( $headers, $projectRows );
	}

	function showCreateProject() {
		global $wgRequest;

		if ( $wgRequest->wasPosted() && $wgRequest->getVal( 'action' ) !== 'create' ) {
			return null;
		}
		$projectInfo = [];
		$projectInfo['projectname'] = [
			'type' => 'text',
			'label-message' => 'openstackmanager-projectname',
			'validation-callback' => [ $this, 'validateText' ],
			'default' => '',
			'section' => 'project',
			'name' => 'projectname',
		];
		$projectInfo['member'] = [
			'type' => 'text',
			'label-message' => 'openstackmanager-member',
			'default' => '',
			'section' => 'project',
			'name' => 'member',
		];
		$projectInfo['action'] = [
			'type' => 'hidden',
			'default' => 'create',
			'name' => 'action',
		];

		$projectForm = new HTMLForm(
			$projectInfo,
			$this->getContext(),
			'openstackmanager-novaproject'
		);
		$projectForm->setSubmitID( 'novaproject-form-createprojectsubmit' );
		$projectForm->setSubmitCallback( [ $this, 'tryCreateSubmit' ] );
		$projectForm->show();
	}

	/**
	 * @return bool
	 */
	function displayQuotas() {
		$this->setHeaders();
		$projectId = $this->getRequest()->getText( 'projectid' );
		$project = new OpenStackNovaProject( $projectId );
		$projectname = $project->getProjectName();
		$this->getOutput()->setPageTitle(
			$this->msg( 'openstackmanager-displayquotas', $projectId )
		);
		if ( !$this->userCanExecute( $this->getUser() ) &&
			!$this->userLDAP->inRole( 'projectadmin', $projectname )
		) {
			$this->notInRole( 'projectadmin', $projectname );
			return false;
		}
		# Change the connection to reference this project
		$this->userNova->setProject( $projectId );
		$regions = $this->userNova->getRegions( 'compute' );
		foreach ( $regions as $region ) {
			$this->userNova->setRegion( $region );
			$limits = $this->userNova->getLimits();
			$ram = $this->msg(
				'openstackmanager-displayquotas-ram',
				$limits->getRamUsed(),
				$limits->getRamAvailable()
			);
			$floatingIps = $this->msg(
				'openstackmanager-displayquotas-floatingips',
				$limits->getFloatingIpsUsed(),
				$limits->getFloatingIpsAvailable()
			);
			$cores = $this->msg(
				'openstackmanager-displayquotas-cores',
				$limits->getCoresUsed(),
				$limits->getCoresAvailable()
			);
			$instances = $this->msg(
				'openstackmanager-displayquotas-instances',
				$limits->getInstancesUsed(),
				$limits->getInstancesAvailable()
			);
			$secGroups = $this->msg(
				'openstackmanager-displayquotas-securitygroups',
				$limits->getSecurityGroupsUsed(),
				$limits->getSecurityGroupsAvailable()
			);
			$limitsOut = Html::element( 'li', [], $cores );
			$limitsOut .= Html::element( 'li', [], $ram );
			$limitsOut .= Html::element( 'li', [], $floatingIps );
			$limitsOut .= Html::element( 'li', [], $instances );
			$limitsOut .= Html::element( 'li', [], $secGroups );
			$limitsOut = Html::rawElement( 'ul', [], $limitsOut );
			$limitsOut = Html::element( 'h2', [], $region ) . $limitsOut;
			$this->getOutput()->addHTML( $limitsOut );
		}
		return true;
	}

	/**
	 * @param array $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryCreateSubmit( $formData, $entryPoint = 'internal' ) {
		global $wgOpenStackManagerDefaultSecurityGroupRules;
		global $wgOpenStackManagerLDAPUsername;

		$project = OpenStackNovaProject::createProject( $formData['projectname'] );
		if ( !$project ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-createprojectfailed' );
			return false;
		}
		# Add project creator and novaadmin to the new project.
		$username = $this->userLDAP->getUsername();
		$project->addMember( $username );
		$project->addMember( $wgOpenStackManagerLDAPUsername );
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
			$role->addMember( $wgOpenStackManagerLDAPUsername );
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
				$this->userNova->addSecurityGroupRule(
					$groupid, $fromport, $toport, $protocol, $range, $sourcegroupid
				);
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
	 * @param array $formData
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
	 * @param array $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryAddMemberSubmit( $formData, $entryPoint = 'internal' ) {
		$project = new OpenStackNovaProject( $formData['projectid'] );
		$projectName = $project->getName();
		$members = explode( ',', $formData['member'] );
		$out = $this->getOutput();
		foreach ( $members as $member ) {
			$user = User::newFromName( $member, 'usable' );
			if ( !$user ) {
				$out->addWikiMsg( 'openstackmanager-failedtoadd', $member, $projectName );
				continue;
			}
			$success = $project->addMember( $member );
			if ( $success ) {
				if ( !$user->isAllowed( 'loginviashell' ) ) {
					# Grant user the shell right if they have
					# successfully been added to a project
					$user->addGroup( 'shell' );
				}
				$out->addWikiMsg( 'openstackmanager-addedto', $member, $projectName );
				if ( ExtensionRegistry::getInstance()->isLoaded( 'Echo' ) ) {
					EchoEvent::create( [
						'type' => 'osm-projectmembers-add',
						'title' => Title::newFromText( $projectName, NS_NOVA_RESOURCE ),
						'agent' => $this->getUser(),
						'extra' => [ 'userAdded' => $user->getId() ],
					] );
				}
			} else {
				$out->addWikiMsg( 'openstackmanager-failedtoadd', $member, $projectName );
			}
		}

		$outHtml = '<br />';
		$outHtml .= Linker::link(
			$this->getPageTitle(),
			$this->msg( 'openstackmanager-backprojectlist' )->escaped()
		);
		$out->addHTML( $outHtml );

		return true;
	}

	/**
	 * @param array $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryDeleteMemberSubmit( $formData, $entryPoint = 'internal' ) {
		$project = OpenStackNovaProject::getProjectById( $formData['projectid'] );
		$projectName = $project->getName();
		$out = $this->getOutput();
		if ( !$project ) {
			$out->addWikiMsg( 'openstackmanager-nonexistentproject' );
			return true;
		}
		foreach ( $formData['members'] as $member ) {
			$success = $project->deleteMember( $member );
			if ( $success ) {
				$out->addWikiMsg( 'openstackmanager-removedfrom', $member, $projectName );
			} else {
				$out->addWikiMsg( 'openstackmanager-failedtoremove', $member, $projectName );
			}
		}
		$outHtml = '<br />';

		$outHtml .= Linker::link(
			$this->getPageTitle(),
			$this->msg( 'openstackmanager-backprojectlist' )->escaped()
		);
		$out->addHTML( $outHtml );

		return true;
	}

	protected function getGroupName() {
		return 'nova';
	}
}
