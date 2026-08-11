import CF7AppsNotice from '../../../../../src/components/CF7AppsNotice';

const CF7AppsFormNotice = ( { type = 'info', text = '' } ) => {
	const formEditorAssets =
		CF7AppsInternalSettings?.formEditorAssetsURL ||
		`${ CF7AppsInternalSettings?.pluginURL || '' }includes/apps/cf7-internal-settings/assets/images/form-editor/`;

	if ( type === 'info' && formEditorAssets ) {
		return (
			<div className="cf7apps-notice cf7apps-notice-info cf7apps-form-notice">
				<p>
					<img
						src={ `${ formEditorAssets }info-icon.svg` }
						alt=""
						width={ 14 }
						height={ 14 }
						className="cf7apps-form-notice__icon"
					/>
					<span dangerouslySetInnerHTML={ { __html: text } } />
				</p>
			</div>
		);
	}

	return <CF7AppsNotice type={ type } text={ text } />;
};

export default CF7AppsFormNotice;
