CREATE TABLE /*_*/openstack_notification_event (
	event_action varchar(10),
	event_actor_id int(10),
	event_instance_host varchar(255),
	event_instance_name varchar(255),
	event_project varchar(255)
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/event_action_instance_host on /*_*/openstack_notification_event (event_action, event_instance_host);
