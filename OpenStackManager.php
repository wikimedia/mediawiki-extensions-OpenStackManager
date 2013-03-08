<?php
/**
 * OpenStackManager extension - lets users manage nova and swift
 *
 *
 * For more info see http://mediawiki.org/wiki/Extension:OpenStackManager
 *
 * @file
 * @ingroup Extensions
 * @author Ryan Lane <rlane@wikimedia.org>
 * @copyright © 2010 Ryan Lane
 * @license GNU General Public Licence 2.0 or later
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	echo( "This file is an extension to the MediaWiki software and cannot be used standalone.\n" );
	die( 1 );
}

$wgExtensionCredits['other'][] = array(
	'path' => __FILE__,
	'name' => 'OpenStackManager',
	'author' => 'Ryan Lane',
	'version' => '2.1',
	'url' => 'http://mediawiki.org/wiki/Extension:OpenStackManager',
	'descriptionmsg' => 'openstackmanager-desc',
);

define( "NS_NOVA_RESOURCE", 498 );
define( "NS_NOVA_RESOURCE_TALK", 499 );
$wgExtraNamespaces[NS_NOVA_RESOURCE] = 'Nova_Resource';
$wgExtraNamespaces[NS_NOVA_RESOURCE_TALK] = 'Nova_Resource_Talk';
$wgContentNamespaces[] = NS_NOVA_RESOURCE;

$wgAvailableRights[] = 'listall';
$wgAvailableRights[] = 'manageproject';
$wgAvailableRights[] = 'managednsdomain';
$wgAvailableRights[] = 'manageglobalpuppet';
$wgAvailableRights[] = 'loginviashell';

$wgHooks['UserRights'][] = 'OpenStackNovaUser::manageShellAccess';

// Keystone identity URI
$wgOpenStackManagerNovaIdentityURI = 'http://localhost:5000/v2.0';

// SSH key storage location, ldap or nova
$wgOpenStackManagerNovaKeypairStorage = 'ldap';
// LDAP Auth domain used for OSM
$wgOpenStackManagerLDAPDomain = '';
// UserDN used for reading and writing on the LDAP database
$wgOpenStackManagerLDAPUser = '';
// Actual username of the LDAP user
$wgOpenStackManagerLDAPUsername = '';
// Password used to bind
$wgOpenStackManagerLDAPUserPassword = '';
// DN location of projects
$wgOpenStackManagerLDAPProjectBaseDN = '';
// DN location of hosts/instances
$wgOpenStackManagerLDAPInstanceBaseDN = '';
// gid used when creating users
//TODO: change this ridiculous option to a configurable naming attribute
// Whether to use uid, rather than cn as a naming attribute for user objects
$wgOpenStackManagerLDAPUseUidAsNamingAttribute = false;
// DN location for posix groups based on projects
$wgOpenStackManagerLDAPProjectGroupBaseDN = "";
$wgOpenStackManagerLDAPDefaultGid = '500';
// Shell used when creating users
$wgOpenStackManagerLDAPDefaultShell = '/bin/bash';
// DNS servers, used in SOA record
$wgOpenStackManagerDNSServers = array( 'primary' => 'localhost', 'secondary' => 'localhost' );
// SOA attributes
$wgOpenStackManagerDNSSOA = array(
	'hostmaster' => 'hostmaster@localhost.localdomain',
	'refresh' => '1800',
	'retry' => '3600',
	'expiry' => '86400',
	'minimum' => '7200'
	);
// Default classes and variables to apply to instances when created
$wgOpenStackManagerPuppetOptions = array(
	'enabled' => false,
	'defaultclasses' => array(),
	'defaultvariables' => array()
	);
// User data to inject into instances when created
$wgOpenStackManagerInstanceUserData = array(
	'cloud-config' => array(),
	'scripts' => array(),
	'upstarts' => array(),
	);
// Default security rules to add to a project when created
$wgOpenStackManagerDefaultSecurityGroupRules = array();
// Image ID to default to in the instance creation interface
$wgOpenStackManagerInstanceDefaultImage = "";
// List of image IDs to not display on instance creation interface
$wgOpenStackManagerInstanceBannedImages = Array();
// List of instance type names to not display on instance creation interface
$wgOpenStackManagerInstanceBannedInstanceTypes = Array();
// Whether resource pages should be managed on instance/project creation/deletion
$wgOpenStackManagerCreateResourcePages = true;
// Whether a Server Admin Log page should be created with project pages
$wgOpenStackManagerCreateProjectSALPages = true;
// Whether we should remove a user from all projects on removal of loginviashell
$wgOpenStackManagerRemoveUserFromAllProjectsOnShellDisable = true;
// Whether we should remove a user from bastion on removal of loginviashell
$wgOpenStackManagerRemoveUserFromBastionProjectOnShellDisable = false;
// 'bastion' project name
$wgOpenStackManagerBastionProjectName = 'bastion';
// Base URL for puppet docs. Classname will be appended with s/::/\//g
$wgOpenStackManagerPuppetDocBase = '';
/**
 * Path to the ssh-keygen utility. Used for converting ssh key formats. False to disable its use.
 */
