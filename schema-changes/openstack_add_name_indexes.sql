ALTER TABLE /*_*/openstack_puppet_vars
	ADD INDEX (var_name);

ALTER TABLE /*_*/openstack_puppet_vars
	ADD INDEX (var_group_id);

ALTER TABLE /*_*/openstack_puppet_classes
	ADD INDEX (class_name);

ALTER TABLE /*_*/openstack_puppet_classes
	ADD INDEX (class_group_id);
