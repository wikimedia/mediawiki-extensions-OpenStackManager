ALTER TABLE /*_*/openstack_puppet_groups
	ADD COLUMN group_project varchar(255) binary default null;

ALTER TABLE /*_*/openstack_puppet_vars
	ADD COLUMN var_project varchar(255) binary default null;

ALTER TABLE /*_*/openstack_puppet_classes
	ADD COLUMN class_project varchar(255) binary default null;
