<?php

/**
 * todo comment me
 *
 * @file
 * @ingroup Extensions
 */

class SpecialNovaServiceGroup extends SpecialNova {
	var $userLDAP;

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
		if ( $action === "addmember" ) {
			$this->addMember();
		} elseif ( $action === "deletemember" ) {
			$this->deleteMember();
		} else {
			$this->displayRestrictionError();
		}
	}

	/**
	 * @return bool
	 */
	function addMember() {
		$this->setHeaders();
		$this->getOutput()->setPagetitle( $this->msg( 'openstackmanager-addservicegroupmember' ) );

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
			foreach ( $projectmembers as $projectmember ) {
				if ( ! in_array( $projectmember, $groupmembers ) ) {
					$member_keys[$projectmember] = $projectmember;
				}
			}

			$servicemembers = $project->getServiceUsers();
			$groupName = $this->getRequest()->getText( 'servicegroupname' );
			foreach ( $servicemembers as $servicemember ) {
				if ( $servicemember != $groupName ) {
					if ( ! in_array( $servicemember, $groupmembers ) ) {
						$servicemember_keys[$servicemember] = $servicemember;
					}
				}
			}

			if ( ( ! $member_keys ) and ( ! $servicemember_keys ) ) {
				$this->getOutput()->addWikiMsg( 'openstackmanager-nomemberstoadd' );
				return true;
			}
			if ( $member_keys ) {
				$groupInfo['members'] = array(
					'type' => 'multiselect',
					'label-message' => 'openstackmanager-member',
					'options' => $member_keys,
					'name' => 'members',
				);
			}
			if ( $servicemember_keys ) {
				$groupInfo['servicemembers'] = array(
					'type' => 'multiselect',
					'label-message' => 'openstackmanager-serviceuser',
					'options' => $servicemember_keys,
					'name' => 'servicemembers',
				);
			}
		} else {
			//TODO: display error
		}
		$groupInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'addmember',
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

		$groupForm = new HTMLForm( $groupInfo, 'openstackmanager-novaservicegroup' );
		$groupForm->setTitle( SpecialPage::getTitleFor( 'NovaServiceGroup' ) );
		$groupForm->setSubmitID( 'novaservicegroup-form-addmembersubmit' );
		$groupForm->setSubmitCallback( array( $this, 'tryAddMemberSubmit' ) );
		$groupForm->show();

		return true;
	}

	/**
	 * @return bool
	 */
	function deleteMember() {
		$this->setHeaders();
		$this->getOutput()->setPagetitle( $this->msg( 'openstackmanager-removeservicegroupmember' ) );

		$groupName = $this->getRequest()->getText( 'servicegroupname' );
		$projectname = $this->getRequest()->getText( 'projectname' );
		$project = OpenStackNovaProject::getProjectByName( $projectname );
		if ( $project ) {
			$group = OpenStackNovaServiceGroup::getServiceGroupByName( $groupName, $project );
			if ( ! $this->userLDAP->inRole( 'projectadmin', $projectname ) &&
				( !$group->isMember( $this->userLDAP->getUsername() ) ) ) {
				# We can delete a member if we're an admin or if we're already in the security group.
				$this->notInServiceGroup();
				return false;
			}
			$projectmembers = $project->getMembers();
			$groupMembers = $group->getMembers();
			$member_keys = array();
			foreach ( $projectmembers as $projectmember ) {
				if ( in_array( $projectmember, $groupMembers ) ) {
					$member_keys[$projectmember] = $projectmember;
				}
			}

			$servicemembers = $project->getServiceUsers();
			foreach ( $servicemembers as $servicemember ) {
				if ( in_array( $servicemember, $groupMembers ) ) {
					$servicemember_keys[$servicemember] = $servicemember;
				}
			}

		} else {
			//TODO: display error
		}
		if ( ( ! $member_keys ) and ( ! $servicemember_keys ) ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-nomemberstoremove' );
			return true;
		}
		$groupInfo = array();
		if ( $member_keys ) {
			$groupInfo['members'] = array(
				'type' => 'multiselect',
				'label-message' => 'openstackmanager-member',
				'options' => $member_keys,
				'name' => 'members',
			);
		}
		if ( $servicemember_keys ) {
			$groupInfo['servicemembers'] = array(
				'type' => 'multiselect',
				'label-message' => 'openstackmanager-serviceuser',
				'options' => $servicemember_keys,
				'name' => 'servicemembers',
			);
		}
		$groupInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'deletemember',
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

		$groupForm = new HTMLForm( $groupInfo, 'openstackmanager-novaservicegroup' );
		$groupForm->setTitle( SpecialPage::getTitleFor( 'NovaServiceGroup' ) );
		$groupForm->setSubmitID( 'novaservicegroup-form-deletemembersubmit' );
		$groupForm->setSubmitCallback( array( $this, 'tryDeleteMemberSubmit' ) );
		$groupForm->show();

		return true;
	}

	/**
	 * @param  $formData
	 * @param string $entryPoint
	 * @return bool
	 */
	function tryAddMemberSubmit( $formData, $entryPoint = 'internal' ) {
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
		foreach ( $members as $member ) {
			$success = $group->addMember( $member );
			if ( $success ) {
				$this->getOutput()->addWikiMsg( 'openstackmanager-addedto', $member, $formData['servicegroupname'] );
			} else {
				$this->getOutput()->addWikiMsg( 'openstackmanager-failedtoadd', $member, $formData['servicegroupname'] );
			}
		}

		foreach ( $servicemembers as $servicemember ) {
			$success = $group->addServiceMember( $servicemember );
			if ( $success ) {
				$this->getOutput()->addWikiMsg( 'openstackmanager-addedto', $servicemember, $formData['servicegroupname'] );
			} else {
				$this->getOutput()->addWikiMsg( 'openstackmanager-failedtoadd', $servicemember, $formData['servicegroupname'] );
			}
		}

		$out = '<br />';
		$returnto = Title::newFromText( $formData['returnto'] );
		if ( !$returnto ) {
			$returnto = SpecialPage::getTitleFor( 'NovaProject' );
		}
		$out .= Linker::link(
			$returnto,
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
		$projectname = $formData['projectname'];
		if ( $projectname ) {
			$project = OpenStackNovaProject::getProjectByName( $projectname );
			if ( ! $project ) {
				$this->getOutput()->addWikiMsg( 'openstackmanager-nonexistentproject' );
				return true;
			}
			$group = OpenStackNovaServiceGroup::getServiceGroupByName( $formData['servicegroupname'], $project );
		} else {
			//TODO: display error
		}
		if ( ! $group ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-nonexistentservicegroup' );
			return true;
		}
		foreach ( $formData['members'] as $member ) {
			$success = $group->deleteMember( $member );
			if ( $success ) {
				$this->getOutput()->addWikiMsg( 'openstackmanager-removedfrom', $member, $formData['servicegroupname'] );
			} else {
				$this->getOutput()->addWikiMsg( 'openstackmanager-failedtoremove', $member, $formData['servicegroupname'] );
			}
		}
		foreach ( $formData['servicemembers'] as $servicemember ) {
			$success = $group->deleteServiceMember( $servicemember );
			if ( $success ) {
				$this->getOutput()->addWikiMsg( 'openstackmanager-removedfrom', $servicemember, $formData['servicegroupname'] );
			} else {
				$this->getOutput()->addWikiMsg( 'openstackmanager-failedtoremove', $servicemember, $formData['servicegroupname'] );
			}
		}

		$out = '<br />';
		$returnto = Title::newFromText( $formData['returnto'] );
		$out .= Linker::link(
			$returnto,
			$this->msg( 'openstackmanager-backprojectlist' )->escaped()
		);
		$this->getOutput()->addHTML( $out );

		return true;
	}
}
