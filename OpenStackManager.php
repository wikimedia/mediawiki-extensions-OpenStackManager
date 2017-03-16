<?php
/**
 * OpenStackManager extension - lets users manage nova and swift
 *
 *
 * For more info see https://mediawiki.org/wiki/Extension:OpenStackManager
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
	'url' => 'https://mediawiki.org/wiki/Extension:OpenStackManager',
	'descriptionmsg' => 'openstackmanager-desc',
	'license-name' => 'GPL-2.0+',
);

define( 'CONTENT_MODEL_YAML', 'yaml' );
define( 'CONTENT_FORMAT_YAML', 'application/yaml' );

define( "NS_NOVA_RESOURCE", 498 );
define( "NS_NOVA_RESOURCE_TALK", 499 );
define( 'NS_HIERA', 666 );
define( 'NS_HIERA_TALK', 667 );

$wgExtraNamespaces[NS_HIERA] = 'Hiera';
$wgExtraNamespaces[NS_HIERA_TALK] = 'Hiera_Talk';
$wgContentHandlers[CONTENT_MODEL_YAML] = 'YamlContentHandler';
$wgNamespaceContentModels[NS_HIERA] = CONTENT_MODEL_YAML;

$wgSyntaxHighlightModels[CONTENT_MODEL_YAML] = 'yaml';

$wgExtraNamespaces[NS_NOVA_RESOURCE] = 'Nova_Resource';
$wgExtraNamespaces[NS_NOVA_RESOURCE_TALK] = 'Nova_Resource_Talk';
$wgContentNamespaces[] = NS_NOVA_RESOURCE;

$wgAvailableRights[] = 'listall';
$wgAvailableRights[] = 'manageproject';
$wgAvailableRights[] = 'managednsdomain';
$wgAvailableRights[] = 'loginviashell';
$wgAvailableRights[] = 'accessrestrictedregions';
$wgAvailableRights[] = 'editallhiera';

$wgHooks['UserAddGroup'][] = 'OpenStackNovaUser::addUserToBastionProject';
$wgHooks['UserRemoveGroup'][] = 'OpenStackNovaUser::removeUserFromBastionProject';
$wgHooks['getUserPermissionsErrors'][] = 'OpenStackManagerHooks::getUserPermissionsErrors';

// Keystone identity URI
$wgOpenStackManagerNovaIdentityURI = 'http://localhost:5000/v2.0';
$wgOpenStackManagerNovaIdentityV3URI = 'http://localhost:5000/v3';

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
// Project that $wgOpenStackManagerLDAPUsername has admin on
$wgOpenStackManagerProject = '';
// Keystone ID of same
$wgOpenStackManagerProjectId = '';
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

// Username for special observer user -- hidden
//  from the OSM front end.
$wgOpenStackHiddenUsernames = array('novaobserver');

// Key/value pairs like array( 'region1' => '10.4.0.11', 'region2' => '10.68.1.35' )
$wgOpenStackManagerProxyGateways = array();

$wgOpenStackManagerIdRanges = array(
	'service' => array(
		'gid' => array( 'min' => 40000, 'max' => 49999 ),
	),
);

// DN location for posix groups based on projects
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
$wgOpenStackManagerBastionProjectId = 'bastion';
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

// A list of regions restricted to a group by right
$wgOpenStackManagerRestrictedRegions = array();

// A list of regions which are visible yet disabled (e.g. instance creation forbidden)
$wgOpenStackManagerReadOnlyRegions = array();

$dir = __DIR__ . '/';

$wgMessagesDirs['OpenStackManager'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['OpenStackManagerAlias'] = $dir . 'OpenStackManager.alias.php';
$wgAutoloadClasses['YamlContent'] = $dir . 'includes/YamlContent.php';
$wgAutoloadClasses['YamlContentHandler'] = $dir . 'includes/YamlContentHandler.php';
$wgAutoloadClasses['OpenStackManagerHooks'] = $dir . 'OpenStackManagerHooks.php';
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
$wgAutoloadClasses['OpenStackNovaLdapConnection'] = $dir . 'nova/OpenStackNovaLdapConnection.php';
$wgAutoloadClasses['OpenStackNovaProject'] = $dir . 'nova/OpenStackNovaProject.php';
$wgAutoloadClasses['OpenStackNovaProjectLimits'] = $dir . 'nova/OpenStackNovaProjectLimits.php';
$wgAutoloadClasses['OpenStackNovaShellAccountNameRequest'] = $dir . 'nova/OpenStackNovaShellAccountNameRequest.php';
$wgAutoloadClasses['OpenStackNovaSecondaryAuthenticationProvider'] = $dir . '/nova/OpenStackNovaSecondaryAuthenticationProvider.php';
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
$wgAutoloadClasses['SpecialNovaResources'] = $dir . 'special/SpecialNovaResources.php';
$wgAutoloadClasses['SpecialNova'] = $dir . 'special/SpecialNova.php';
$wgAutoloadClasses['ApiNovaInstance'] = $dir . 'api/ApiNovaInstance.php';
$wgAutoloadClasses['ApiNovaAddress'] = $dir . 'api/ApiNovaAddress.php';
$wgAutoloadClasses['ApiNovaProjects'] = $dir . 'api/ApiNovaProjects.php';
$wgAutoloadClasses['ApiNovaProjectLimits'] = $dir . 'api/ApiNovaProjectLimits.php';
$wgAutoloadClasses['ApiNovaServiceGroups'] = $dir . 'api/ApiNovaServiceGroups.php';
$wgAutoloadClasses['ApiListNovaProjects'] = $dir . 'api/ApiListNovaProjects.php';
$wgAutoloadClasses['ApiListNovaInstances'] = $dir . 'api/ApiListNovaInstances.php';
$wgAutoloadClasses['Spyc'] = $dir . 'Spyc.php';
$wgAutoloadClasses['EchoOpenStackManagerPresentationModel'] = $dir . 'EchoOpenStackManagerPresentationModel.php';
$wgAutoloadClasses['OpenStackManagerEvent'] = $dir . 'OpenStackManagerEvent.php';
$wgSpecialPages['NovaInstance'] = 'SpecialNovaInstance';
$wgSpecialPages['NovaKey'] = 'SpecialNovaKey';
$wgSpecialPages['NovaProject'] = 'SpecialNovaProject';
$wgSpecialPages['NovaSecurityGroup'] = 'SpecialNovaSecurityGroup';
$wgSpecialPages['NovaServiceGroup'] = 'SpecialNovaServiceGroup';
$wgSpecialPages['NovaRole'] = 'SpecialNovaRole';
$wgSpecialPages['NovaVolume'] = 'SpecialNovaVolume';
$wgSpecialPages['NovaSudoer'] = 'SpecialNovaSudoer';
$wgSpecialPages['NovaResources'] = 'SpecialNovaResources';

$wgHooks['LDAPSetCreationValues'][] = 'OpenStackNovaUser::LDAPSetCreationValues';
$wgHooks['LDAPRetrySetCreationValues'][] = 'OpenStackNovaUser::LDAPRetrySetCreationValues';
$wgHooks['LDAPModifyUITemplate'][] = 'OpenStackNovaUser::LDAPModifyUITemplate';
$wgHooks['AbortNewAccount'][] = 'OpenStackNovaUser::AbortNewAccount';
$wgHooks['LDAPUpdateUser'][] = 'OpenStackNovaUser::LDAPUpdateUser';
$wgHooks['DynamicSidebarGetGroups'][] = 'OpenStackNovaUser::DynamicSidebarGetGroups';
$wgHooks['ChainAuth'][] = 'OpenStackNovaUser::ChainAuth';
$wgHooks['GetPreferences'][] = 'OpenStackNovaUser::novaUserPreferences';
$wgHooks['AuthChangeFormFields'][] = 'OpenStackNovaUser::AuthChangeFormFields';

$wgAuthManagerAutoConfig['preauth'] += [
	OpenStackNovaSecondaryAuthenticationProvider::class => [
			'class' => OpenStackNovaSecondaryAuthenticationProvider::class,
			'sort' => 0, // non-UI providers should run early
	],
];

$commonModuleInfo = array(
	'localBasePath' => __DIR__,
	'remoteExtPath' => 'OpenStackManager',
);

$wgResourceModules['ext.openstack'] = array(
	'position' => 'top',

	'styles' => 'modules/ext.openstack.css',

	'dependencies' => array(
		'jquery.spinner',
		'mediawiki.api',
		'jquery.ui.dialog',
	),

	'scripts' => array(
		'modules/ext.openstack.js',
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
		'modules/ext.openstack.Instance.js',
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
		'modules/ext.openstack.Address.js',
	),
) + $commonModuleInfo;

$wgAPIModules['novainstance'] = 'ApiNovaInstance';
$wgAPIModules['novaaddress'] = 'ApiNovaAddress';
$wgAPIModules['novaprojects'] = 'ApiNovaProjects';
$wgAPIModules['novaservicegroups'] = 'ApiNovaServiceGroups';
$wgAPIModules['novaprojectlimits'] = 'ApiNovaProjectLimits';
$wgAPIListModules['novaprojects'] = 'ApiListNovaProjects';
$wgAPIListModules['novainstances'] = 'ApiListNovaInstances';

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
		$updater->addExtensionTable( 'openstack_tokens', "$base/schema-changes/tokens.sql" );
		$updater->addExtensionTable( 'openstack_notification_event', "$base/schema-changes/openstack_add_notification_events_table.sql" );
		$updater->addExtensionUpdate( array( 'modifyField', 'openstack_tokens', 'token', "$base/schema-changes/openstack_change_token_size.sql", true ) );
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
		'presentation-model' => 'EchoOpenStackManagerPresentationModel',
		'category' => 'osm-instance-build-completed',
		'section' => 'message',
	);

	$notifications['osm-instance-reboot-completed'] = array(
		'presentation-model' => 'EchoOpenStackManagerPresentationModel',
		'category' => 'osm-instance-reboot-completed',
		'section' => 'message',
	);

	$notifications['osm-instance-deleted'] = array(
		'presentation-model' => 'EchoOpenStackManagerPresentationModel',
		'category' => 'osm-instance-deleted',
		'section' => 'message',
	);

	$notifications['osm-projectmembers-add'] = array(
		'presentation-model' => 'EchoOpenStackManagerPresentationModel',
		'category' => 'osm-projectmembers-add',
		'section' => 'message',
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
