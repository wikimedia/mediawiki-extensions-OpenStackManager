<?php
class OpenStackManagerNotificationFormatter extends EchoBasicFormatter {
	protected function processParam( $event, $param, $message, $user ) {
		if ( $param === 'instance' ) {
			$instance = $event->getExtraParam( 'instanceName' );
			if ( $instance ) {
				$message->params( $instance );
			} else {
				$message->params( '' );
			}
		} else {
			parent::processParam( $event, $param, $message, $user );
		}
	}
}
