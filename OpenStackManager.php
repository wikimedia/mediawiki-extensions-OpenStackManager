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
	'version' => '2.2.0',
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
$wgAvailableRights[] = 'accessrestrictedregions';

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
$wgOpenStackManagerLDAPProjectBaseDN = '';
// DN location of service groups
$wgOpenStackManagerLDAPServiceGroupBaseDN = '';
// Service groups will be automatically prefaced with this;
// e.g. if the user asks for 'fancytool' the group will be called
// 'local-fancytool'.
$wgOpenStackManagerServiceGroupPrefix = 'local-';
// Default pattern for service group homedir creation.
// %u is username, %p is $wgOpenStackManagerServiceGroupPrefix.
$wgOpenStackManagerServiceGroupHomedirPattern = '/home/%p%u/';

// Key/value pairs like array( 'region1' => '10.4.0.11', 'region2' => '10.68.1.35' )
$wgOpenStackManagerProxyGateways = array();

$wgOpenStackManagerIdRanges = array(
	'service' => array(
		'gid' => array( 'min' => 40000, 'max' => 49999 ),
	),
);

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
// List of instance type names to not display on instance creation interface
$wgOpenStackManagerInstanceBannedInstanceTypes = array();
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
// Custom namespace for projects
$wgOpenStackManagerProjectNamespace = NS_NOVA_RESOURCE;

// Puppetstatus interval in minutes.  After this much time puppet status
// will be deemed stale
$wgPuppetInterval = 1440;

// A list of regions restricted to a group by right
$wgOpenStackManagerRestrictedRegions = array();

// A list of regions which are visible yet disabled (e.g. instance creation forbidden)
$wgOpenStackManagerReadOnlyRegions = array();

$dir = __DIR__ . '/';

