/**
 * Data type options for the Webhook app: hide Form Data when Method is GET.
 *
 * @param {string|undefined} method POST / GET from form state
 * @param {Object} fullOptions Options map from settings (e.g. { json: 'JSON', form: 'Form Data' })
 * @return {Object} Filtered options
 */
export function getWebhookDataTypeOptions( method, fullOptions ) {
	if ( ! fullOptions || typeof fullOptions !== 'object' ) {
		return {};
	}
	const isGet = String( method || '' ).toUpperCase() === 'GET';
	if ( ! isGet ) {
		return { ...fullOptions };
	}
	const opts = { ...fullOptions };
	delete opts.form;
	return opts;
}
