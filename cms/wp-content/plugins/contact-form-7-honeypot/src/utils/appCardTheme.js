import { __ } from '@wordpress/i18n';

const PAYMENT_APP_IDS = ['stripe', 'paypal', 'square'];

/** Map parent theme keys to translatable labels (msgids must stay English). */
const getParentThemeLabel = ( parentKey ) => {
	const labels = {
		general: __( 'General', 'cf7apps' ),
		'spam-protection': __( 'Spam Protection', 'cf7apps' ),
		integrations: __( 'Integrations', 'cf7apps' ),
		integration: __( 'Integrations', 'cf7apps' ),
		payments: __( 'Payment', 'cf7apps' ),
		'payment-gate': __( 'Payment', 'cf7apps' ),
		payment: __( 'Payment', 'cf7apps' ),
	};

	return labels[ parentKey ] || labels.general;
};


export const getCF7AppsAssetsURL = () => {
	if (typeof CF7Apps !== 'undefined' && CF7Apps?.assetsURL) {
		return CF7Apps.assetsURL;
	}
	if (
		typeof CF7AppsInternalSettings !== 'undefined' &&
		CF7AppsInternalSettings?.assetsURL
	) {
		return CF7AppsInternalSettings.assetsURL;
	}
	return '';
};

export const getCf7AppsRuntime = () => {
	if ( typeof CF7AppsInternalSettings !== 'undefined' ) {
		return {
			restURL: CF7AppsInternalSettings.restURL || '',
			nonce: CF7AppsInternalSettings.nonce || '',
		};
	}

	if ( typeof CF7Apps !== 'undefined' ) {
		return {
			restURL: CF7Apps.restURL || '',
			nonce: CF7Apps.nonce || '',
		};
	}

	return {
		restURL: '',
		nonce: '',
	};
};

const TOOLBAR_ICON = {
	general: 'filter-general.svg',
	'spam-protection': 'filter-spam.svg',
	integrations: 'filter-integrations.svg',
	integration: 'filter-integrations.svg',
	payments: 'filter-payments.svg',
	'payment-gate': 'filter-payments.svg',
};

const PARENT_THEMES = {
	general: {
		badgeBg: '#e9f0ff',
		border: '#4f6797',
		badgeIcon: TOOLBAR_ICON.general,
	},
	'spam-protection': {
		badgeBg: '#fff7e2',
		border: '#a27700',
		badgeIcon: TOOLBAR_ICON['spam-protection'],
	},
	integrations: {
		badgeBg: '#ffeee9',
		border: '#c33810',
		badgeIcon: TOOLBAR_ICON.integrations,
	},
	integration: {
		badgeBg: '#ffeee9',
		border: '#c33810',
		badgeIcon: TOOLBAR_ICON.integration,
	},
	payments: {
		badgeBg: '#eae6ff',
		border: '#533afd',
		badgeIcon: TOOLBAR_ICON.payments,
	},
	'payment-gate': {
		badgeBg: '#eae6ff',
		border: '#533afd',
		badgeIcon: TOOLBAR_ICON['payment-gate'],
	},
};

const APP_BORDER_OVERRIDES = {
	'cf7-redirection': '#208aae',
	'cf7-entries': '#4f6797',
	honeypot: '#a27700',
	hcaptcha: '#417662',
	webhook: '#c33810',
	'acf-integration': '#208aae',
	'cf7-ai-form-generator': '#4f6797',
	stripe: '#533afd',
	paypal: '#003087',
	square: '#1a1a1a',
};

const APP_BADGE_OVERRIDES = {
	'cf7-redirection': '#edfbfc',
	hcaptcha: '#f0f7ec',
	'acf-integration': '#edfbfc',
	'cf7-ai-form-generator': '#e9f0ff',
	stripe: '#eae6ff',
	paypal: '#dfefff',
	square: '#f2f2f2',
};

const APP_TITLE_COLORS = {
	stripe: '#533afd',
	paypal: '#003087',
	square: '#1a1a1a',
};

const APP_BETA_IDS = ['cf7-ai-form-generator'];

/** Badge icon dimensions (px). */
const APP_BADGE_ICON_STYLE = {
	general: { width: 7.3, height: 10 },
	stripe: { width: 10, height: 7 },
	paypal: { width: 10, height: 7 },
	square: { width: 10, height: 7 },
};