$wgSshKeygen = 'ssh-keygen';
/**
 * Path to the puttygen utility. Used for converting ssh key formats. False to disable its use.
 */
$wgPuttygen = 'puttygen';

$dir = dirname( __FILE__ ) . '/';

$wgExtensionMessagesFiles['OpenStackManager'] = $dir . 'OpenStackManager.i18n.php';
$wgExtensionMessagesFiles['OpenStackManagerAlias'] = $dir . 'OpenStackManager.alias.php';
$wgAutoloadClasses['OpenStackNovaInstance'] = $dir . 'nova/OpenStackNovaInstance.php';
$wgAutoloadClasses['OpenStackNovaInstanceType'] = $dir . 'nova/OpenStackNovaInstanceType.php';
$wgAutoloadClasses['OpenStackNovaImage'] = $dir . 'nova/OpenStackNovaImage.php';
$wgAutoloadClasses['OpenStackNovaKeypair'] = $dir . 'nova/OpenStackNovaKeypair.php';
$wgAutoloadClasses['OpenStackNovaController'] = $dir . 'nova/OpenStackNovaController.php';
$wgAutoloadClasses['OpenStackNovaUser'] = $dir . 'nova/OpenStackNovaUser.php';
$wgAutoloadClasses['OpenStackNovaDomain'] = $dir . 'nova/OpenStackNovaDomain.php';
$wgAutoloadClasses['OpenStackNovaHost'] = $dir . 'nova/OpenStackNovaHost.php';
$wgAutoloadClasses['OpenStackNovaAddress'] = $dir . 'nova/OpenStackNovaAddress.php';
$wgAutoloadClasses['OpenStackNovaSecurityGroup'] = $dir . 'nova/OpenStackNovaSecurityGroup.php';
$wgAutoloadClasses['OpenStackNovaSecurityGroupRule'] = $dir . 'nova/OpenStackNovaSecurityGroupRule.php';
$wgAutoloadClasses['OpenStackNovaRole'] = $dir . 'nova/OpenStackNovaRole.php';
$wgAutoloadClasses['OpenStackNovaVolume'] = $dir . 'nova/OpenStackNovaVolume.php';
$wgAutoloadClasses['OpenStackNovaSudoer'] = $dir . 'nova/OpenStackNovaSudoer.php';
$wgAutoloadClasses['OpenStackNovaArticle'] = $dir . 'nova/OpenStackNovaArticle.php';
$wgAutoloadClasses['OpenStackNovaHostJob'] = $dir . 'nova/OpenStackNovaHostJob.php';
$wgAutoloadClasses['OpenStackNovaPuppetGroup'] = $dir . 'nova/OpenStackNovaPuppetGroup.php';
$wgAutoloadClasses['OpenStackNovaLdapConnection'] = $dir . 'nova/OpenStackNovaLdapConnection.php';
$wgAutoloadClasses['OpenStackNovaProject'] = $dir . 'nova/OpenStackNovaProject.php';
$wgAutoloadClasses['OpenStackNovaProjectGroup'] = $dir . 'nova/OpenStackNovaProjectGroup.php';
$wgAutoloadClasses['SpecialNovaInstance'] = $dir . 'special/SpecialNovaInstance.php';
$wgAutoloadClasses['SpecialNovaKey'] = $dir . 'special/SpecialNovaKey.php';
$wgAutoloadClasses['SpecialNovaProject'] = $dir . 'special/SpecialNovaProject.php';
$wgAutoloadClasses['SpecialNovaDomain'] = $dir . 'special/SpecialNovaDomain.php';
$wgAutoloadClasses['SpecialNovaAddress'] = $dir . 'special/SpecialNovaAddress.php';
$wgAutoloadClasses['SpecialNovaSecurityGroup'] = $dir . 'special/SpecialNovaSecurityGroup.php';
$wgAutoloadClasses['SpecialNovaRole'] = $dir . 'special/SpecialNovaRole.php';
$wgAutoloadClasses['SpecialNovaVolume'] = $dir . 'special/SpecialNovaVolume.php';
$wgAutoloadClasses['SpecialNovaSudoer'] = $dir . 'special/SpecialNovaSudoer.php';
$wgAutoloadClasses['SpecialNovaPuppetGroup'] = $dir . 'special/SpecialNovaPuppetGroup.php';
$wgAutoloadClasses['SpecialNova'] = $dir . 'special/SpecialNova.php';
$wgAutoloadClasses['Spyc'] = $dir . 'Spyc.php';
$wgAutoloadClasses['OpenStackManagerNotificationFormatter'] = $dir . 'OpenStackManagerNotificationFormatter.php';
$wgSpecialPages['NovaInstance'] = 'SpecialNovaInstance';
$wgSpecialPageGroups['NovaInstance'] = 'nova';
$wgSpecialPages['NovaKey'] = 'SpecialNovaKey';
$wgSpecialPageGroups['NovaKey'] = 'nova';
$wgSpecialPages['NovaProject'] = 'SpecialNovaProject';
$wgSpecialPageGroups['NovaProject'] = 'nova';
$wgSpecialPages['NovaDomain'] = 'SpecialNovaDomain';
$wgSpecialPageGroups['NovaDomain'] = 'nova';
$wgSpecialPages['NovaAddress'] = 'SpecialNovaAddress';
$wgSpecialPageGroups['NovaAddress'] = 'nova';
$wgSpecialPages['NovaSecurityGroup'] = 'SpecialNovaSecurityGroup';
$wgSpecialPageGroups['NovaSecurityGroup'] = 'nova';
$wgSpecialPages['NovaRole'] = 'SpecialNovaRole';
$wgSpecialPageGroups['NovaRole'] = 'nova';
$wgSpecialPages['NovaVolume'] = 'SpecialNovaVolume';
$wgSpecialPageGroups['NovaVolume'] = 'nova';
$wgSpecialPages['NovaSudoer'] = 'SpecialNovaSudoer';
$wgSpecialPageGroups['NovaSudoer'] = 'nova';
$wgJobClasses['addDNSHostToLDAP'] = 'OpenStackNovaHostJob';
$wgSpecialPageGroups['NovaPuppetGroup'] = 'nova';
$wgSpecialPages['NovaPuppetGroup'] = 'SpecialNovaPuppetGroup';

