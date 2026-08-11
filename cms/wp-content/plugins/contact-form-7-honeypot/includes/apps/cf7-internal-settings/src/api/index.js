function cf7appsFetch( url, options ) {
    options = options || {};
    options.headers = options.headers || {};
    options.headers['X-WP-Nonce'] = CF7AppsInternalSettings.nonce;

    if ( options.body && ! options.headers['Content-Type'] ) {
        options.headers['Content-Type'] = 'application/json';
    }

    let base = CF7AppsInternalSettings.restURL || '';

    const isPlain = base.indexOf( 'rest_route=' ) !== -1;

    let requestUrl;
    if ( isPlain ) {
        // Normalize base so rest_route always ends with a single slash.
        const [prefix, restParam] = base.split( 'rest_route=' );
        let route = restParam || '/';
        if ( ! route.startsWith( '/' ) ) {
            route = '/' + route;
        }
        if ( ! route.endsWith( '/' ) ) {
            route = route + '/';
        }

        const restBase = `${ prefix }rest_route=${ route }cf7apps/v1/`;

        const [endpoint, query] = url.split( '?' );
        requestUrl = restBase + endpoint + ( query ? `&${ query }` : '' );
    } else {
        // Pretty permalinks: just ensure trailing slash and append as usual.
        if ( ! base.endsWith( '/' ) ) {
            base += '/';
        }
        requestUrl = `${ base }cf7apps/v1/${ url }`;
    }

    return fetch( requestUrl, options );
}

export async function getMenu() {
    const response = await cf7appsFetch( 'get-menu-items?menu-for=internal-settings', {
        method: 'GET',
    } );

    if ( ! response.ok ) {
        return false;
    }

    const json = await response.json();

    return json.data;
}

export async function getApps( app, formId ) {
    const response = await cf7appsFetch( `get-apps/${ app }?settings-for=internal-settings&form-id=${ formId }`, {
        method: 'GET'
    } );

    if ( ! response.ok ) {
        return false;
    }

    const json = await response.json();

    return json.data;
}

export async function saveSettings( app, formData, formId ) {

    const response = await cf7appsFetch( `save-app-settings`, {
        method: 'POST',
        body: JSON.stringify( {
            id: app,
            form_id: formId,
            app_settings: formData,
            'settings-for': 'internal-settings'
        } ),
    } );

    if ( ! response.ok ) {
        return false;
    }

    const json = await response.json();

    return json.data;
}

export async function getRecommendedPlugins() {
    const response = await cf7appsFetch( 'recommended-plugins', {
        method: 'GET',
    } );

    if ( ! response.ok ) {
        return false;
    }

    const json = await response.json();

    return json.success ? json.data : false;
}

export async function installActivateRecommendedPlugin( slug ) {
    try {
        const response = await cf7appsFetch(
            'recommended-plugins/install-activate',
            {
                method: 'POST',
                body: JSON.stringify( { slug } ),
            }
        );
        const json = await response.json();

        if ( ! response.ok || ! json.success ) {
            return {
                error: json?.data?.message || 'Request failed.',
            };
        }

        return { data: json.data };
    } catch ( error ) {
        return { error: 'Request failed.' };
    }
}
