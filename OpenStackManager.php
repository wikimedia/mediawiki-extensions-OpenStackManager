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
 * @license GPL-2.0-or-later
 */

if ( !defined( 'MEDIAWIKI' ) ) {
	echo "This file is an extension to the MediaWiki software and cannot be used standalone.\n";
	die( 1 );
}

$wgExtensionCredits['other'][] = [
	'path' => __FILE__,
	'name' => 'OpenStackManager',
	'author' => 'Ryan Lane',
	'version' => '3.0.0',
	'url' => 'https://mediawiki.org/wiki/Extension:OpenStackManager',
	'descriptionmsg' => 'openstackmanager-desc',
	'license-name' => 'GPL-2.0-or-later',
];

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

$wgHooks['getUserPermissionsErrors'][] = 'OpenStackManagerHooks::getUserPermissionsErrors';

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
// from the OSM front end.
$wgOpenStackHiddenUsernames = [ 'novaobserver' ];

// Key/value pairs like array( 'region1' => '10.4.0.11', 'region2' => '10.68.1.35' )
$wgOpenStackManagerProxyGateways = [];

$wgOpenStackManagerIdRanges = [
	'service' => [
		'gid' => [ 'min' => 40000, 'max' => 49999 ],
	],
];

// DN location for posix groups based on projects
$wgOpenStackManagerLDAPDefaultGid = '500';
// Shell used when creating users
$wgOpenStackManagerLDAPDefaultShell = '/bin/bash';
// DNS servers, used in SOA record
$wgOpenStackManagerDNSServers = [ 'primary' => 'localhost', 'secondary' => 'localhost' ];
// SOA attributes
$wgOpenStackManagerDNSSOA = [
	'hostmaster' => 'hostmaster@localhost.localdomain',
	'refresh' => '1800',
	'retry' => '3600',
	'expiry' => '86400',
	'minimum' => '7200'
	];
// User data to inject into instances when created
$wgOpenStackManagerInstanceUserData = [
	'cloud-config' => [],
	'scripts' => [],
	'upstarts' => [],
	];
// Default security rules to add to a project when created
$wgOpenStackManagerDefaultSecurityGroupRules = [];
// List of instance type names to not display on instance creation interface
$wgOpenStackManagerInstanceBannedInstanceTypes = [];
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
$wgOpenStackManagerRestrictedRegions = [];

// A list of regions which are visible yet disabled (e.g. instance creation forbidden)
$wgOpenStackManagerReadOnlyRegions = [];

$dir = __DIR__ . '/';

$wgMessagesDirs['OpenStackManager'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['OpenStackManagerAlias'] = $dir . 'OpenStackManager.alias.php';
$wgAutoloadClasses['YamlContent'] = $dir . 'includes/YamlContent.php';
$wgAutoloadClasses['YamlContentHandler'] = $dir . 'includes/YamlContentHandler.php';
$wgAutoloadClasses['OpenStackManagerHooks'] = $dir . 'OpenStackManagerHooks.php';
$wgAutoloadClasses['OpenStackNovaKeypair'] = $dir . 'nova/OpenStackNovaKeypair.php';
$wgAutoloadClasses['OpenStackNovaUser'] = $dir . 'nova/OpenStackNovaUser.php';
$wgAutoloadClasses['OpenStackNovaLdapConnection'] = $dir . 'nova/OpenStackNovaLdapConnection.php';
$wgAutoloadClasses['OpenStackNovaShellAccountNameRequest'] =
	$dir . 'nova/OpenStackNovaShellAccountNameRequest.php';
$wgAutoloadClasses['OpenStackNovaSecondaryAuthenticationProvider'] =
	$dir . '/nova/OpenStackNovaSecondaryAuthenticationProvider.php';
$wgAutoloadClasses['SpecialNovaKey'] = $dir . 'special/SpecialNovaKey.php';
$wgAutoloadClasses['SpecialNova'] = $dir . 'special/SpecialNova.php';
$wgSpecialPages['NovaKey'] = 'SpecialNovaKey';

$wgHooks['LDAPSetCreationValues'][] = 'OpenStackNovaUser::LDAPSetCreationValues';
$wgHooks['LDAPRetrySetCreationValues'][] = 'OpenStackNovaUser::LDAPRetrySetCreationValues';
$wgHooks['LDAPModifyUITemplate'][] = 'OpenStackNovaUser::LDAPModifyUITemplate';
$wgHooks['GetPreferences'][] = 'OpenStackNovaUser::novaUserPreferences';
$wgHooks['AuthChangeFormFields'][] = 'OpenStackNovaUser::AuthChangeFormFields';

$wgAuthManagerAutoConfig['preauth'] += [
	OpenStackNovaSecondaryAuthenticationProvider::class => [
			'class' => OpenStackNovaSecondaryAuthenticationProvider::class,
			'sort' => 0, // non-UI providers should run early
	],
];

if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}