$wgMessagesDirs['OpenStackManager'] = __DIR__ . '/i18n';
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
$wgAutoloadClasses['OpenStackNovaPublicHost'] = $dir . 'nova/OpenStackNovaPublicHost.php';
$wgAutoloadClasses['OpenStackNovaPrivateHost'] = $dir . 'nova/OpenStackNovaPrivateHost.php';
$wgAutoloadClasses['OpenStackNovaAddress'] = $dir . 'nova/OpenStackNovaAddress.php';
$wgAutoloadClasses['OpenStackNovaSecurityGroup'] = $dir . 'nova/OpenStackNovaSecurityGroup.php';
$wgAutoloadClasses['OpenStackNovaSecurityGroupRule'] = $dir . 'nova/OpenStackNovaSecurityGroupRule.php';
$wgAutoloadClasses['OpenStackNovaRole'] = $dir . 'nova/OpenStackNovaRole.php';
$wgAutoloadClasses['OpenStackNovaServiceGroup'] = $dir . 'nova/OpenStackNovaServiceGroup.php';
$wgAutoloadClasses['OpenStackNovaVolume'] = $dir . 'nova/OpenStackNovaVolume.php';
$wgAutoloadClasses['OpenStackNovaSudoer'] = $dir . 'nova/OpenStackNovaSudoer.php';
$wgAutoloadClasses['OpenStackNovaProxy'] = $dir . 'nova/OpenStackNovaProxy.php';
$wgAutoloadClasses['OpenStackNovaArticle'] = $dir . 'nova/OpenStackNovaArticle.php';
$wgAutoloadClasses['OpenStackNovaHostJob'] = $dir . 'nova/OpenStackNovaHostJob.php';
$wgAutoloadClasses['OpenStackNovaHostDeleteJob'] = $dir . 'nova/OpenStackNovaHostDeleteJob.php';
$wgAutoloadClasses['OpenStackNovaPuppetGroup'] = $dir . 'nova/OpenStackNovaPuppetGroup.php';
$wgAutoloadClasses['OpenStackNovaLdapConnection'] = $dir . 'nova/OpenStackNovaLdapConnection.php';
$wgAutoloadClasses['OpenStackNovaProject'] = $dir . 'nova/OpenStackNovaProject.php';
$wgAutoloadClasses['OpenStackNovaProjectLimits'] = $dir . 'nova/OpenStackNovaProjectLimits.php';
$wgAutoloadClasses['OpenStackNovaProjectGroup'] = $dir . 'nova/OpenStackNovaProjectGroup.php';
$wgAutoloadClasses['SpecialNovaInstance'] = $dir . 'special/SpecialNovaInstance.php';
$wgAutoloadClasses['SpecialNovaKey'] = $dir . 'special/SpecialNovaKey.php';
$wgAutoloadClasses['SpecialNovaProject'] = $dir . 'special/SpecialNovaProject.php';
$wgAutoloadClasses['SpecialNovaDomain'] = $dir . 'special/SpecialNovaDomain.php';
$wgAutoloadClasses['SpecialNovaAddress'] = $dir . 'special/SpecialNovaAddress.php';
$wgAutoloadClasses['SpecialNovaSecurityGroup'] = $dir . 'special/SpecialNovaSecurityGroup.php';
$wgAutoloadClasses['SpecialNovaRole'] = $dir . 'special/SpecialNovaRole.php';
$wgAutoloadClasses['SpecialNovaServiceGroup'] = $dir . 'special/SpecialNovaServiceGroup.php';
$wgAutoloadClasses['SpecialNovaVolume'] = $dir . 'special/SpecialNovaVolume.php';
$wgAutoloadClasses['SpecialNovaSudoer'] = $dir . 'special/SpecialNovaSudoer.php';
$wgAutoloadClasses['SpecialNovaProxy'] = $dir . 'special/SpecialNovaProxy.php';
$wgAutoloadClasses['SpecialNovaPuppetGroup'] = $dir . 'special/SpecialNovaPuppetGroup.php';
$wgAutoloadClasses['SpecialNovaResources'] = $dir . 'special/SpecialNovaResources.php';
$wgAutoloadClasses['SpecialNova'] = $dir . 'special/SpecialNova.php';
$wgAutoloadClasses['ApiNovaInstance'] = $dir . 'api/ApiNovaInstance.php';
$wgAutoloadClasses['ApiNovaAddress'] = $dir . 'api/ApiNovaAddress.php';
$wgAutoloadClasses['ApiNovaProjects'] = $dir . 'api/ApiNovaProjects.php';
$wgAutoloadClasses['ApiNovaProjectLimits'] = $dir . 'api/ApiNovaProjectLimits.php';
$wgAutoloadClasses['ApiNovaServiceGroups'] = $dir . 'api/ApiNovaServiceGroups.php';
$wgAutoloadClasses['ApiListNovaProjects'] = $dir . 'api/ApiListNovaProjects.php';
$wgAutoloadClasses['Spyc'] = $dir . 'Spyc.php';
$wgAutoloadClasses['OpenStackManagerNotificationFormatter'] = $dir . 'OpenStackManagerNotificationFormatter.php';
$wgAutoloadClasses['OpenStackManagerEvent'] = $dir . 'OpenStackManagerEvent.php';
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
$wgSpecialPages['NovaServiceGroup'] = 'SpecialNovaServiceGroup';
$wgSpecialPageGroups['NovaServiceGroup'] = 'nova';
$wgSpecialPages['NovaRole'] = 'SpecialNovaRole';
$wgSpecialPageGroups['NovaRole'] = 'nova';
$wgSpecialPages['NovaVolume'] = 'SpecialNovaVolume';
$wgSpecialPageGroups['NovaVolume'] = 'nova';
$wgSpecialPages['NovaSudoer'] = 'SpecialNovaSudoer';
$wgSpecialPageGroups['NovaSudoer'] = 'nova';
$wgSpecialPages['NovaProxy'] = 'SpecialNovaProxy';
$wgSpecialPageGroups['NovaProxy'] = 'nova';
$wgJobClasses['addDNSHostToLDAP'] = 'OpenStackNovaHostJob';
$wgJobClasses['deleteDNSHostFromLDAP'] = 'OpenStackNovaHostDeleteJob';
$wgSpecialPageGroups['NovaPuppetGroup'] = 'nova';
$wgSpecialPages['NovaPuppetGroup'] = 'SpecialNovaPuppetGroup';
$wgSpecialPageGroups['NovaResources'] = 'nova';
$wgSpecialPages['NovaResources'] = 'SpecialNovaResources';

