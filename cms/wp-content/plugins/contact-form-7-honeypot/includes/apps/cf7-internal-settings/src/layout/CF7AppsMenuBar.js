import { useEffect, useState } from '@wordpress/element';
import CF7AppsSkeletonLoader from '../components/CF7AppsSkeletonLoader';
import { Accordion, AccordionDetails, AccordionSummary, Typography } from '@mui/material';
import { ExpandMore } from '@mui/icons-material';
import { NavLink } from 'react-router';
import { getMenu } from '../api';

const CF7AppsMenuBar = () => {
    const [ isLoading, setIsLoading ] = useState( true );
    const [ menuItems, setMenuItems ] = useState( [] );

    useEffect( () => {
        setIsLoading( true );

        getMenu().then( response => {
                if ( response ) {
                    setMenuItems( response );
                    setIsLoading( false );
                }
            } );
    }, [] );

    const getParentMenu = ( menu ) => {
        switch ( menu ) {
            case 'Spam Protection':
                return (
                    <>
                        <img src={ `${ CF7AppsInternalSettings.assetsURL }/images/spam-protection.png` } width={ '30px' } alt={ menu } /> { menu }
                    </>
                );
            case 'Integration':
                return (
                    <>
                        <img src={ `${ CF7AppsInternalSettings.assetsURL }/images/spam-protection.png` } width={ '23px' } alt={ menu } /> { menu }
                    </>
                );
            case 'General':
                return (
                    <>
                        <img style={ { marginTop: '-7px' } } src={ `${ CF7AppsInternalSettings.assetsURL }/images/general.png` } width={ '23px' } alt={ menu } /> { menu }
                    </>
                );
            default:
                return menu;
        }
    }

    return (
        <>
            <div className={ 'cf7apps-menu-bar' }>

                {
                    isLoading
                    ?
                        <div>
                            <CF7AppsSkeletonLoader count={1} height={40} width={205} />
                            <br />
                            <CF7AppsSkeletonLoader count={1} height={30} />
                            <br />
                            <CF7AppsSkeletonLoader count={1} height={20} />
                            <br />
                            <CF7AppsSkeletonLoader count={1} height={20} />
                            <br />
                            <CF7AppsSkeletonLoader count={1} height={20} />
                        </div>
                    :
                        <div>
                            <div className={ 'cf7apps-menu-container' }>
                                {
                                    Object.keys( menuItems ).map( ( parentMenu, parentIndex ) => {
                                        return (
                                            <Accordion key={ parentIndex } defaultExpanded className={ 'cf7apps-menu-accordion' }>
                                                <AccordionSummary expandIcon={ <ExpandMore /> }>
                                                    <Typography component={ 'span' } className={ 'cf7apps-menu-heading' }>
                                                        { getParentMenu( parentMenu ) }
                                                    </Typography>
                                                </AccordionSummary>

                                                <AccordionDetails className={ 'cf7apps-menu-routes-container' }>
                                                    {
                                                        Object.entries( menuItems[ parentMenu ] ).map( ( [ route, submenu ], submenuIndex ) => {
                                                            return (
                                                                <div key={ submenuIndex } className={ 'cf7apps-menu-route' }>
                                                                   <NavLink to={ `/settings/${ route }` }>{ submenu }</NavLink>
                                                                </div>
                                                            );
                                                        } )
                                                    }
                                                </AccordionDetails>
                                            </Accordion>
                                        );
                                    } )
                                }
                            </div>
                        </div>
                }
            </div>
        </>
    );
}

export default CF7AppsMenuBar;