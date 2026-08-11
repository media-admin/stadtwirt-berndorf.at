import { useCallback, useEffect, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { toast } from 'react-toastify';
import CF7AppsSkeletonLoader from './CF7AppsSkeletonLoader';
import {
	getRecommendedPlugins,
	installActivateRecommendedPlugin,
} from '../api/api';

const PROMO_COPY = {
	'post-smtp': {
		titleClass: 'cf7apps-recommended-plugin__title--smtp',
		description: __(
			'Fix Your WordPress Email Delivery Issues with Post SMTP',
			'cf7apps'
		),
	},
	'password-protected': {
		titleClass: 'cf7apps-recommended-plugin__title--pp',
		description: __(
			'Protect your WordPress Site Content in Minutes with Password Protected',
			'cf7apps'
		),
	},
};

const CF7AppsRecommendedPlugins = () => {
	const [ isLoading, setIsLoading ] = useState( true );
	const [ plugins, setPlugins ] = useState( [] );
	const [ busySlug, setBusySlug ] = useState( '' );

	const loadPlugins = useCallback( async () => {
		setIsLoading( true );
		const data = await getRecommendedPlugins();
		setPlugins( Array.isArray( data ) ? data : [] );
		setIsLoading( false );
	}, [] );

	useEffect( () => {
		loadPlugins();
	}, [ loadPlugins ] );

	const handleAction = async ( plugin ) => {
		if ( plugin.status === 'active' || ! plugin.can_install ) {
			return;
		}

		setBusySlug( plugin.slug );

		const result = await installActivateRecommendedPlugin( plugin.slug );

		setBusySlug( '' );

		if ( ! result || result.error ) {
			toast.error(
				result?.error ||
					__( 'Could not install or activate the plugin. Please try again.', 'cf7apps' )
			);
			return;
		}

		setPlugins( result.data );
		toast.success(
			plugin.status === 'installed'
				? __( 'Plugin activated successfully.', 'cf7apps' )
				: __( 'Plugin installed and activated successfully.', 'cf7apps' )
		);
		if ( plugin.redirect_url ) {
			setTimeout( () => {
				window.location.href = plugin.redirect_url;
			}, 450 );
			return;
		}
		setTimeout( () => window.location.reload(), 600 );
	};

	const visiblePlugins = plugins.filter( ( plugin ) => plugin.status !== 'active' );

	if ( ! isLoading && visiblePlugins.length === 0 ) {
		return null;
	}

	return (
		<section
			className="cf7apps-sidebar-block cf7apps-recommended-plugins"
			aria-label={ __( 'Supercharge Your Workflow', 'cf7apps' ) }
		>
			<h3 className="cf7apps-sidebar-block__title">
				{ __( 'Supercharge Your Workflow', 'cf7apps' ) }
			</h3>
			{ isLoading ? (
				<CF7AppsSkeletonLoader count={ 2 } height={ 88 } width="100%" />
			) : (
				visiblePlugins.map( ( plugin ) => {
					const promo = PROMO_COPY[ plugin.slug ] || {};

					return (
						<div key={ plugin.slug } className="cf7apps-recommended-plugin">
							<div className="cf7apps-recommended-plugin__header">
								<div className="cf7apps-recommended-plugin__brand">
									<div className="cf7apps-recommended-plugin__icon-wrap">
										<img
											className="cf7apps-recommended-plugin__icon"
											src={ plugin.icon_url }
											alt=""
											width={ 20 }
											height={ 20 }
										/>
									</div>
									<h4
										className={
											promo.titleClass
												? `cf7apps-recommended-plugin__title ${ promo.titleClass }`
												: 'cf7apps-recommended-plugin__title'
										}
									>
										{ plugin.title }
									</h4>
								</div>
								{ plugin.can_install ? (
									<button
										type="button"
										className="cf7apps-recommended-plugin__btn"
										disabled={ busySlug === plugin.slug }
										onClick={ () => handleAction( plugin ) }
									>
										{ busySlug === plugin.slug
											? __( 'Please wait…', 'cf7apps' )
											: plugin.button_label }
									</button>
								) : (
									<span className="cf7apps-recommended-plugin__btn cf7apps-recommended-plugin__btn--disabled">
										{ plugin.button_label }
									</span>
								) }
							</div>
							<p className="cf7apps-recommended-plugin__desc">
								{ promo.description || plugin.description }
							</p>
						</div>
					);
				} )
			) }
		</section>
	);
};

export default CF7AppsRecommendedPlugins;
