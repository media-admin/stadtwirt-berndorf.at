import { createElement } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

export const parentMenuToSlug = ( menu ) =>
	String( menu || '' )
		.toLowerCase()
		.replace( /\s+/g, '-' );

const PARENT_MENU_ICONS_BY_SLUG = {
	general: 'filter-general.svg',
	'spam-protection': 'filter-spam.svg',
	integration: 'filter-integrations.svg',
	integrations: 'filter-integrations.svg',
	payment: 'filter-payments.svg',
	payments: 'filter-payments.svg',
	'payment-gate': 'filter-payments.svg',
};

export const isPaymentParentMenu = ( menu ) => {
	const slug = parentMenuToSlug( menu );

	return (
		slug === 'payment' ||
		slug === 'payments' ||
		slug === 'payment-gate'
	);
};

export const getParentMenuIconFile = ( menu ) => {
	if ( isPaymentParentMenu( menu ) ) {
		return PARENT_MENU_ICONS_BY_SLUG.payment;
	}

	const slug = parentMenuToSlug( menu );

	return PARENT_MENU_ICONS_BY_SLUG[ slug ] || null;
};

export const getParentMenuLabel = ( menu ) => {
	const slug = parentMenuToSlug( menu );

	const labels = {
		general: __( 'General', 'cf7apps' ),
		'spam-protection': __( 'Spam Protection', 'cf7apps' ),
		integration: __( 'Integrations', 'cf7apps' ),
		integrations: __( 'Integrations', 'cf7apps' ),
		payment: __( 'Payment', 'cf7apps' ),
		payments: __( 'Payment', 'cf7apps' ),
		'payment-gate': __( 'Payment', 'cf7apps' ),
	};

	if ( labels[ slug ] ) {
		return labels[ slug ];
	}

	if ( isPaymentParentMenu( menu ) ) {
		return __( 'Payment', 'cf7apps' );
	}

	return menu;
};

export const getParentHeadingClassName = ( menu ) => {
	const slug = parentMenuToSlug( menu );
	const classes = [ 'cf7apps-menu-heading' ];

	if ( slug ) {
		classes.push( `cf7apps-menu-heading--${ slug }` );
	}

	return classes.join( ' ' );
};

export const getParentMenuIcon = ( menu, iconOnly = false ) => {
	const iconFile = getParentMenuIconFile( menu );
	const assetsBase =
		typeof CF7Apps !== 'undefined' ? CF7Apps?.assetsURL || '' : '';

	if ( ! iconFile || ! assetsBase ) {
		return null;
	}

	const size = iconOnly ? 18 : 18;

	return createElement( 'img', {
		className: 'cf7apps-menu-parent-icon',
		src: `${ assetsBase }/images/dashboard/toolbar/${ iconFile }`,
		alt: '',
		width: size,
		height: size,
	} );
};

export const slugMatchesParentMenu = ( slug, menu ) => {
	const menuSlug = parentMenuToSlug( menu );

	if ( ! slug || ! menuSlug ) {
		return false;
	}

	if ( slug === menuSlug ) {
		return true;
	}

	if ( slug === 'integrations' && menuSlug === 'integration' ) {
		return true;
	}

	if (
		slug === 'payments' &&
		( menuSlug === 'payment' ||
			menuSlug === 'payments' ||
			menuSlug === 'payment-gate' )
	) {
		return true;
	}

	return false;
};

export const getParentNavPath = ( menu ) => {
	const slug = parentMenuToSlug( menu );

	return slug === 'general' ? '/' : `/${ slug }`;
};

export const getActiveParentMenu = ( location, menuItems ) => {
	const path = location?.pathname || '/';

	const settingsMatch = path.match( /^\/settings\/([^/]+)/ );

	if ( settingsMatch ) {
		const appId = settingsMatch[ 1 ];

		for ( const [ parent, routes ] of Object.entries( menuItems || {} ) ) {
			if ( routes && Object.prototype.hasOwnProperty.call( routes, appId ) ) {
				return parent;
			}
		}
	}

	const parts = path.split( '/' ).filter( Boolean );

	if ( parts.length === 1 && parts[ 0 ] !== 'settings' ) {
		const found = Object.keys( menuItems || {} ).find( ( menu ) =>
			slugMatchesParentMenu( parts[ 0 ], menu )
		);

		if ( found ) {
			return found;
		}
	}

	if ( path === '/' || path === '' ) {
		return 'general';
	}

	return null;
};

export const isParentMenuActive = ( menu, activeParent ) =>
	Boolean( activeParent && menu === activeParent );
