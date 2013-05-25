( function ( mw, $ ) {
	'use strict';

	var api = new mw.Api();

	/**
	 * @class mw.openStack.Instance
	 * Represents an OpenStack Instance
	 * @param {Object} descriptor An object with properties:
	 * @param {String} descriptor.id OpenStack Instance ID.
	 * @param {String} descriptor.name Instance name.
	 * @param {String} descriptor.project Name of project.
	 * @param {String} descriptor.region Instance's OpenStack region.
	 */
	function Instance( descriptor ) {
		$.extend( true, this, descriptor );
	}

	Instance.prototype = new mw.OpenStackInterface();

	// A mapping of action names to [ success message, failure message ]:
	Instance.prototype.notifications = {
		reboot : {
			success : 'openstackmanager-rebootedinstance',
			failure : 'openstackmanager-rebootinstancefailed'
		}
	};

	/**
	 * Make an 'action=novainstance' call to the MediaWiki API specifying this
	 * instance and the requested subaction. If notification messages are
	 * configured for this subaction, issues the appropriate notification upon
	 * success/failure.
	 * @param {String} Value for 'subaction' parameter.
	 * @param {Object} Additional parameters, if any.
	 */
	Instance.prototype.api = function ( subaction, params ) {
		var self = this,
			messages = self.notifications[subaction],
			req = api.post( $.extend( {
				format     : 'json',
				action     : 'novainstance',
				instanceid : self.id,
				project    : self.project,
				region     : self.region,
				subaction  : subaction
			}, params ) );

		if ( messages !== undefined ) {
			req.then(
				this.succeed.bind( this, subaction, this.address ),
				this.fail.bind( this, subaction, this.address )
			);
		}
		return req;
	};

	// export
	mw.openStack.Instance = Instance;

	$( '.novainstanceaction' ).on( 'click', function ( e ) {
		var action = mw.util.getParamValue( 'action', this.href ),
			$el = $( this ),
			$spinner = $.createSpinner(),
			instance = new mw.openStack.Instance( $el.data() );

		if ( action !== 'reboot' ) {
			// only reboot is supported right now.
			return;
		}

		e.preventDefault();

		$el.hide().after( $spinner );

		instance.api( action )
			.always( function () {
				$spinner.remove();
				$el.show();
			} );
	} );
} ( mediaWiki, jQuery ) );
