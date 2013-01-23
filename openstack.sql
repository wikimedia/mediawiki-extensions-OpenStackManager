CREATE TABLE /*_*/openstack_puppet_groups (
	-- ID for groups. Puppet variables and classes
	-- may be grouped, and can share the same group.
	group_id int not null primary key auto_increment,

	-- User-presentable name of the group
	group_name varchar(255) binary not null,

	-- OpenStack project to which this group belongs, if any
	group_project varchar(255) binary,

	-- OpenStack project to which this group belongs, if any
	group_is_global boolean not null

) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/group_name on /*_*/openstack_puppet_groups (group_name);

CREATE TABLE /*_*/openstack_puppet_vars (
	-- ID for puppet variable
	var_id int not null primary key auto_increment,

	-- User-presentable name of puppet variable
	var_name varchar(255) binary not null,

	-- Group to which this variable belongs
	var_group_id int not null

) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/var_name on /*_*/openstack_puppet_vars (var_name);
CREATE INDEX /*i*/var_group_id on /*_*/openstack_puppet_vars (var_group_id);

CREATE TABLE /*_*/openstack_puppet_classes (
	-- IF for puppet class
	class_id int not null primary key auto_increment,

	-- User-presentable name of puppet class
	class_name varchar(255) binary not null,

	-- Group to which this class belongs
	class_group_id int not null

) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/class_name on /*_*/openstack_puppet_classes (class_name);
CREATE INDEX /*i*/class_group_id on /*_*/openstack_puppet_classes (class_group_id);

CREATE TABLE /*_*/openstack_notification_event (
	event_action varchar(10),
	event_actor_id int(10),
	event_instance_host varchar(255),
	event_instance_name varchar(255),
	event_project varchar(255)
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/event_action_instance_host on /*_*/openstack_notification_event (event_action, event_instance_host);
