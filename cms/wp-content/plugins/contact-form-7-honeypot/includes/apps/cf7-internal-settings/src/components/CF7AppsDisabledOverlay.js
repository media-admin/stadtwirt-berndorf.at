import { __, sprintf } from '@wordpress/i18n';
import { Button } from '@wordpress/components';

const CF7AppsDisabledOverlay = ({ appId, appTitle }) => {
    const redirectToSettings = () => {
        window.location.href = `${CF7AppsInternalSettings.appIndexURL}#/settings/${appId}`;
    };

    return (
        <div className="cf7apps-disabled-overlay">
            <Button
                className="cf7apps-enable-button"
                onClick={redirectToSettings}
                variant="primary"
                size="large"
            >
                {__('To Enable, Go to Settings', 'cf7apps')}
            </Button>
        </div>
    );
};

export default CF7AppsDisabledOverlay;