$wgHooks['LDAPSetCreationValues'][] = 'OpenStackNovaUser::LDAPSetCreationValues';
$wgHooks['LDAPRetrySetCreationValues'][] = 'OpenStackNovaUser::LDAPRetrySetCreationValues';
$wgHooks['LDAPModifyUITemplate'][] = 'OpenStackNovaUser::LDAPModifyUITemplate';
$wgHooks['LDAPUpdateUser'][] = 'OpenStackNovaUser::LDAPUpdateUser';
$wgHooks['DynamicSidebarGetGroups'][] = 'OpenStackNovaUser::DynamicSidebarGetGroups';
$wgHooks['ChainAuth'][] = 'OpenStackNovaUser::ChainAuth';
$wgHooks['GetPreferences'][] = 'OpenStackNovaUser::manageSSHKey';

$commonModuleInfo = array(
	'localBasePath' => dirname( __FILE__ ) . '/modules',
	'remoteExtPath' => 'OpenStackManager/modules',
);

$wgResourceModules['ext.openstack'] = array(
	'styles' => 'ext.openstack.css',
) + $commonModuleInfo;

# Schema changes
$wgHooks['LoadExtensionSchemaUpdates'][] = 'efOpenStackSchemaUpdates';

$wgHooks['EchoGetDefaultNotifiedUsers'][] = 'efEchoGetDefaultNotifiedUsers';

/**
 * Handler for EchoGetDefaultNotifiedUsers hook.
 * @param $event EchoEvent to get implicitly subscribed users for
 * @param &$users Array to append implicitly subscribed users to.
 * @return bool true in all cases
 */
