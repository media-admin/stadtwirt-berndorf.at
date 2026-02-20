import { useEffect, useState } from '@wordpress/element';
import CF7AppsSkeletonLoader from './CF7AppsSkeletonLoader';
import {Link, useLocation, useNavigate } from 'react-router';
import { Button, Flex, FlexItem, Tooltip } from '@wordpress/components';
import { KeyboardArrowLeft } from '@mui/icons-material';
import { __ } from '@wordpress/i18n';

const CF7AppsHeader = () => {
    let path = useLocation();
    const navigate = useNavigate();
    const [ isLoading, setIsLoading ] = useState( true );

    useEffect( () => {
        const timer = setTimeout( () => {
            setIsLoading( false );
        }, 500 );

        return () => clearTimeout( timer );
    }, [] );

    const handleBackClick = () => {
        navigate( -1 )
    }

    return (
        <div>

            {
                isLoading
                ?
                    <div>
                        <CF7AppsSkeletonLoader count={1} height={85} />
                    </div>
                :
                    <div className={ 'cf7apps-header' }>
                        <div className={ 'container' }>
                            <Flex>
                                <FlexItem>
                                    <Link to={ '/' }>
                                        <img src={ `${ CF7AppsInternalSettings.assetsURL }/images/logo.png` } width={ '250px' } alt={ 'CF7 Apps Logo' } />
                                    </Link>
                                </FlexItem>

                                <FlexItem>
                                    <div className="cf7apps-header-right">
                                        { CF7AppsInternalSettings?.pluginVersion && (
                                            <span className="cf7apps-header-version">
                                                { CF7AppsInternalSettings.pluginVersion }
                                            </span>
                                        ) }

                                        <Tooltip text={ __( 'View documentation', 'cf7apps' ) } position="bottom">
                                            <button
                                                type="button"
                                                className="cf7apps-header-icon-button"
                                                aria-label={ __( 'View documentation', 'cf7apps' ) }
                                                onClick={ () => window.open( 'https://cf7apps.com/docs/?utm_source=plugin&utm_medium=header&utm_campaign=documentation', '_blank' ) }
                                            >
                                                <img
                                                    src={ `${ CF7AppsInternalSettings.assetsURL }/images/document-text.png` }
                                                    alt={ __( 'Documentation', 'cf7apps' ) }
                                                    className="cf7apps-header-icon-img"
                                                />
                                            </button>
                                        </Tooltip>

                                        <Tooltip text={ __( 'Share your idea with us', 'cf7apps' ) } position="bottom">
                                            <button
                                                type="button"
                                                className="cf7apps-header-icon-button"
                                                aria-label={ __( 'Share your idea with us', 'cf7apps' ) }
                                                onClick={ () => window.open( 'https://cf7apps.com/submit-idea/?utm_source=plugin&utm_medium=header&utm_campaign=idea', '_blank' ) }
                                            >
                                                <img
                                                    src={ `${ CF7AppsInternalSettings.assetsURL }/images/lamp-charge.png` }
                                                    alt={ __( 'Share idea', 'cf7apps' ) }
                                                    className="cf7apps-header-icon-img"
                                                />
                                            </button>
                                        </Tooltip>

                                        { undefined !== path.pathname && '/' !== path.pathname && (
                                            <Button onClick={ handleBackClick } className="cf7apps-btn icon tertiary-secondary cf7apps-header-back-btn">
                                                <KeyboardArrowLeft />
                                                { __( 'Back', 'cf7apps' ) }
                                            </Button>
                                        ) }
                                    </div>
                                </FlexItem>
                            </Flex>
                        </div>
                    </div>
            }

        </div>
    );
}

export default CF7AppsHeader;