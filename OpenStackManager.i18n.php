<?php
/**
 * Internationalisation file for extension OpenStackManager
 *
 * @file
 * @ingroup Extensions
 * @author Ryan Lane <rlane@wikimedia.org>
 * @copyright © 2010 Ryan Lane
 * @license GNU General Public Licence 2.0 or later
 */

$messages = array();

/** English
 * @author Ryan Lane <rlane@wikimedia.org>
 */
$messages['en'] = array(
	'openstackmanager-desc' => 'Lets users manage nova and swift',

	'openstackmanager' => 'OpenStackManager',
	'openstackmanager-instance' => 'Manage Instance',
	'openstackmanager-title' => 'OpenStackManager',

	'openstackmanager' => 'Not logged in',
	'openstackmanager' => '<p>You must be logged in to perform this action</p>',
    'openstackmanager' => 'No Nova credentials found for your account',
	'openstackmanager' => '<p>There were no Nova credentials found for your user account. Please ask a Nova administrator to create credentials for you.</p>',
	'openstackmanager' => 'Your account is not in the project requested',
	'openstackmanager' => '<p>You can not complete the action requested as your user account is not in the project requested.</p>',
	'openstackmanager' =>'Create Domain',

	'openstackmanager' =>'Delete domain',
	'openstackmanager' =>'Are you sure you wish to delete domain "$1"? This action has reprecusions on all VMs. Do not take this action lightly!',

	'openstackmanager' =>'Create a new domain',
	'openstackmanager' =>'Failed to create domain',
	'openstackmanager' =>'Created domain',
	'openstackmanager' =>'Back to domain list',
	'openstackmanager' =>'Successfully deleted domain',
	'openstackmanager' => 'Failed to delete domain',

	'openstackmanager' =>'Create Instance',
	'openstackmanager' =>'Configure Instance',
	'openstackmanager' => 'The host requested does not exist.',

	'openstackmanager' => 'Are you sure you wish to delete instance "$1"?',
	'openstackmanager' => 'Instance list',
	'openstackmanager' => 'Instance Name',
	'openstackmanager' => 'Instance ID',
	'openstackmanager' => 'Instance State',
	'openstackmanager' => 'Instance Type',
	'openstackmanager' => 'Image ID',
	'openstackmanager' => 'Actions',

	'openstackmanager' => 'Create a new instance',
	'openstackmanager' => 'Requested domain is invalid',

	'openstackmanager' => 'Created instance $1 with image $2 and hostname $3 and ip $4',
	'openstackmanager' => 'Failed to create instance as the host could not be added to LDAP',
    'openstackmanager' => 'Failed to create instance',
	'openstackmanager' => 'Back to instance list',
    'openstackmanager' => 'Deleted instance $1',
	'openstackmanager' => 'Successfully deleted instance, but failed to remove $1 DNS entry',
	'openstackmanager' => 'Successfully modified instance',
	'openstackmanager' => 'Failed to modify instance',
	'openstackmanager' => 'The host requested does not exist.',

	'openstackmanager' => 'Delete key',
	'openstackmanager' => 'Are you sure you wish to delete the above key?',
	'openstackmanager' => 'Key list',
	'openstackmanager' => 'Import a new key',
	'openstackmanager' => 'Name',
	'openstackmanager' => 'Fingerprint',
	'openstackmanager' => 'Invalid keypair location configured',
	'openstackmanager' => 'Failed to import keypair',
	'openstackmanager' => 'Imported keypair',
	'openstackmanager' => 'Imported keypair $1 with fingerprint $2',
	'openstackmanager' => 'Invalid keypair location configured',
	'openstackmanager' => 'Back to key list',
	'openstackmanager' => 'Successfully deleted key',
	'openstackmanager' => 'Failed to delete key',

	'openstackmanager' => 'Add projectmember',
	'openstackmanager' => 'Remove project member',
	'openstackmanager' => 'Are you sure you wish to remove $1 from project $2',
	'openstackmanager' => 'Are you sure you wish to delete project "$1"? This action has reprecusions on all VMs. Do not take this action lightly!',
	'openstackmanager' => 'Create a new project',
	'openstackmanager' => 'Project name',
	'openstackmanager' => 'Members',
	'openstackmanager' => 'Action',
	'openstackmanager' => 'Remove member',
	'openstackmanager' => 'Add a member',
	'openstackmanager' => 'Failed to create project',
	'openstackmanager' => 'Created project',
	'openstackmanager' => 'Back to project list',
	'openstackmanager' => 'Successfully deleted project',
	'openstackmanager' => 'Failed to delete project',
	'openstackmanager' => 'Successfully added $1 to $2',
	'openstackmanager' => 'Failed to add $1 to $2',

);
