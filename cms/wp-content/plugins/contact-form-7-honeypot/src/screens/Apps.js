import { useEffect, useState } from "@wordpress/element";
import { __, sprintf } from "@wordpress/i18n";
import { getApps } from "../api/api";
import CF7AppsSkeletonLoader from "../components/CF7AppsSkeletonLoader";
import CF7AppsApp from "../components/CF7AppsApp";
import CF7AppsNotice from "../components/CF7AppsNotice";
import { useParams } from "react-router";

const Apps = () => {
    const [isLoading, setIsLoading] = useState(true);
    const [apps, setApps] = useState(false);
    const [showAcfNotice, setShowAcfNotice] = useState(false);
    const { parent } = useParams();

    // Scroll to top when ACF notice is shown
    useEffect(() => {
        if (showAcfNotice) {
            // Use setTimeout to ensure DOM has updated
            setTimeout(() => {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }, 100);
        }
    }, [showAcfNotice]);

    useEffect( () => {
        async function fetchApps() {
            const apps = await getApps();
            setApps(apps);
            setIsLoading(false);
        }
        
        fetchApps();
    }, []);

    const normalizeParent = (parent) => String(parent || '').toLowerCase().replace( /\s+/g, '-' );

    const renderAppsFor = (key) => {
        if ( ! apps || ! Array.isArray( apps ) ) {
            return null;
        }

        return apps.map( ( app, idx ) => {
            if ( normalizeParent( app.parent_menu ) === key ) {
                return <CF7AppsApp key={ app.id || idx } settings={ app } />;
            }

            return null;
        } );
    };

    return (
        <div className="cf7apps-body">
            <div className="cf7apps-apps-header">
                <div className="cf7apps-container">
                    <div>
                        <h2>{ __( 'Unleash the full potential of Contact Form 7!', 'cf7apps' ) }</h2>
                        <p>
                            { __( 'Simplify, customize, and enhance your form building experience.', 'cf7apps' ) }
                        </p>
                    </div>
                </div>
            </div>
            {showAcfNotice && (
                <div className="cf7apps-container" style={{ marginTop: '20px', marginBottom: '20px' }}>
                    <CF7AppsNotice
                        type="danger"
                        text={sprintf(
                            __( 'This integration requires the Advanced Custom Fields plugin to be installed and active. %s', 'cf7apps' ),
                            '<a href="' + (window.location.origin + '/wp-admin/plugin-install.php?s=advanced-custom-fields&tab=search&type=term') + '" style="text-decoration: underline; font-weight: bold;">' + __( 'Install ACF Plugin', 'cf7apps' ) + '</a>'
                        )}
                    />
                </div>
            )}
            <div className="cf7apps-apps-section">
                <div className="cf7apps-container">
                    <h2>{ __( 'General', 'cf7apps' ) }</h2>

                    <div className={ 'cf7apps-apps-container' }>
                        {
                            isLoading ?
                                <>
                                    <div className="cf7apps-app">
                                        <CF7AppsSkeletonLoader width="100%" height={250} />
                                    </div>
                                    <div className="cf7apps-app">
                                        <CF7AppsSkeletonLoader width="100%" height={250} />
                                    </div>
                                    <div className="cf7apps-app">
                                        <CF7AppsSkeletonLoader width="100%" height={250} />
                                    </div>
                                </> :
                                Object.keys( apps ).map( appIndex => {
                                    // Skip ACF integration from General section
                                    if ( apps[ appIndex ].id === 'acf-integration' ) {
                                        return null;
                                    }
                                    
                                    return (
                                        <>
                                            {
                                                'general' === String( apps[ appIndex ].parent_menu ).toLowerCase().replace( /\s+/g, '-' )
                                                && <CF7AppsApp settings={ apps[ appIndex ] } />
                                            }
                                        </>
                                    )
                                } )
                        }
                    </div>

                    <h2>{ __( 'Spam Protection Apps', 'cf7apps' ) }</h2>
                    <div className="cf7apps-apps-container">
                        {
                            isLoading
                            ?
                            <>
                                <div className="cf7apps-app">
                                    <CF7AppsSkeletonLoader width="100%" height={250} />
                                </div>
                                <div className="cf7apps-app">
                                    <CF7AppsSkeletonLoader width="100%" height={250} />
                                </div>
                                <div className="cf7apps-app">
                                    <CF7AppsSkeletonLoader width="100%" height={250} />
                                </div>
                            </>
                            :
                            (() => {
                                // Filter spam protection apps (excluding ACF integration)
                                const spamProtectionApps = Object.keys(apps).filter(appIndex => {
                                    const normalizedParentMenu = String( apps[ appIndex ].parent_menu ).toLowerCase().replace( /\s+/g, '-' );
                                    return 'spam-protection' === normalizedParentMenu && apps[ appIndex ].id !== 'acf-integration';
                                });
                                
                                return spamProtectionApps.map((appIndex) => (
                                    <CF7AppsApp key={appIndex} settings={ apps[ appIndex ] } />
                                ));
                            })()
                        }
                    </div>

                    <h2>{ __( 'Integrations', 'cf7apps' ) }</h2>
                    <div className="cf7apps-apps-container">
                        {
                            isLoading
                            ?
                            <>
                                <div className="cf7apps-app">
                                    <CF7AppsSkeletonLoader width="100%" height={250} />
                                </div>
                                <div className="cf7apps-app">
                                    <CF7AppsSkeletonLoader width="100%" height={250} />
                                </div>
                                <div className="cf7apps-app">
                                    <CF7AppsSkeletonLoader width="100%" height={250} />
                                </div>
                            </>
                            :
                            Object.keys(apps).map((appIndex) => {
                                const normalizedParentMenu = String( apps[ appIndex ].parent_menu ).toLowerCase().replace( /\s+/g, '-' );
                                return (
                                    <>
                                        {
                                            ( 
                                                'integrations' === normalizedParentMenu ||
                                                'integration' === normalizedParentMenu ||
                                                apps[ appIndex ].id === 'acf-integration' 
                                            )
                                            && <CF7AppsApp 
                                                settings={ apps[ appIndex ] } 
                                                onShowAcfNotice={() => {
                                                    // Reset notice state to ensure useEffect triggers
                                                    setShowAcfNotice(false);
                                                    // Use setTimeout to ensure state reset before setting to true
                                                    setTimeout(() => {
                                                        setShowAcfNotice(true);
                                                        // Scroll to top immediately
                                                        window.scrollTo({ top: 0, behavior: 'smooth' });
                                                    }, 10);
                                                    setShowAcfNotice(true);
                                                    setTimeout(() => setShowAcfNotice(false), 5000);
                                                }}
                                            />
                                        }
                                    </>
                                )
                            })
                        }
                    </div>
                </div>
            </div>
        </div>
    );
}

export default Apps;