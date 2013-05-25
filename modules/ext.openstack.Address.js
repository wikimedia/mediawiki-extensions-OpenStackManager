( function ( mw, $ ) {
	'use strict';

	var api = new mw.Api();

	/**
	 * @class mw.openStack.Address
	 * Represents an OpenStack Address
	 * @param {string} address
	 * @param {string} region
	 * @param {string} project
	 * @extends mw.OpenStackInterface
	 */
	function Address( ipAddr, addressId, region, project, $row ) {
		this.ipAddr = ipAddr;
		this.addressId = addressId;
		this.region = region;
		this.project = project;
		this.$row = $row;
	}

	Address.prototype = new mw.OpenStackInterface();

	var ADDP = Address.prototype;

	/**
	 * @property {Object} notifications Message names for success and failure for different actions
	 */
	ADDP.notifications = {
		disassociate: {
			success: 'openstackmanager-disassociatedaddress',
			failure: 'openstackmanager-disassociateaddressfailed'
		}
	};

	/**
	 * Change a link from one state to another.
	 * @param {jQuery} $link
	 * @param {string} fromClass
	 * @param {string} toClass
	 * @param {string} linkText Name of a message to use as the new link text
	 * @param {string} action
	 */
	ADDP.changeLink = function ( fromClass, toClass, linkText, action ) {
		var $link = this.$row.find( '.' + fromClass );
		$link.removeClass( fromClass );
		$link.addClass( toClass );
		$link.text( mw.msg( linkText ) );
		$link.attr( 'href',
			mw.config.get( 'wgScript' ) + '?' +
			$.param( {
				title: 'Special:NovaAddress',
				action: action,
				id: this.addressId,
				project: this.project,
				region: this.region
			} )
		);

	};

	/**
	 * Mark an address as being disassociated from any instance.
	 *
	 * @param {string} actionClass Name of the class on the link that instigated this action.
	 * @param {Object} data The response from the server that we may need to handle.
	 */
	ADDP.markEmpty = function ( actionClass, data ) {
		var $rowCache = this.$row;

		if ( data.error ) {
			this.notifyError( actionClass, data.error.code || 'openstackmanager-disassociateaddressfailed' );
			return;
		}

		$rowCache.find( '.instance-id' ).empty();
		$rowCache.find( '.instance-name' ).empty();

		this.changeLink(
			'disassociate-link',
			'associate-link',
			'openstackmanager-associateaddress',
			'associate'
		);

		this.changeLink(
			'reassociate-link',
			'release-link',
			'openstackmanager-releaseaddress',
			'release'
		);
	};

	/**
	 * Notify the user that some action they performed caused an error.
	 * @param {string} actionClass
	 * @param {string} errorMsg Name of a message to put in as the error.
	 */
	ADDP.notifyError = function ( actionClass, errorMsg ) {
		var $rowCache = this.$row,
			$link = $rowCache.find( '.' + actionClass ),
			$error = $( '<span>' );

		errorMsg = errorMsg || 'openstackmanager-unknownerror';

		$error.addClass( 'error' );
		$error.text( mw.msg( errorMsg ) );

		$link.after( $error );
	};

	/**
	 * Make an 'action=novaaddress' call to the MediaWiki API specifying this
	 * instance and the requested subaction. If notification messages are
	 * configured for this subaction, issues the appropriate notification upon
	 * success/failure.
	 * @param {string} subaction Value for 'subaction' parameter.
	 * @param {string} classname The class of the link where this action originated
	 * @param {Object} params Additional parameters, if any.
	 * @return {jQuery.promise}
	 */
	ADDP.api = function ( subaction, classname, params ) {
		var messages = this.notifications[subaction],
			req = api.post( $.extend( {
				action: 'novaaddress',
				project: this.project,
				id: this.addressId,
				region: this.region,
				subaction: subaction
			}, params ) );

		if ( messages !== undefined ) {
			req.then(
				this.succeed.bind( this, subaction, this.ipAddr ),
				this.fail.bind( this, subaction, this.ipAddr )
			);
		}

		req.then(
			this.markEmpty.bind( this, classname ),
			this.notifyError.bind( this, classname )
		);

		return req;
	};

	/**
	 * Disassociate this IP address from its project.
	 * @param {Object} params
	 * @returns {jQuery.promise}
	 */
	ADDP.disassociate = function ( params ) {
		var promise = new $.Deferred();

		this.confirm(
			mw.msg( 'openstackmanager-disassociateaddress-confirm', this.ipAddr ),
			function () {
				this.api( 'disassociate', 'disassociate-link', params )
					.always( promise.resolve );
			}.bind( this ),
			function () {
				promise.reject();
			}
		);

		return promise.promise();
	};

	mw.openStack.Address = Address;

	// TODO: Make this a more general thing and put it in the base module maybe
	$( '.novaaddressaction' ).on( 'click', function ( e ) {
		var action = mw.util.getParamValue( 'action', this.href ),
			$el = $( this ),
			$row = $el.closest( 'tr' ),
			$spinner = $.createSpinner(),
			data = $el.data(),
			address = new mw.openStack.Address( data.ip, data.id, data.region, data.project, $row );

		if ( address[action] === undefined || !( address[action] instanceof Function ) ) {
			// This action isn't supported!
			return;
		}

		e.preventDefault();

		$el.hide().after( $spinner );

		address[action]().always( function () {
			$spinner.remove();
			$el.show();
		} );
	} );
} ( mediaWiki, jQuery ) );
