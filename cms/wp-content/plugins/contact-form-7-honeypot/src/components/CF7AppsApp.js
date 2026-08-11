import { useEffect, useRef, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { Link } from 'react-router';
import { saveSettings } from '../api/api';
import { toast } from 'react-toastify';
import { getAppCardTheme } from '../utils/appCardTheme';

const PAYMENT_PRO_UPGRADE_URL = 'https://cf7apps.com/pricing/';
/** Match default ToastContainer autoClose (CF7AppsToastNotification). */
const TOGGLE_NOTICE_MS = 4000;

const CF7AppsApp = ( { settings, onShowAcfNotice } ) => {
	const [ isEnabled, setIsEnabled ] = useState( false );
	const [ isToggleLocked, setIsToggleLocked ] = useState( false );
	const unlockTimerRef = useRef( null );

	const assetsBase = CF7Apps?.assetsURL || '';
	const theme = getAppCardTheme( settings, assetsBase );
	const crownIconUrl = assetsBase
		? `${ assetsBase }/images/dashboard/pro-crown.svg`
		: '';
	const settingsIconUrl = assetsBase
		? `${ assetsBase }/images/dashboard/card-settings.svg`
		: '';
	const settingsIconStyle = settingsIconUrl
		? {
				maskImage: `url(${ settingsIconUrl })`,
				WebkitMaskImage: `url(${ settingsIconUrl })`,
		  }
		: undefined;

	const SettingsIcon = () =>
		settingsIconUrl ? (
			<span
				className="cf7apps-app-card__settings-icon"
				style={ settingsIconStyle }
				aria-hidden="true"
			/>
		) : null;

	useEffect( () => {
		setIsEnabled( settings.is_enabled );
	}, [ settings.is_enabled ] );

	useEffect( () => {
		return () => {
			if ( unlockTimerRef.current ) {
				clearTimeout( unlockTimerRef.current );
			}
		};
	}, [] );

	const isProTeaser = Boolean( settings.is_pro_teaser );
	const upgradeUrl = settings.upgrade_url || PAYMENT_PRO_UPGRADE_URL;
	const titleImage = settings.title_image || '';
	const badgeIconMaskStyle = theme.badgeIconUrl
		? {
				maskImage: `url(${ theme.badgeIconUrl })`,
				WebkitMaskImage: `url(${ theme.badgeIconUrl })`,
		  }
		: undefined;

	const cardStyle = {
		'--cf7apps-card-border': theme.border,
		'--cf7apps-card-badge-bg': theme.badgeBg,
		'--cf7apps-card-badge-icon': theme.badgeIconColor,
		'--cf7apps-card-enabled-bg': theme.enabledBg,
	};

	const lockToggleUntilNoticeEnds = () => {
		setIsToggleLocked( true );

		if ( unlockTimerRef.current ) {
			clearTimeout( unlockTimerRef.current );
		}

		unlockTimerRef.current = setTimeout( () => {
			setIsToggleLocked( false );
			unlockTimerRef.current = null;
		}, TOGGLE_NOTICE_MS );
	};

	const switchApp = async () => {
		if ( isProTeaser ) {
			window.open( upgradeUrl, '_blank', 'noopener,noreferrer' );
			return;
		}

		if ( isToggleLocked ) {
			return;
		}

		if ( settings.requires_acf && ! settings.acf_available && ! isEnabled ) {
			if ( onShowAcfNotice ) {
				onShowAcfNotice();
			}
			return;
		}

		const enabling = ! isEnabled;

		setIsToggleLocked( true );

		const saved = await saveSettings( settings.id, {
			is_enabled: enabling,
		} );

		if ( ! saved ) {
			setIsToggleLocked( false );
			toast.error( __( 'Error! Something Went Wrong', 'cf7apps' ) );
			return;
		}

		setIsEnabled( enabling );

		toast.success(
			enabling
				? __( 'App enabled successfully.', 'cf7apps' )
				: __( 'App disabled successfully.', 'cf7apps' ),
			{ autoClose: TOGGLE_NOTICE_MS }
		);

		lockToggleUntilNoticeEnds();
	};

	const showSettings = settings.has_admin_settings && ! isProTeaser;
	const titleStyle = theme.titleColor ? { color: theme.titleColor } : undefined;

	const ProTag = () => (
		<span className="cf7apps-app-card__pro-tag">
			{ crownIconUrl && (
				<img
					className="cf7apps-app-card__pro-tag-icon"
					src={ crownIconUrl }
					alt=""
					width={ 12 }
					height={ 10 }
				/>
			) }
			<span className="cf7apps-app-card__pro-tag-label">
				{ __( 'PRO', 'cf7apps' ) }
			</span>
		</span>
	);

	const settingsControl = showSettings ? (
		<Link
			to={ `settings/${ settings.id }` }
			className="cf7apps-app-card__settings"
			aria-label={ __( 'Settings', 'cf7apps' ) }
		>
			<SettingsIcon />
		</Link>
	) : null;

	return (
		<article
			className={ `cf7apps-app cf7apps-app-${ settings.id }${
				isEnabled ? ' is-enabled' : ''
			}${ isProTeaser ? ' is-pro-teaser' : '' }` }
		>
			<div className="cf7apps-app-card" style={ cardStyle }>
				{ theme.decorationLayers?.length > 0 && (
					<div
						className="cf7apps-app-card__decor"
						aria-hidden="true"
					>
						{ theme.decorationLayers.map( ( src, index ) => (
							<img
								key={ src }
								className={
									isEnabled
										? 'cf7apps-app-card__decor-img is-active'
										: 'cf7apps-app-card__decor-img'
								}
								src={ src }
								alt=""
								style={ theme.decorLayerStyles?.[ index ] }
							/>
						) ) }
					</div>
				) }

				<div className="cf7apps-app-card__inner">
					<div className="cf7apps-app-card__header">
						<div className="cf7apps-app-card__badges">
							<span className="cf7apps-app-card__badge">
								{ theme.badgeIconUrl && (
									<span
										className="cf7apps-app-card__badge-icon"
										style={ {
											...badgeIconMaskStyle,
											width: `${
												theme.badgeIconStyle?.width ??
												theme.badgeIconStyle?.maxWidth ??
												10
											}px`,
											height: `${
												theme.badgeIconStyle?.height ??
												10
											}px`,
										} }
										aria-hidden="true"
									/>
								) }
								<span className="cf7apps-app-card__badge-label">
									{ theme.label }
								</span>
							</span>
							{ theme.isBeta && (
								<span className="cf7apps-app-card__beta-tag">
									{ theme.betaIconUrl && (
										<img
											className="cf7apps-app-card__beta-tag-icon"
											src={ theme.betaIconUrl }
											alt=""
											width={ 10 }
											height={ 10 }
											decoding="async"
										/>
									) }
									<span className="cf7apps-app-card__beta-tag-label">
										{ __( 'Beta', 'cf7apps' ) }
									</span>
								</span>
							) }
						</div>
						{ settingsControl }
					</div>

					<div className="cf7apps-app-card__content">
						{ titleImage ? (
							<img
								className="cf7apps-app-card__title-image"
								src={ titleImage }
								alt={ settings.title }
								height={ 18 }
								decoding="async"
							/>
						) : (
							<h3
								className="cf7apps-app-card__title"
								style={ titleStyle }
							>
								{ settings.title }
							</h3>
						) }
						<p className="cf7apps-app-card__desc">
							{ settings.description }
						</p>
					</div>

					<div className="cf7apps-app-card__footer">
						{ isProTeaser ? (
							<ProTag />
						) : (
							<>
								<button
									type="button"
									className={
										[
											'cf7apps-app-toggle',
											isEnabled ? 'is-on' : '',
											isToggleLocked ? 'is-loading' : '',
										]
											.filter( Boolean )
											.join( ' ' )
									}
									role="switch"
									aria-checked={ isEnabled }
									aria-busy={ isToggleLocked }
									aria-disabled={ isToggleLocked }
									disabled={ isToggleLocked }
									aria-label={
										isToggleLocked
											? __( 'Updating app…', 'cf7apps' )
											: isEnabled
												? __( 'Disable app', 'cf7apps' )
												: __( 'Enable app', 'cf7apps' )
									}
									onClick={ switchApp }
								>
									<span className="cf7apps-app-toggle__thumb">
										{ isToggleLocked && (
											<span
												className="cf7apps-app-toggle__spinner"
												aria-hidden="true"
											/>
										) }
									</span>
								</button>
								<span className="cf7apps-app-card__status-label">
									{ isEnabled
										? __( 'Enabled', 'cf7apps' )
										: __( 'Disabled', 'cf7apps' ) }
								</span>
							</>
						) }
					</div>
				</div>
			</div>
		</article>
	);
};

export default CF7AppsApp;
