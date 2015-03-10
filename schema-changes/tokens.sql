CREATE TABLE /*_*/openstack_tokens (
	-- IF for token
	token_id int not null primary key auto_increment,

	-- token itself
	token varchar(2048) binary not null,

	-- User to which this token belongs
	user_id int not null

) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/user_id on /*_*/openstack_tokens (user_id);

