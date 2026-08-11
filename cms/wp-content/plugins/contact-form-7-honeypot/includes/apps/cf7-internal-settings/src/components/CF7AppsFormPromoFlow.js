const getPromoAsset = ( file ) => {
	const base = CF7AppsInternalSettings?.formPromoAssetsURL || '';
	return `${ base }${ file }`;
};

/**
 * Promo flow illustration — transparent composite from design reference.
 */
const CF7AppsFormPromoFlow = () => (
	<div className="cf7apps-form-promo__flow" aria-hidden="true">
		<img
			className="cf7apps-form-promo__flow-illustration"
			src={ getPromoAsset( 'flow-illustration.png' ) }
			alt=""
			width={ 188 }
			height={ 49 }
		/>
	</div>
);

export default CF7AppsFormPromoFlow;