const APP_BADGE_ICON_STYLE_BY_APP = {
	'cf7-redirection': APP_BADGE_ICON_STYLE.general,
	'cf7-entries': APP_BADGE_ICON_STYLE.general,
};

/** Variant2 — full-card tint when toggle is on (per app/category). */
const APP_ENABLED_BG = {
	'cf7-redirection': '#edfbfc',
	'cf7-entries': '#e9f0ff',
	honeypot: '#fff7e2',
	hcaptcha: '#f0f7ec',
	webhook: '#ffeee9',
	'acf-integration': '#edfbfc',
	'cf7-ai-form-generator': '#e9f0ff',
	stripe: '#f5f3ff',
	paypal: '#dfefff',
	square: '#f7f7f7',
};

const APP_DECORATIONS = {
	'cf7-redirection': ['redirection.svg'],
	'cf7-entries': ['entries.svg'],
	honeypot: ['honeypot.svg'],
	hcaptcha: ['hcaptcha.svg'],
	webhook: ['webhook.svg'],
	'acf-integration': ['acf.svg'],
	'cf7-ai-form-generator': ['ai-form.svg'],
	stripe: ['stripe.svg'],
	paypal: ['paypal.svg'],
	square: ['square.svg'],
};

/**
 * App card 423×180px — decor zone + illustration size/position (px).
 * @see nodes 3837:13902, 3848:5646, 3848:6077, etc.
 */
const APP_DECOR_LAYOUT = {
	'cf7-redirection': {
		layers: [{ maxWidth: 102, maxHeight: 102, bottomCut: 10 }],
	},
	'cf7-entries': {
		layers: [{ maxWidth: 100, maxHeight: 100, bottomCut: 10 }],
	},
	honeypot: {
		layers: [{ maxWidth: 98, maxHeight: 104, bottomCut: 10 }],
	},
	hcaptcha: {
		layers: [{ maxWidth: 104, maxHeight: 102, bottomCut: 10 }],
	},
	webhook: {
		layers: [{ maxWidth: 102, maxHeight: 100, bottomCut: 10 }],
	},
	'acf-integration': {
		layers: [{ maxWidth: 100, maxHeight: 98, bottomCut: 10 }],
	},
	'cf7-ai-form-generator': {
		layers: [{ maxWidth: 104, maxHeight: 102, bottomCut: 10 }],
	},
	stripe: {
		layers: [{ maxWidth: 102, maxHeight: 108, bottomCut: 14 }],
	},
	paypal: {
		layers: [{ maxWidth: 100, maxHeight: 108, bottomCut: 16 }],
	},
	square: {
		layers: [{ maxWidth: 100, maxHeight: 108, bottomCut: 16 }],
	},
};

const layerStyleFromConfig = (layer) => {
	const style = {
		width: 'auto',
		height: 'auto',
		maxWidth: `${layer.maxWidth ?? 100}%`,
		maxHeight: `${layer.maxHeight ?? 102}%`,
		marginRight: `${layer.marginRight ?? -2}px`,
		marginBottom: `-${layer.bottomCut ?? 10}px`,
	};

	if (layer.flip) {
		style.transform = 'scaleX(-1)';
	}

	return style;
};

export const normalizeParentMenu = (parentMenu) =>
	String(parentMenu || '')
		.toLowerCase()
		.replace(/\s+/g, '-');

export const isPaymentApp = (app) => {
	const normalized = normalizeParentMenu(app?.parent_menu);

	return (
		PAYMENT_APP_IDS.includes(app?.id) ||
		normalized === 'payment' ||
		normalized === 'payments' ||
		normalized === 'payment-gate'
	);
};

export const resolveCategoryKey = (app) => {
	if (isPaymentApp(app)) {
		return 'payments';
	}

	return normalizeParentMenu(app?.parent_menu);
};

