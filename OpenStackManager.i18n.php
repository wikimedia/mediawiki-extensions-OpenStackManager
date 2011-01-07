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
 * @author Sam Reed <reedy@wikimedia.org>
 */
$messages['en'] = array(
	'openstackmanager-desc' => 'Lets users manage nova and swift',

	'openstackmanager' => 'OpenStackManager',
	'openstackmanager-instance' => 'Manage Instance',
	'openstackmanager-title' => 'OpenStackManager',

	'openstackmanager-notloggedin' => 'Not logged in',
	'openstackmanager-mustbeloggedin' => '<p>You must be logged in to perform this action</p>',
	'openstackmanager-nonovacred' => 'No Nova credentials found for your account',
	'openstackmanager-nonovacred-admincreate' => '<p>There were no Nova credentials found for your user account. Please ask a Nova administrator to create credentials for you.</p>',
	'openstackmanager-noaccount' => 'Your account is not in the project requested',
	'openstackmanager-noaccount2' => '<p>You can not complete the action requested as your user account is not in the project requested.</p>',
	'openstackmanager-createdomain' => 'Create Domain',

	'openstackmanager-deletedomain' => 'Delete domain',
	'openstackmanager-deletedomain-confirm' => 'Are you sure you wish to delete domain "$1"? This action has reprecusions on all VMs. Do not take this action lightly!',

	'openstackmanager-createdomainfailed' => 'Failed to create domain',
	'openstackmanager-createddomain' => 'Created domain',
	'openstackmanager-domainlist' => 'Domain list',
	'openstackmanager-backdomainlist' => 'Back to domain list',
	'openstackmanager-deleteddomain' => 'Successfully deleted domain',
	'openstackmanager-failedeletedomain' => 'Failed to delete domain',

	'openstackmanager-novainstance-instance' => 'Instance',
	'openstackmanager-configureinstance' => 'Configure Instance',
	'openstackmanager-nonexistanthost' => 'The host requested does not exist.',
	'openstackmanager-dnsdomain' => 'DNS Domain',
	'openstackmanager-puppetclasses' => 'Puppet Classes',
	'openstackmanager-novainstance-info' => 'Instance Information',
	'openstackmanager-novainstance-puppetinfo' => 'Puppet Information',

	'openstackmanager-deleteinstancequestion' => 'Are you sure you wish to delete instance "$1"?',
	'openstackmanager-instancelist' => 'Instance list',
	'openstackmanager-instancename' => 'Instance Name',
	'openstackmanager-instanceid' => 'Instance ID',
	'openstackmanager-instancestate' => 'Instance State',
	'openstackmanager-instancetype' => 'Instance Type',
	'openstackmanager-availabilityzone' => 'Availability Zone',
	'openstackmanager-imageid' => 'Image ID',
	'openstackmanager-imagetype' => 'Image Type',
	'openstackmanager-actions' => 'Actions',

	'openstackmanager-createinstance' => 'Create a new instance',
	'openstackmanager-invaliddomain' => 'Requested domain is invalid',

	'openstackmanager-createdinstance' => 'Created instance $1 with image $2 and hostname $3 and ip $4',
	'openstackmanager-createfailedldap' => 'Failed to create instance as the host could not be added to LDAP',
	'openstackmanager-createinstancefailed' => 'Failed to create instance',
	'openstackmanager-backinstancelist' => 'Back to instance list',
	'openstackmanager-deletedinstance' => 'Deleted instance $1',
	'openstackmanager-deletedinstance-faileddns' => 'Successfully deleted instance, but failed to remove $1 DNS entry',
	'openstackmanager-modifiedinstance' => 'Successfully modified instance',
	'openstackmanager-modifyinstancefailed' => 'Failed to modify instance',
	'openstackmanager-deleteinstancefailed' => 'Failed to delete instance',
	'openstackmanager-nonexistanthost' => 'The host requested does not exist.',

	'openstackmanager-deletekey' => 'Delete key',
	'openstackmanager-deletekeyconfirm' => 'Are you sure you wish to delete the above key?',
	'openstackmanager-keylist' => 'Key list',
	'openstackmanager-importkey' => 'Import a new key',
	'openstackmanager-name' => 'Name',
	'openstackmanager-fingerprint' => 'Fingerprint',
	'openstackmanager-invalidkeypair' => 'Invalid keypair location configured',
	'openstackmanager-keypairimportfailed' => 'Failed to import keypair',
	'openstackmanager-keypairimported' => 'Imported keypair',
	'openstackmanager-keypairimportedfingerprint' => 'Imported keypair $1 with fingerprint $2',
	'openstackmanager-backkeylist' => 'Back to key list',
	'openstackmanager-deletedkey' => 'Successfully deleted key',
	'openstackmanager-deletedkeyfailed' => 'Failed to delete key',

	'openstackmanager-addmember' => 'Add project member',
	'openstackmanager-removemember' => 'Remove project member',
	'openstackmanager-removememberconfirm' => 'Are you sure you wish to remove $1 from project $2?',
	'openstackmanager-removeprojectconfirm' => 'Are you sure you wish to delete project "$1"? This action has reprecusions on all VMs. Do not take this action lightly!',
	'openstackmanager-createproject' => 'Create a new project',
	'openstackmanager-projectname' => 'Project name',
	'openstackmanager-members' => 'Members',
	'openstackmanager-action' => 'Action',
	'openstackmanager-createproject' => 'Create project',
	'openstackmanager-createprojectfailed' => 'Failed to create project',
	'openstackmanager-createdproject' => 'Created project',
	'openstackmanager-projectlist' => 'Project list',
	'openstackmanager-backprojectlist' => 'Back to project list',
	'openstackmanager-deleteproject' => 'Delete project',
	'openstackmanager-deletedproject' => 'Successfully deleted project',
	'openstackmanager-deleteprojectfailed' => 'Failed to delete project',
	'openstackmanager-addedto' => 'Successfully added $1 to $2',
	'openstackmanager-failedtoadd' => 'Failed to add $1 to $2',
	'openstackmanager-removedfrom' => 'Successfully removed $1 from $2',
	'openstackmanager-failedtoremove' => 'Failed to remove $1 from $2',
	'openstackmanager-badinstancename' => 'Bad instance name provided. Instance names must start with a-z, and can only contain a-z, 0-9, and - characters.',

);
