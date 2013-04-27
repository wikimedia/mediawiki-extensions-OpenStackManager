( function ( mw, $ ) {
	'use strict';

	var api = new mw.Api();

	/**
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

	// A mapping of action names to [ success message, failure message ]:
	Instance.prototype.notifications = {
		reboot : {
			success : 'openstackmanager-rebootedinstance',
			failure : 'openstackmanager-rebootinstancefailed'
		}
	};

	/**
	 * Issue a notification about this instance's status.
	 * @param {String} msg Message key; will get instance name as parameter.
	 */
	Instance.prototype.notify = function ( msg ) {
		return mw.notify( mw.msg( msg, this.name ) );
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
				function () { self.notify( messages.success ); },
				function () { self.notify( messages.failure ); }
			);
		}
		return req;
	};

	// export
	mw.openStack = {
		Instance: Instance
	};

} ( mediaWiki, jQuery ) );