export const getAppCardTheme = (settings, assetsBase = '') => {
	const parentKey = resolveCategoryKey(settings);
	const parentTheme =
		PARENT_THEMES[parentKey] || PARENT_THEMES.general;
	const decorFiles = APP_DECORATIONS[settings?.id];
	const layout = APP_DECOR_LAYOUT[settings?.id] || {};
	const decorationLayers =
		decorFiles && assetsBase
			? decorFiles.map(
				(file) => `${assetsBase}/images/dashboard/${file}`
			)
			: [];

	const layerConfigs = layout.layers || [];
	const decorLayerStyles = decorationLayers.map((_src, index) => {
		if (layerConfigs[index]) {
			return layerStyleFromConfig(layerConfigs[index]);
		}
		return layerStyleFromConfig({
			maxWidth: 90,
			maxHeight: 85,
			bottom: 0,
		});
	});

	const badgeIconUrl =
		parentTheme.badgeIcon && assetsBase
			? `${assetsBase}/images/dashboard/toolbar/${parentTheme.badgeIcon}`
			: '';

	const enabledBg =
		APP_ENABLED_BG[settings?.id] || parentTheme.badgeBg;

	const badgeBg =
		APP_BADGE_OVERRIDES[settings?.id] || parentTheme.badgeBg;

	const betaIconUrl = assetsBase
		? `${assetsBase}/images/dashboard/beta-icon.svg`
		: '';

	const badgeIconStyle =
		APP_BADGE_ICON_STYLE_BY_APP[settings?.id] ||
		APP_BADGE_ICON_STYLE[settings?.id] ||
		APP_BADGE_ICON_STYLE[parentKey] || {
			width: 10,
			height: 10,
		};

	const border =
		APP_BORDER_OVERRIDES[settings?.id] || parentTheme.border;

	return {
		...parentTheme,
		label: getParentThemeLabel( parentKey ),
		badgeBg,
		border,
		badgeIconColor: border,
		enabledBg,
		titleColor: APP_TITLE_COLORS[settings?.id] || '',
		isBeta: APP_BETA_IDS.includes(settings?.id),
		betaIconUrl,
		badgeIconUrl,
		badgeIconStyle,
		decorationLayers,
		decorLayerStyles,
	};
};

export const getAppSettingsHeaderIcon = ( settings, assetsBase = '' ) => {
	const decorFile = APP_DECORATIONS[ settings?.id ]?.[ 0 ];

	if ( decorFile && assetsBase ) {
		return `${ assetsBase }/images/dashboard/${ decorFile }`;
	}

	return settings?.icon || '';
};

export const getAppSettingsPanelStyle = ( settings, assetsBase = '' ) => {
	const theme = getAppCardTheme(settings, assetsBase);

	return {
		'--cf7apps-settings-accent': theme.border,
		'--cf7apps-settings-accent-soft': theme.badgeBg,
		'--cf7apps-settings-accent-surface': theme.enabledBg,
		'--cf7apps-settings-badge-icon': theme.badgeIconColor,
		'--cf7apps-card-border': theme.border,
		'--cf7apps-card-badge-bg': theme.badgeBg,
		'--cf7apps-card-badge-icon': theme.badgeIconColor,
		...(theme.titleColor
			? { '--cf7apps-settings-title-color': theme.titleColor }
			: {}),
	};
};

const FORM_EDITOR_PAYMENT_THEME = {
	'--cf7apps-settings-accent': '#208aae',
	'--cf7apps-settings-accent-soft': '#eefbff',
	'--cf7apps-settings-accent-surface': '#edfbfc',
	'--cf7apps-settings-menu-active-bg': '#edfbfc',
};

const FORM_EDITOR_THEME_OVERRIDES = {
	payment: FORM_EDITOR_PAYMENT_THEME,
	payments: FORM_EDITOR_PAYMENT_THEME,
	'payment-gate': FORM_EDITOR_PAYMENT_THEME,
	'spam-protection': {
		'--cf7apps-settings-menu-active-bg': '#fff7e2',
	},
	general: {
		'--cf7apps-settings-menu-active-bg': '#e9f0ff',
	},
	integrations: {
		'--cf7apps-settings-menu-active-bg': '#ffeee9',
	},
	integration: {
		'--cf7apps-settings-menu-active-bg': '#ffeee9',
	},
};

export const getFormEditorSettingsPanelStyle = (settings, assetsBase = '') => {
	const base = getAppSettingsPanelStyle(settings, assetsBase);
	const parentKey = normalizeParentMenu(settings?.parent_menu);
	const overrides =
		FORM_EDITOR_THEME_OVERRIDES[parentKey] || FORM_EDITOR_PAYMENT_THEME;

	return {
		...base,
		...overrides,
	};
};
