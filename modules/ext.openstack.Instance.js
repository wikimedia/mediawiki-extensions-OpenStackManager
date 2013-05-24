( function ( mw, $ ) {
	'use strict';

	var api = new mw.Api(),
		consoledialogs = {};

	/**
	 * @class mw.openStack.Instance
	 * Represents an OpenStack Instance
	 * @param {Object} descriptor An object with properties:
	 * @param {String} descriptor.id OpenStack Instance ID.
	 * @param {String} descriptor.name Instance name.
	 * @param {String} descriptor.project Name of project.
	 * @param {String} descriptor.region Instance's OpenStack region.
	 */
	function Instance( descriptor, $row ) {
		$.extend( true, this, descriptor );
		this.$row = $row;
	}

	Instance.prototype = new mw.OpenStackInterface();

	// A mapping of action names to [ success message, failure message ]:
	Instance.prototype.notifications = {
		reboot : {
			success : 'openstackmanager-rebootedinstance',
			failure : 'openstackmanager-rebootinstancefailed'
		},
		consoleoutput : {
			failure : 'openstackmanager-getconsoleoutputfailed'
		}
	};

	Instance.prototype.consoleoutput = function ( params ) {
		var self = this,
			deferred = $.Deferred();

		if ( self.id in consoledialogs ) {
			setTimeout( deferred.reject );
			return deferred.promise();
		}

		this.api( 'consoleoutput', params )
			.done(
				function ( data ) {
					var $consoleoutput = $( '<pre>' ),
						$existingConsoleoutput = $( '.osm-consoleoutput' ),
						position;

					consoledialogs[ self.id ] = $consoleoutput;
					$consoleoutput.attr( 'title', mw.msg( 'openstackmanager-consoleoutput', self.name, self.id ) )
						.addClass( 'osm-consoleoutput' )
						.text( data.novainstance.consoleoutput );
					if ( $existingConsoleoutput.length ) {
						// position this dialog next to the last
						position = {
							my: 'left top',
							at: 'right top',
							of: $existingConsoleoutput.last().dialog( 'widget' )
						};
					} else {
						// this is the first dialog, position it left bottom
						position = {
							my: 'left bottom',
							at: 'left bottom',
							of: window
						};
					}
					$consoleoutput.dialog( {
						// remove the element, or it'll screw up positioning
						close: function () {
							delete consoledialogs[ self.id ];
							$( this ).dialog( 'destroy' ).remove();
						},
						modal: false,
						draggable: true,
						resizable: true,
						height: 500,
						width: 600,
						position: position,
						autoOpen: false
					} );
					$consoleoutput.dialog( 'widget' ).css( 'position', 'fixed' );
					$consoleoutput.dialog( 'open' );
				},
				deferred.resolve
			)
			.fail( deferred.reject );

		return deferred.promise();
	};

	Instance.prototype.reboot = function ( params ) {
		var deferred = $.Deferred(),
			$state = this.$row.find( '.novainstancestate' );

		this.api( 'reboot', params )
			.done(
				function ( data ) {
					$state.text( data.novainstance.instancestate );
				},
				deferred.resolve
			)
			.fail( deferred.reject );

		return deferred.promise();
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
				instanceid : self.osid,
				project    : self.project,
				region     : self.region,
				token      : mw.user.tokens.get( 'editToken' ),
				subaction  : subaction
			}, params ) );

		if ( messages !== undefined ) {
			req.then(
				this.succeed.bind( this, subaction, this.name ),
				this.fail.bind( this, subaction, this.name )
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
			$row = $el.closest( 'tr' ),
			instance = new mw.openStack.Instance( $el.data(), $row );

		if ( !$.isFunction( instance[action] ) ) {
			// This action isn't supported!
			return;
		}

		e.preventDefault();

		$el.hide().after( $spinner );

		instance[action]().always( function () {
			$spinner.remove();
			$el.show();
		} );
	} );
} ( mediaWiki, jQuery ) );
