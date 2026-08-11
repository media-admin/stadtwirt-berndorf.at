import { getCf7AppsRuntime } from '../utils/appCardTheme';

function getRestHeaders() {
    const runtime = getCf7AppsRuntime();

    return {
        'X-WP-Nonce': runtime.nonce,
        'Content-Type': 'application/json',
    };
}

function getCf7AppsRestUrl( endpoint ) {
    let base = getCf7AppsRuntime().restURL || '';
    const isPlain = base.indexOf( 'rest_route=' ) !== -1;

    if ( isPlain ) {
        const [prefix, restParam] = base.split( 'rest_route=' );
        let route = restParam || '/';
        if ( ! route.startsWith( '/' ) ) {
            route = '/' + route;
        }
        if ( ! route.endsWith( '/' ) ) {
            route += '/';
        }

        return `${prefix}rest_route=${route}cf7apps/v1/${endpoint}`;
    }

    if ( ! base.endsWith( '/' ) ) {
        base += '/';
    }
    return `${base}cf7apps/v1/${endpoint}`;
}

async function requestRecommendedEndpoint( endpoint, options ) {
    const normalized = endpoint.replace( /^\/+/, '' );
    const urls = [ getCf7AppsRestUrl( normalized ) ];

    if ( ! normalized.endsWith( '/' ) ) {
        urls.push( getCf7AppsRestUrl( `${normalized}/` ) );
    }

    const base = getCf7AppsRuntime().restURL || '';
    if ( base.includes( '/wp-json' ) ) {
        const plainBase = base.replace( /\/wp-json\/?$/, '/index.php?rest_route=/' );
        urls.push( `${plainBase}cf7apps/v1/${normalized}` );
        if ( ! normalized.endsWith( '/' ) ) {
            urls.push( `${plainBase}cf7apps/v1/${normalized}/` );
        }
    }

    let lastResponse = null;
    let lastJson = null;

    for ( const url of urls ) {
        const response = await fetch( url, options );
        let json = null;
        try {
            json = await response.json();
        } catch ( e ) {
            json = null;
        }

        if ( response.ok && json?.success ) {
            return { response, json };
        }

        lastResponse = response;
        lastJson = json;

        const isNoRoute = response.status === 404 && json?.code === 'rest_no_route';
        if ( ! isNoRoute ) {
            break;
        }
    }

    return { response: lastResponse, json: lastJson };
}

/**
 * Fetches the menu items from the server.
 * 
 * @since 3.0.0
 */
export async function getMenu() {
    const response = await fetch(`${getCf7AppsRuntime().restURL}cf7apps/v1/get-menu-items`, {
        headers: getRestHeaders(),
    });

    if (!response.ok) {
        return false;
    }

    const json = await response.json();

    return json.data;
}

/**
 * Fetches the app settings from the server.
 * 
 * @param {string} id The ID of the app.
 * 
 * @since 3.0.0
 */
export async function getApps( id = '' ) {
    // Use REST route with optional /id param, e.g. .../get-apps or .../get-apps/123
    let url = `${getCf7AppsRuntime().restURL}cf7apps/v1/get-apps`;
    if (id) {
        url += `/${encodeURIComponent(id)}`;
    }

    const response = await fetch(url, {
        headers: getRestHeaders(),
        method: 'GET'
    });

    if (!response.ok) {
        return false;
    }

    const json = await response.json();

    return json.data;
}

/**
 * Saves the App settings to the server.
 * 
 * @param {string} id The ID of the app. 
 * @param {object} app_settings The app settings to save.
 *  
 * @returns 
 * 
 * @since 3.0.0
 */
export async function saveSettings(id, app_settings) {
    const response = await fetch(
        `${getCf7AppsRuntime().restURL}cf7apps/v1/save-app-settings`, {
            headers: getRestHeaders(),
            method: 'POST',
            body: JSON.stringify({ 
                id: id,
                app_settings
            })
        }
    );

    if (!response.ok) {
        return false;
    }

    const json = await response.json();

    return json.data;
}

/**
 * Fetches the CF7 forms from the server.
 *  
 * @since 3.0.0
 * 
 * @returns {array} The CF7 forms.
 */
export async function getCF7Forms() {
    const response = await fetch(
        `${getCf7AppsRuntime().restURL}cf7apps/v1/get-cf7-forms`, {
            headers: getRestHeaders(),
            method: 'GET'
        }
    );

    if (!response.ok) {
        return false;
    }

    const json = await response.json();
    
    return json.data;
}

