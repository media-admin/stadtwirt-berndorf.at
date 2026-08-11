import { createRoot, StrictMode } from '@wordpress/element';
import domReady from '@wordpress/dom-ready';
import { HashRouter } from 'react-router';
import CF7AppsToastNotification from './components/CF7AppsToastNotification';
import CF7AppsHeader from './components/CF7AppsHeader';
import CF7AppsMenuBar from './layout/CF7AppsMenuBar';
import CF7AppsBody from './layout/CF7AppsBody';
import { CF7AppsNavProvider } from './context/CF7AppsNavContext';
import CF7AppsFormPromo from './components/CF7AppsFormPromo';

import './index.css';

const CF7AppsView = () => {

    return (
        <>
            <CF7AppsToastNotification />

            <div className="cf7apps-main-content cf7apps-form-editor-shell">
                <CF7AppsHeader />

                <div className="cf7apps-form-settings-shell">
                    <CF7AppsMenuBar />
                    <div className="cf7apps-page-main cf7apps-page-main--settings">
                        <CF7AppsBody />
                    </div>
                </div>
            </div>
        </>
    );
}

domReady(() => {
    const container = document.getElementById('cf7apps-root');
    const promoContainer = document.getElementById('cf7apps-form-promo-root');

    if (container) {
        const root = createRoot(container);
        root.render(
            <HashRouter>
                <StrictMode>
                    <CF7AppsNavProvider>
                        <CF7AppsView />
                    </CF7AppsNavProvider>
                </StrictMode>
            </HashRouter>
        );
    }

    if (promoContainer) {
        const promoRoot = createRoot(promoContainer);
        promoRoot.render(
            <StrictMode>
                <CF7AppsFormPromo />
            </StrictMode>
        );
    }
});
