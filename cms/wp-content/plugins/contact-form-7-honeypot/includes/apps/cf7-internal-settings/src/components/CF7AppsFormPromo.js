import { useCallback, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { toast } from 'react-toastify';
import CF7AppsFormPromoFlow from './CF7AppsFormPromoFlow';
import {
	getRecommendedPlugins,
	installActivateRecommendedPlugin,
} from '../api';

const POST_SMTP_SLUG = 'post-smtp';

const getPromoAsset = ( file ) => {
	const base = CF7AppsInternalSettings?.formPromoAssetsURL || '';
	return `${ base }${ file }`;
};

const CF7AppsFormPromo = () => {
	const [ isLoading, setIsLoading ] = useState( true );
	const [ plugin, setPlugin ] = useState( null );
	const [ busy, setBusy ] = useState( false );

	const loadPlugin = useCallback( async () => {
		setIsLoading( true );

		try {
			const data = await getRecommendedPlugins();
			const entry = Array.isArray( data )
				? data.find( ( item ) => item.slug === POST_SMTP_SLUG )
				: null;
			setPlugin( entry || null );
		} finally {
			setIsLoading( false );
		}
	}, [] );

	useEffect( () => {
		loadPlugin();
	}, [ loadPlugin ] );

	const handleInstall = async () => {
		if ( ! plugin || plugin.status === 'active' || ! plugin.can_install ) {
			return;
		}

		setBusy( true );

		const result = await installActivateRecommendedPlugin( POST_SMTP_SLUG );

		setBusy( false );

		if ( ! result || result.error ) {
			toast.error(
				result?.error ||
					__( 'Could not install Post SMTP. Please try again.', 'cf7apps' )
			);
			return;
		}

		const updated = Array.isArray( result.data )
			? result.data.find( ( item ) => item.slug === POST_SMTP_SLUG )
			: null;

		setPlugin( updated || null );
		toast.success(
			plugin.status === 'installed'
				? __( 'Post SMTP activated successfully.', 'cf7apps' )
				: __( 'Post SMTP installed and activated successfully.', 'cf7apps' )
		);

		if ( plugin.redirect_url ) {
			setTimeout( () => {
				window.location.href = plugin.redirect_url;
			}, 450 );
		}
	};

	if ( isLoading ) {
		return (
			<aside className="cf7apps-form-promo" aria-hidden="true">
				<div className="cf7apps-form-promo__shell">
					<div className="cf7apps-form-promo__card cf7apps-form-promo__card--loading" />
				</div>
			</aside>
		);
	}

	if ( ! plugin || plugin.status === 'active' ) {
		return null;
	}

	const buttonLabel =
		plugin.status === 'installed'
			? __( 'Activate Post SMTP', 'cf7apps' )
			: __( 'Install Post SMTP Now', 'cf7apps' );

	return (
		<aside
			className="cf7apps-form-promo"
			aria-label={ __( 'Post SMTP promotion', 'cf7apps' ) }
		>
			<div className="cf7apps-form-promo__shell">
				<article className="cf7apps-form-promo__card">
					<div className="cf7apps-form-promo__gradient">
						<div className="cf7apps-form-promo__stage">
							<img
								className="cf7apps-form-promo__header"
								src={ getPromoAsset( 'promo-header.png' ) }
								width={ 264 }
								height={ 196 }
								alt={ __(
									'Post SMTP - Fix Contact Form 7 not sending emails error via Post SMTP',
									'cf7apps'
								) }
							/>

							<CF7AppsFormPromoFlow />

							{ plugin.can_install ? (
								<button
									type="button"
									className="cf7apps-form-promo__btn"
									disabled={ busy }
									onClick={ handleInstall }
								>
									{ busy
										? __( 'Please wait…', 'cf7apps' )
										: buttonLabel }
								</button>
							) : (
								<span className="cf7apps-form-promo__btn cf7apps-form-promo__btn--disabled">
									{ buttonLabel }
								</span>
							) }
						</div>
					</div>
				</article>
			</div>
		</aside>
	);
};

export default CF7AppsFormPromo;
