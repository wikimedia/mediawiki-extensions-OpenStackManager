<?php

/**
 * todo comment me
 *
 * @file
 * @ingroup Extensions
 */

class SpecialNovaRole extends SpecialNova {

	var $userLDAP;

	function __construct() {
		parent::__construct( 'NovaRole', 'manageproject' );

		$this->userLDAP = new OpenStackNovaUser();
	}

	function execute( $par ) {
		if ( !$this->getUser()->isLoggedIn() ) {
			$this->notLoggedIn();
			return;
		}
		$this->userLDAP = new OpenStackNovaUser();
		$action = $this->getRequest()->getVal( 'action' );
		if ( $action === "addmember" ) {
			$this->addMember();
		} elseif ( $action === "deletemember" ) {
			$this->deleteMember();
		} else {
			$this->displayRestrictionError();
			return false;
		}
	}

	/**
	 * @return bool
	 */
	function addMember() {
		$this->setHeaders();
		$this->getOutput()->setPagetitle( wfMsg( 'openstackmanager-addmember' ) );

		$roleInfo = array();
		$rolename = $this->getRequest()->getText( 'rolename' );
		$projectname = $this->getRequest()->getText( 'projectname' );
		if ( $projectname ) {
			if ( !$this->userCanExecute( $this->getUser() ) && !$this->userLDAP->inRole( $rolename, $projectname, true ) ) {
				$this->displayRestrictionError();
				return false;
			}
			$project = OpenStackNovaProject::getProjectByName( $projectname );
			$projectmembers = $project->getMembers();
			$role = OpenStackNovaRole::getProjectRoleByName( $rolename, $project );
			$rolemembers = $role->getMembers();
			$member_keys = array();
			foreach ( $projectmembers as $projectmember ) {
				if ( ! in_array( $projectmember, $rolemembers ) ) {
					$member_keys[$projectmember] = $projectmember;
				}
			}
			if ( ! $member_keys ) {
				$this->getOutput()->addWikiMsg( 'openstackmanager-nomemberstoadd' );
				return true;
			}
			$roleInfo['members'] = array(
				'type' => 'multiselect',
				'label-message' => 'openstackmanager-member',
				'options' => $member_keys,
				'section' => 'role/info',
				'name' => 'members',
			);
		} else {
			//TODO: display error
		}
		$roleInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'addmember',
			'name' => 'action',
		);
		$roleInfo['rolename'] = array(
			'type' => 'hidden',
			'default' => $rolename,
			'name' => 'rolename',
		);
		$roleInfo['projectname'] = array(
			'type' => 'hidden',
			'default' => $projectname,
			'name' => 'projectname',
		);
		$roleInfo['returnto'] = array(
			'type' => 'hidden',
			'default' => $this->getRequest()->getText('returnto'),
			'name' => 'returnto',
		);

		$roleForm = new HTMLForm( $roleInfo, 'openstackmanager-novarole' );
		$roleForm->setTitle( SpecialPage::getTitleFor( 'NovaRole' ) );
		$roleForm->setSubmitID( 'novarole-form-addmembersubmit' );
		$roleForm->setSubmitCallback( array( $this, 'tryAddMemberSubmit' ) );
		$roleForm->show();

		return true;
	}

	/**
	 * @return bool
	 */
	function deleteMember() {
		$this->setHeaders();
		$this->getOutput()->setPagetitle( wfMsg( 'openstackmanager-removerolemember' ) );

		$rolename = $this->getRequest()->getText( 'rolename' );
		$projectname = $this->getRequest()->getText( 'projectname' );
		if ( $projectname ) {
			if ( !$this->userCanExecute( $this->getUser() ) && !$this->userLDAP->inRole( $rolename, $projectname, true ) ) {
				$this->displayRestrictionError();
				return false;
			}
			$project = OpenStackNovaProject::getProjectByName( $projectname );
			$projectmembers = $project->getMembers();
			$role = OpenStackNovaRole::getProjectRoleByName( $rolename, $project );
			$rolemembers = $role->getMembers();
			$member_keys = array();
			foreach ( $projectmembers as $projectmember ) {
				if ( in_array( $projectmember, $rolemembers ) ) {
					$member_keys[$projectmember] = $projectmember;
				}
			}
		} else {
			//TODO: display error
		}
		if ( ! $member_keys ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-nomemberstoremove' );
			return true;
		}
		$roleInfo = array();
		$roleInfo['members'] = array(
			'type' => 'multiselect',
			'label-message' => 'openstackmanager-member',
			'options' => $member_keys,
			'section' => 'role/info',
			'name' => 'members',
		);
		$roleInfo['action'] = array(
			'type' => 'hidden',
			'default' => 'deletemember',
			'name' => 'action',
		);
		$roleInfo['rolename'] = array(
			'type' => 'hidden',
			'default' => $rolename,
			'name' => 'rolename',
		);
		$roleInfo['projectname'] = array(
			'type' => 'hidden',
			'default' => $projectname,
			'name' => 'projectname',
		);
		$roleInfo['returnto'] = array(
			'type' => 'hidden',
			'default' => $this->getRequest()->getText('returnto'),
			'name' => 'returnto',
		);

		$roleForm = new HTMLForm( $roleInfo, 'openstackmanager-novarole' );
		$roleForm->setTitle( SpecialPage::getTitleFor( 'NovaRole' ) );
		$roleForm->setSubmitID( 'novarole-form-deletemembersubmit' );
		$roleForm->setSubmitCallback( array( $this, 'tryDeleteMemberSubmit' ) );
		$roleForm->show();

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
			$role = OpenStackNovaRole::getProjectRoleByName( $formData['rolename'], $project );
			$members = $formData['members'];
		} else {
			//TODO: display error
		}
		if ( ! $role ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-nonexistentrole' );
			return true;
		}
		foreach ( $members as $member ) {
			$success = $role->addMember( $member );
			if ( $success ) {
				$this->getOutput()->addWikiMsg( 'openstackmanager-addedto', $member, $formData['rolename'] );
			} else {
				$this->getOutput()->addWikiMsg( 'openstackmanager-failedtoadd', $member, $formData['rolename'] );
			}
		}

		$out = '<br />';
		$returnto = Title::newFromText( $formData['returnto'] );
		$out .= Linker::link( $returnto, wfMsgHtml( 'openstackmanager-backprojectlist' ) );
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
			$role = OpenStackNovaRole::getProjectRoleByName( $formData['rolename'], $project );
		} else {
			//TODO: display error
		}
		if ( ! $role ) {
			$this->getOutput()->addWikiMsg( 'openstackmanager-nonexistentrole' );
			return true;
		}
		foreach ( $formData['members'] as $member ) {
			$success = $role->deleteMember( $member );
			if ( $success ) {
				$this->getOutput()->addWikiMsg( 'openstackmanager-removedfrom', $member, $formData['rolename'] );
			} else {
				$this->getOutput()->addWikiMsg( 'openstackmanager-failedtoremove', $member, $formData['rolename'] );
			}
		}

		$out = '<br />';
		$returnto = Title::newFromText( $formData['returnto'] );
		$out .= Linker::link( $returnto, wfMsgHtml( 'openstackmanager-backprojectlist' ) );
		$this->getOutput()->addHTML( $out );

		return true;
	}
}
