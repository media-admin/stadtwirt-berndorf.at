import { createRoot, StrictMode } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';
import { HashRouter } from 'react-router';
import CF7AppsToastNotification from './components/CF7AppsToastNotification';
import CF7AppsHeader from './components/CF7AppsHeader';
import CF7AppsMenuBar from './layout/CF7AppsMenuBar';
import CF7AppsBody from './layout/CF7AppsBody';

import './index.css';

const CF7AppsView = () => {

    return (
        <>
            <CF7AppsToastNotification />

            <div className={ 'cf7apps-main-content' }>
                <CF7AppsHeader />

                <div className={ 'cf7apps-layout' }>
                    <CF7AppsMenuBar />
                    <CF7AppsBody />
                </div>
            </div>
        </>
    );
}

domReady( () => {

    const container = document.getElementById( 'cf7apps-root' );
    if ( container ) {
        const root = createRoot( container );
        root.render(
            <HashRouter>
                <StrictMode>
                    <CF7AppsView />
                </StrictMode>
            </HashRouter>
        );
    }
} );
