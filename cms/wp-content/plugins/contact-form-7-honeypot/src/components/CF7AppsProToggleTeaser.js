import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { getCF7AppsAssetsURL } from '../utils/appCardTheme';

const CF7AppsProToggleTeaser = () => {
	const [ showMore, setShowMore ] = useState( false );
	const assetsBase = getCF7AppsAssetsURL();
	const crownIcon = assetsBase
		? `${ assetsBase.replace( /\/$/, '' ) }/images/dashboard/pro-crown.svg`
		: '';
	const fullText = __(
		'Conditional redirection will redirect users based on specific form field values or submission conditions. Upgrade to PRO to unlock this feature.',
		'cf7apps'
	);
	const previewLength = 52;

	return (
		<div className="cf7apps-pro-toggle-teaser">
			<div className="cf7apps-pro-toggle-teaser__row">
				<div className="cf7apps-pro-toggle-teaser__label-wrap">
					<span className="cf7apps-pro-toggle-teaser__label">
						{ __( 'Conditional Redirection', 'cf7apps' ) }
					</span>
					<span className="cf7apps-pro-toggle-teaser__badge">
						{ crownIcon && (
							<img
								src={ crownIcon }
								alt=""
								width={ 12 }
								height={ 10 }
								aria-hidden="true"
							/>
						) }
						<span>{ __( 'PRO', 'cf7apps' ) }</span>
					</span>
				</div>
				<button
					type="button"
					className="cf7apps-settings-switch"
					role="switch"
					aria-checked={ false }
					aria-label={ __( 'Conditional Redirection', 'cf7apps' ) }
					disabled
				>
					<span className="cf7apps-settings-switch__track" aria-hidden="true" />
					<span className="cf7apps-settings-switch__knob" aria-hidden="true" />
				</button>
			</div>
			<p className="cf7apps-pro-toggle-teaser__help">
				{ showMore
					? fullText
					: `${ fullText.substring( 0, previewLength ) }... ` }
				<button
					type="button"
					className="cf7apps-show-more-text"
					onClick={ () => setShowMore( ! showMore ) }
				>
					{ showMore
						? __( 'Show Less', 'cf7apps' )
						: __( 'Show More', 'cf7apps' ) }
				</button>
			</p>
		</div>
	);
};

export default CF7AppsProToggleTeaser;