$wgHooks['LDAPSetCreationValues'][] = 'OpenStackNovaUser::LDAPSetCreationValues';
$wgHooks['LDAPRetrySetCreationValues'][] = 'OpenStackNovaUser::LDAPRetrySetCreationValues';
$wgHooks['LDAPModifyUITemplate'][] = 'OpenStackNovaUser::LDAPModifyUITemplate';
$wgHooks['AbortNewAccount'][] = 'OpenStackNovaUser::AbortNewAccount';
$wgHooks['LDAPUpdateUser'][] = 'OpenStackNovaUser::LDAPUpdateUser';
$wgHooks['DynamicSidebarGetGroups'][] = 'OpenStackNovaUser::DynamicSidebarGetGroups';
$wgHooks['ChainAuth'][] = 'OpenStackNovaUser::ChainAuth';
$wgHooks['GetPreferences'][] = 'OpenStackNovaUser::novaUserPreferences';
$wgHooks['AddNewAccount'][] = 'OpenStackNovaUser::RequestShellAccess';

$commonModuleInfo = array(
	'localBasePath' => dirname( __FILE__ ) . '/modules',
	'remoteExtPath' => 'OpenStackManager/modules',
);

$wgResourceModules['ext.openstack'] = array(
	'styles' => 'ext.openstack.css',

	'dependencies' => array(
		'jquery.spinner',
		'mediawiki.api',
		'jquery.ui.dialog',
	),

	'scripts' => array(
		'ext.openstack.js',
	),
) + $commonModuleInfo;

$wgResourceModules['ext.openstack.Instance'] = array(
	'dependencies' => array(
		'ext.openstack',
	),

	'messages' => array(
		'openstackmanager-rebootinstancefailed',
		'openstackmanager-rebootedinstance',
		'openstackmanager-consoleoutput',
		'openstackmanager-getconsoleoutputfailed',
		'openstackmanager-deletedinstance',
		'openstackmanager-deleteinstancefailed',
		'openstackmanager-deleteinstance',
		'openstackmanager-deleteinstancequestion',
	),
	'scripts' => array(
		'ext.openstack.Instance.js',
	),
) + $commonModuleInfo;

$wgResourceModules['ext.openstack.Address'] = array(
	'dependencies' => array(
		'ext.openstack',
	),

	'messages' => array(
		'openstackmanager-disassociateaddressfailed',
		'openstackmanager-disassociateaddress-confirm',
		'openstackmanager-disassociatedaddress',
		'openstackmanager-associateaddress',
		'openstackmanager-releaseaddress',
		'openstackmanager-unknownerror',
	),

	'scripts' => array(
		'ext.openstack.Address.js',
	),
) + $commonModuleInfo;

$wgAPIModules['novainstance'] = 'ApiNovaInstance';
$wgAPIModules['novaaddress'] = 'ApiNovaAddress';
$wgAPIModules['novaprojects'] = 'ApiNovaProjects';
$wgAPIModules['novaservicegroups'] = 'ApiNovaServiceGroups';
$wgAPIModules['novaprojectlimits'] = 'ApiNovaProjectLimits';
$wgAPIListModules['novaprojects'] = 'ApiListNovaProjects';

# Schema changes
$wgHooks['LoadExtensionSchemaUpdates'][] = 'efOpenStackSchemaUpdates';

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
		$updater->addExtensionTable( 'openstack_notification_event', "$base/schema-changes/openstack_add_notification_events_table.sql" );
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

# Echo integration
$wgHooks['BeforeCreateEchoEvent'][] = 'efOpenStackOnBeforeCreateEchoEvent';
$wgHooks['EchoGetDefaultNotifiedUsers'][] = 'efOpenStackGetDefaultNotifiedUsers';
$wgDefaultUserOptions['echo-subscriptions-web-osm-instance-build-completed'] = true;
$wgDefaultUserOptions['echo-subscriptions-email-osm-instance-build-completed'] = true;
$wgDefaultUserOptions['echo-subscriptions-web-osm-instance-reboot-completed'] = true;
$wgDefaultUserOptions['echo-subscriptions-web-osm-instance-deleted'] = true;
$wgDefaultUserOptions['echo-subscriptions-email-osm-instance-deleted'] = true;
$wgDefaultUserOptions['echo-subscriptions-web-osm-projectmembers-add'] = true;
$wgDefaultUserOptions['echo-subscriptions-email-osm-projectmembers-add'] = true;

/**
 * Add OSM events to Echo.
 *
 * @param array $notifications Echo notifications
 * @param array $notificationCategories Echo notification categories
 */
