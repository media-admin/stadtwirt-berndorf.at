import { useEffect, useState } from '@wordpress/element';
import CF7AppsSkeletonLoader from './CF7AppsSkeletonLoader';
import { Tooltip } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const CF7AppsHeader = () => {
	const [ isLoading, setIsLoading ] = useState( true );

	useEffect( () => {
		const timer = setTimeout( () => {
			setIsLoading( false );
		}, 500 );

		return () => clearTimeout( timer );
	}, [] );

	const assetsBase = CF7AppsInternalSettings?.assetsURL || '';

	return (
		<div className="cf7apps-header-wrap cf7apps-form-editor-header-wrap">
			{ isLoading ? (
				<div>
					<CF7AppsSkeletonLoader count={ 1 } height={ 48 } />
				</div>
			) : (
				<div className="cf7apps-header cf7apps-form-editor-header">
					<div className="cf7apps-header-inner">
						<div className="cf7apps-header-left">
							<span className="cf7apps-header-logo" title={ __( 'CF7 Apps', 'cf7apps' ) }>
								{ assetsBase ? (
									<img
										src={ `${ assetsBase }images/logo.png` }
										alt={ __( 'CF7 Apps', 'cf7apps' ) }
										className="cf7apps-header-logo-img"
										height={ 36 }
										decoding="async"
									/>
								) : (
									<span className="cf7apps-nav-logo-text">CF7 Apps</span>
								) }
							</span>
						</div>

						<div className="cf7apps-header-right">
							<Tooltip text={ __( 'View documentation', 'cf7apps' ) } position="bottom">
								<button
									type="button"
									className="cf7apps-header-icon-button cf7apps-form-editor-header__doc"
									aria-label={ __( 'View documentation', 'cf7apps' ) }
									onClick={ () =>
										window.open(
											'https://cf7apps.com/docs/?utm_source=plugin&utm_medium=header&utm_campaign=documentation',
											'_blank'
										)
									}
								>
									<img
										src={ `${ assetsBase }images/book.svg` }
										alt={ __( 'Documentation', 'cf7apps' ) }
										className="cf7apps-header-icon-img cf7apps-header-doc-icon"
										width={ 20 }
										height={ 17 }
										decoding="async"
									/>
								</button>
							</Tooltip>

							{ CF7AppsInternalSettings?.pluginVersion && (
								<span className="cf7apps-header-version cf7apps-form-editor-header__version">
									V { CF7AppsInternalSettings.pluginVersion }
								</span>
							) }
						</div>
					</div>
				</div>
			) }
		</div>
	);
};

export default CF7AppsHeader;
