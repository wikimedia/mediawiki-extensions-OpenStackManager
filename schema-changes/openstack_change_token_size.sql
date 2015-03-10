ALTER TABLE /*_*/openstack_tokens
	MODIFY COLUMN token varchar(2048) binary not null;