function efOpenStackOnBeforeCreateEchoEvent(
	&$notifications, &$notificationCategories
) {
	$notifications['osm-instance-build-completed'] = array(
		'formatter-class' => 'OpenStackManagerNotificationFormatter',
		'category' => 'osm-instance-build-completed',
		'title-message' => 'notification-osm-instance-build-completed',
		'title-params' => array( 'agent', 'title', 'instance' ),
		'icon' => 'placeholder',
		'payload' => array( 'summary' ),
		'email-subject-message' => 'notification-osm-instance-build-completed-email-subject',
		'email-subject-params' => array( 'instance' ),
		'email-body-batch-message' => 'notification-osm-instance-build-completed-email-body-batch',
		'email-body-batch-params' => array( 'agent', 'title', 'instance' ),
	);

	$notifications['osm-instance-reboot-completed'] = array(
		'formatter-class' => 'OpenStackManagerNotificationFormatter',
		'category' => 'osm-instance-reboot-completed',
		'title-message' => 'notification-osm-instance-reboot-completed',
		'title-params' => array( 'agent', 'title', 'instance' ),
		'icon' => 'placeholder',
		'payload' => array( 'summary' ),
		'email-subject-message' => 'notification-osm-instance-reboot-completed-email-subject',
		'email-subject-params' => array( 'instance' ),
		'email-body-batch-message' => 'notification-osm-instance-reboot-completed-email-body-batch',
		'email-body-batch-params' => array( 'agent', 'title', 'instance' ),
	);

	$notifications['osm-instance-deleted'] = array(
		'formatter-class' => 'OpenStackManagerNotificationFormatter',
		'category' => 'osm-instance-deleted',
		'title-message' => 'notification-osm-instance-deleted',
		'title-params' => array( 'agent', 'title', 'instance' ),
		'icon' => 'trash',
		'payload' => array( 'summary' ),
		'email-subject-message' => 'notification-osm-instance-deleted-email-subject',
		'email-subject-params' => array( 'instance' ),
		'email-body-batch-message' => 'notification-osm-instance-deleted-email-body-batch',
		'email-body-batch-params' => array( 'agent', 'title', 'instance' ),
	);

	$notifications['osm-projectmembers-add'] = array(
		'formatter-class' => 'EchoBasicFormatter',
		'category' => 'osm-projectmembers-add',
		'title-message' => 'notification-osm-projectmember-added',
		'title-params' => array( 'agent', 'title' ),
		'icon' => 'placeholder',
		'payload' => array( 'summary' ),
		'email-subject-message' => 'notification-osm-projectmember-added-email-subject',
		'email-subject-params' => array(),
		'email-body-batch-message' => 'notification-osm-projectmember-added-email-body-batch',
		'email-body-batch-params' => array( 'agent', 'title' ),
	);

	return true;
}

/**
 * Define who gets notifications for an event.
 *
 * @param $event EchoEvent to get implicitly subscribed users for
 * @param &$users array to append implicitly subscribed users to.
 * @return bool true in all cases
 */
function efOpenStackGetDefaultNotifiedUsers ( $event, &$users ) {
	if ( $event->getType() == 'osm-instance-build-completed' ||
		$event->getType() == 'osm-instance-deleted'
	) {
		$extra = $event->getExtra();
		foreach ( OpenStackNovaProject::getProjectByName( $extra['projectName'] )->getRoles() as $role ) {
			if ( $role->getRoleName() == 'projectadmin' ) {
				foreach ( $role->getMembers() as $roleMember ) {
					$roleMemberUser = User::newFromName( $roleMember );
					$users[$roleMemberUser->getId()] = $roleMemberUser;
				}
			}
		}
	} elseif ( $event->getType() == 'osm-instance-reboot-completed' ) {
		// Only notify the person who initiated the reboot.
		$users[$event->getAgent()->getId()] = $event->getAgent();
	} elseif ( $event->getType() == 'osm-projectmembers-add' ) {
		$extra = $event->getExtra();
		$users[$extra['userAdded']] = User::newFromId( $extra['userAdded'] );
	}
	unset( $users[0] );
	return true;
}

$wgHooks['BeforePageDisplay'][] = 'efOpenStackBeforePageDisplay';

/**
 * @param $out OutputPage
 * @param $skin Skin
 * @return bool
 */
function efOpenStackBeforePageDisplay( $out, $skin ) {
	if ( $out->getTitle()->isSpecial( 'Preferences' ) ) {
		$out->addModuleStyles( 'ext.openstack' );
	}
	return true;
}