function efEchoGetDefaultNotifiedUsers ( $event, &$users ) {
	if ( $event->getType() == 'osm-instance-build-completed' || $event->getType() == 'osm-instance-deleted' ) {
		$extra = $event->getExtra(); // Sigh. PHP 5.3 compatability.
		foreach ( OpenStackNovaProject::getProjectByName( $extra['projectName'] )->getRoles() as $role ) {
			if ( $role->getRoleName() == 'projectadmin' ) {
				foreach ( $role->getMembers() as $roleMember ) {
					if ( $roleMember != $event->getAgent() || $event->getType() != 'osm-instance-deleted' ) { // Instance deletion notifications don't need to go to the agent, they already know...
						$roleMemberUser = User::newFromName( $roleMember );
						$users[$roleMemberUser->getId()] = $roleMemberUser;
					}
				}
			}
		}
	} elseif ( $event->getType() == 'osm-instance-reboot-completed' ) {
		$users[$event->getAgent()->getId()] = $event->getAgent(); // Only notify the person who did it to say the reboot was completed.
	}
	return true;
}

$wgEchoNotificationFormatters['osm-instance-build-completed'] = array(
	'class' => 'OpenStackManagerNotificationFormatter',
	'title-message' => 'notification-osm-instance-build-completed',
	'title-params' => array( 'agent', 'title', 'instance' ),
	'icon' => 'placeholder',
	'payload' => array( 'summary' )
);

$wgEchoNotificationFormatters['osm-instance-reboot-completed'] = array(
	'class' => 'OpenStackManagerNotificationFormatter',
	'title-message' => 'notification-osm-instance-reboot-completed',
	'title-params' => array( 'agent', 'title', 'instance' ),
	'icon' => 'placeholder',
	'payload' => array( 'summary' )
);

$wgEchoNotificationFormatters['osm-instance-deleted'] = array(
	'class' => 'OpenStackManagerNotificationFormatter',
	'title-message' => 'notification-osm-instance-deleted',
	'title-params' => array( 'agent', 'title', 'instance' ),
	'icon' => 'trash',
	'payload' => array( 'summary' )
);

$wgEchoEnabledEvents[] = 'osm-instance-build-completed';
$wgEchoEnabledEvents[] = 'osm-instance-reboot-completed';
$wgEchoEnabledEvents[] = 'osm-instance-deleted';

/**
 * @param $updater DatabaseUpdater
 * @return bool
 */
function efOpenStackSchemaUpdates( $updater ) {
	$base = dirname( __FILE__ );
	switch ( $updater->getDB()->getType() ) {
	case 'mysql':
		$updater->addExtensionTable( 'openstack_puppet_groups', "$base/openstack.sql" );
		$updater->addExtensionTable( 'openstack_puppet_vars', "$base/openstack.sql" );
		$updater->addExtensionTable( 'openstack_puppet_classes', "$base/openstack.sql" );
		$updater->addExtensionTable( 'openstack_tokens', "$base/schema-changes/tokens.sql" );
		$updater->addExtensionUpdate( array( 'addField', 'openstack_puppet_groups', 'group_project', "$base/schema-changes/openstack_project_field.sql", true ) );
		$updater->addExtensionUpdate( array( 'addField', 'openstack_puppet_groups', 'group_is_global', "$base/schema-changes/openstack_group_is_global_field.sql", true ) );
		$updater->addExtensionUpdate( array( 'dropField', 'openstack_puppet_groups', 'group_position', "$base/schema-changes/openstack_drop_positions.sql", true ) );
		$updater->addExtensionUpdate( array( 'dropField', 'openstack_puppet_vars', 'var_position', "$base/schema-changes/openstack_drop_positions.sql", true ) );
		$updater->addExtensionUpdate( array( 'dropField', 'openstack_puppet_classes', 'class_position', "$base/schema-changes/openstack_drop_positions.sql", true ) );
		$updater->addExtensionUpdate( array( 'addIndex', 'openstack_puppet_vars', 'var_name', "$base/schema-changes/openstack_add_name_indexes.sql", true ) );
		$updater->addExtensionUpdate( array( 'addIndex', 'openstack_puppet_vars', 'var_group_id', "$base/schema-changes/openstack_add_name_indexes.sql", true ) );
		$updater->addExtensionUpdate( array( 'addIndex', 'openstack_puppet_classes', 'class_name', "$base/schema-changes/openstack_add_name_indexes.sql", true ) );
		$updater->addExtensionUpdate( array( 'addIndex', 'openstack_puppet_classes', 'class_group_id', "$base/schema-changes/openstack_add_name_indexes.sql", true ) );
		break;
	}
	return true;
}
