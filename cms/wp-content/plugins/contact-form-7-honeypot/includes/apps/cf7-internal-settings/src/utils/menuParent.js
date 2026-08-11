import { __ } from '@wordpress/i18n';

export const parentMenuToSlug = ( menu ) =>
	String( menu || '' )
		.toLowerCase()
		.replace( /\s+/g, '-' );

const PARENT_MENU_ICONS_BY_SLUG = {
	general: 'menu-general.svg',
	'spam-protection': 'menu-spam.svg',
	integration: 'menu-integrations.svg',
	integrations: 'menu-integrations.svg',
	payment: 'menu-payment.svg',
	payments: 'menu-payment.svg',
	'payment-gate': 'menu-payment.svg',
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

export const isPaymentParentMenu = ( menu ) => {
	const slug = parentMenuToSlug( menu );

	return (
		slug === 'payment' ||
		slug === 'payments' ||
		slug === 'payment-gate'
	);
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