/**
 * If the app has migrated or not.
 * 
 * @returns {boolean} True if the app has migrated, false otherwise.
 * 
 * @since 3.0.0
 */
export async function hasMigrated() {
    const response = await fetch(
        `${getCf7AppsRuntime().restURL}cf7apps/v1/has-migrated`, {
            headers: getRestHeaders(),
            method: 'GET'
        }
    );

    if (!response.ok) {
        return false;
    }

    const json = await response.json();
    
    return json.data;
}

/**
 * Migrates the app from old structure to new structure.
 * 
 * @returns {boolean} True if the migration was successful, false otherwise.
 * 
 * @since 3.0.0
 */
export async function migrate() {
    const response = await fetch(
        `${getCf7AppsRuntime().restURL}cf7apps/v1/migrate`, {
            headers: getRestHeaders(),
            method: 'POST'
        }
    );

    if (!response.ok) {
        return false;
    }

    const json = await response.json();
    
    return json.data;
}

/**
 * Fetches the CF7 entries from the server.
 *
 * @since 3.1.0
 * @returns {Promise<*|boolean>}
 */
export async function getCF7Entries( { page = 1, perPage = 10, form_id = 0, search = '', start_date = 0, end_date = 0 } = {} ) {

    const baseUrl   = `${getCf7AppsRuntime().restURL}cf7apps/v1/get-cf7-entries`;
    const separator = baseUrl.includes( '?' ) ? '&' : '?';
    const url       = `${ baseUrl }${ separator }page=${ page }&per-page=${ perPage }&form-id=${ form_id }&search=${ search }&start-date=${ start_date }&end-date=${ end_date }`;

    const response = await fetch(
        url, {
            headers: getRestHeaders(),
            method: 'GET'
        }
    );

    if (!response.ok) {
        return false;
    }

    const json = await response.json();

    return {
        entries: json.data,
        total: json.total,
    }
}

/**
 * Deletes the CF7 entries from the server.
 *
 * @since 3.1.0
 * @param entryIds Array of entry IDs to delete.
 *
 * @returns {Promise<*|boolean>}
 */
export async function deleteCF7Entries( entryIds = [] ) {
    if ( ! Array.isArray( entryIds ) || entryIds.length === 0 ) {
        return false;
    }

    // serialize the entry IDs to a query string
    const queryString = entryIds.map( id => `entry_ids[]=${ encodeURIComponent( id ) }` ).join( '&' );

    const baseUrl   = `${getCf7AppsRuntime().restURL}cf7apps/v1/delete-cf7-entries`;
    const separator = baseUrl.includes( '?' ) ? '&' : '?';
    const url       = `${ baseUrl }${ separator }${ queryString }`;

    const response = await fetch(
        url, {
            headers: getRestHeaders(),
            method: 'GET',
    } );

    if ( ! response.ok ) {
        return false;
    }

    const json = await response.json();
    return json.data;
}

/**
 * Fetches all CF7 forms from the server.
 *
 * @since 3.1.0
 * @returns {Promise<*|boolean>}
 */
export async function getAllCF7Forms() {
    const response = await fetch(
        `${getCf7AppsRuntime().restURL}cf7apps/v1/get-all-cf7-forms`, {
            headers: getRestHeaders(),
            method: 'GET'
        }
    );

    if (!response.ok) {
        return false;
    }

    const json = await response.json();

    return json.data;
}

export async function getRecommendedPlugins() {
    const { response, json } = await requestRecommendedEndpoint( 'recommended-plugins', {
        headers: getRestHeaders(),
        method: 'GET',
    } );

    if ( ! response?.ok || ! json?.success ) {
        return false;
    }

    return json.data;
}

export async function installActivateRecommendedPlugin( slug ) {
    const { response, json } = await requestRecommendedEndpoint(
        'recommended-plugins/install-activate',
        {
            headers: getRestHeaders(),
            method: 'POST',
            body: JSON.stringify( { slug } ),
        }
    );

    if ( ! response?.ok || ! json?.success ) {
        return {
            error: json?.data?.message || 'Request failed.',
        };
    }

    return { data: json.data };
}

export async function fetchSpamCount() {
    const response = await fetch(
        `${getCf7AppsRuntime().restURL}cf7apps/v1/spam-count`, {
            headers: getRestHeaders(),
            method: 'GET'
        }
    );

    if ( ! response.ok ) {
        return false;
    }

    const json = await response.json();

    return json.data;
}