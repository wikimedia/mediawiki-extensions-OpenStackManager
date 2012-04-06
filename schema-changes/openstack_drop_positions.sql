ALTER TABLE /*_*/openstack_puppet_groups
	DROP COLUMN group_position;

ALTER TABLE /*_*/openstack_puppet_vars
	DROP COLUMN var_position;

ALTER TABLE /*_*/openstack_puppet_classes
	DROP COLUMN class_position;
