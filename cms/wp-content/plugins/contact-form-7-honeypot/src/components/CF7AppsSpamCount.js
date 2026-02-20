import { __ } from '@wordpress/i18n';
import { useState, useEffect } from '@wordpress/element';
import CF7AppsSkeletonLoader from "./CF7AppsSkeletonLoader";
import { fetchSpamCount } from '../api/api';
const CF7AppsSpamCount = () => {

    const [ spamCount, setSpamCount ] = useState( 0 );
    const [ since, setSince ]         = useState( null );
    const [ loading, setLoading ]     = useState( true );

    useEffect( () => {
            setLoading( true );

            fetchSpamCount().then( response => {
                    setSpamCount( response.count );
                    setSince( response.since );
                    setLoading( false );
                } ).catch( error => {
                    setSpamCount( 0 );
                    setSince( null );
                    setLoading( false );
                } );
        }, [] );

    return (
        <div className={ 'cf7apps-spam-count' }>
            {
                loading
                ?
                    <>
                        <CF7AppsSkeletonLoader height={ 45 } width={ '100%' } />
                    </>
                :
                    <div className={ 'cf7apps-notice cf7apps-notice-info' }>
                        <p>
                            { __( 'Honeypot has stopped', 'cf7apps' ) }
                            &nbsp;<strong>{ spamCount }</strong>&nbsp;
                            { __( 'spam submissions since', 'cf7apps' ) }
                            &nbsp;<strong>{ since }</strong>&nbsp;
                        </p>
                    </div>
            }
        </div>
    );
}

export default CF7AppsSpamCount;