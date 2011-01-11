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
	'openstackmanager-desc' => 'Lets users manage Nova and Swift',

	'openstackmanager' => 'OpenStackManager',
	'openstackmanager-instance' => 'Manage Instance',
	'openstackmanager-title' => 'OpenStackManager',

	'openstackmanager-novadomain' => 'Nova Domain',
	'openstackmanager-novainstance' => 'Nova Instance',
	'openstackmanager-novakey' => 'Nova Key',
	'openstackmanager-novaproject' => 'Nova Project',

	'openstackmanager-delete' => 'delete',
	'openstackmanager-configure' => 'configure',
	'openstackmanager-rename' => 'rename',
	'openstackmanager-actions' => 'Actions',
	'openstackmanager-notloggedin' => 'Login required',
	'openstackmanager-mustbeloggedin' => 'You must be logged in to perform this action',
	'openstackmanager-nonovacred' => 'No Nova credentials found for your account',
	'openstackmanager-nonovacred-admincreate' => 'There were no Nova credentials found for your user account. Please ask a Nova administrator to create credentials for you.',
	'openstackmanager-noaccount' => 'Your account is not in the project requested',
	'openstackmanager-noaccount2' => 'You can not complete the action requested as your user account is not in the project requested.',
	'openstackmanager-createdomain' => 'Create Domain',

	'openstackmanager-deletedomain' => 'Delete domain',
	'openstackmanager-deletedomain-confirm' => 'Are you sure you wish to delete domain "$1"? This action has reprecusions on all VMs. Do not take this action lightly!',
	'openstackmanager-novadomain-domain' => 'Domain',
	'openstackmanager-novadomain-info' => 'Domain Information',

	'openstackmanager-createdomainfailed' => 'Failed to create domain',
	'openstackmanager-createddomain' => 'Created domain',
	'openstackmanager-domainlist' => 'Domain list',
	'openstackmanager-backdomainlist' => 'Back to domain list',
	'openstackmanager-deleteddomain' => 'Successfully deleted domain',
	'openstackmanager-failedeletedomain' => 'Failed to delete domain',
	'openstackmanager-domainname' => 'Domain Name',
	'openstackmanager-fqdn' => 'Fully Qualified Domain Name',
	'openstackmanager-location' => 'Location',

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
	'openstackmanager-instanceip' => 'Instance IP',
	'openstackmanager-availabilityzone' => 'Availability Zone',
	'openstackmanager-imageid' => 'Image ID',
	'openstackmanager-imagetype' => 'Image Type',

	'openstackmanager-createinstance' => 'Create a new instance',
	'openstackmanager-invaliddomain' => 'Requested domain is invalid',

	'openstackmanager-createdinstance' => 'Created instance $1 with image $2 and hostname $3',
	'openstackmanager-createfailedldap' => 'Failed to create instance as the host could not be added to LDAP',
	'openstackmanager-createinstancefailed' => 'Failed to create instance',
	'openstackmanager-backinstancelist' => 'Back to instance list',
	'openstackmanager-deletedinstance' => 'Deleted instance $1',
	'openstackmanager-deletedinstance-faileddns' => 'Successfully deleted instance, but failed to remove $1 DNS entry',
	'openstackmanager-modifiedinstance' => 'Successfully modified instance',
	'openstackmanager-modifyinstancefailed' => 'Failed to modify instance',
	'openstackmanager-deleteinstancefailed' => 'Failed to delete instance',

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
	'openstackmanager-member' => 'Member',
	'openstackmanager-action' => 'Action',
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
	'openstackmanager-novaproject-project' => 'Project',
	'openstackmanager-novaproject-info' => 'Project Information',

	'openstackmanager-shellaccountname' => 'Instance Shell Account Name',
	'openstackmanager-shellaccountnamehelp' => 'The shell account name must start with a-z, and can only contain a-z, 0-9, -, and _ characters',

	'openstackmanager-addresslist' => 'Public IP Address List',
	'openstackmanager-address' => 'Public IP Address',
	'openstackmanager-allocateaddress' => 'Allocate a new public IP address',
	'openstackmanager-releaseaddress' => 'Release IP',
	'openstackmanager-associateaddress' => 'Associate IP',
	'openstackmanager-reassociateaddress' => 'Reassociate IP',
	'openstackmanager-disassociateaddress' => 'Disassociate IP',
	'openstackmanager-allocateaddressfailed' => 'Failed to allocate new public IP address',
	'openstackmanager-allocatedaddress' => 'Allocated new public IP address: $1',
	'openstackmanager-backaddresslist' => 'Back to address list',
	'openstackmanager-allocateaddress-confirm' => 'Are you sure you would like to allocate a new public IP address in project $1?',
	'openstackmanager-releasedaddress' => 'Successfully released address: $1',
	'openstackmanager-releaseaddressfailed' => 'Failed to release address: $1',
	'openstackmanager-associatedaddress' => 'Successfully associated $1 with instance id $2',
	'openstackmanager-associateaddressfailed' => 'Failed to associate $1 with instance id $2',
	'openstackmanager-disassociatedaddress' => 'Successfully disassociated $1',
	'openstackmanager-disassociateaddressfailed' => 'Failed to disassociate $1',
	'openstackmanager-disassociateaddress-confirm' => 'Are you sure you would like to disassociate $1?',
	'openstackmanager-releaseaddress-confirm' => 'Are you sure you would like to release $1?',

);

/** Message documentation (Message documentation)
 * @author Sam Reed <reedy@wikimedia.org>
 */
$messages['qqq'] = array(
	'openstackmanager-desc' => '{{desc}}',
	'openstackmanager-notloggedin' => 'Page title',
	'openstackmanager-createproject' => 'Page title',
	'openstackmanager-addmember' => 'Page title',
	'openstackmanager-removemember' => 'Page title',
	'openstackmanager-deleteproject' => 'Page title',
	'openstackmanager-projectlist' => 'Page title',
	'openstackmanager-instancelist' => 'Page title',
	'openstackmanager-deletedomain' => 'Page title',
	'openstackmanager-createdomain' => 'Page title',
	'openstackmanager-configureinstance' => 'Page title',
	'openstackmanager-importkey' => 'Page title',
	'openstackmanager-deletekey' => 'Page title',
	'openstackmanager-keylist' => 'Page title',
	'openstackmanager-nonovacred' => 'Page title',
);
