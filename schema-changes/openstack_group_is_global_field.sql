ALTER TABLE /*_*/openstack_puppet_groups
	ADD COLUMN group_is_global boolean not null default false;
