import { useEffect, useState } from "@wordpress/element";
import { Button, FlexItem } from "@wordpress/components";
import CF7AppsSkeletonLoader from "../components/CF7AppsSkeletonLoader";
import { getMenu } from "../api/api";
import { Accordion, AccordionDetails, AccordionSummary, Typography } from "@mui/material";
import { ExpandMore, DensityMedium, KeyboardArrowLeft } from "@mui/icons-material";
import { NavLink } from "react-router";

const MenuBar = (props) => {
    const [isLoading, setIsLoading] = useState(true);
    const [menuItems, setMenuItems] = useState([]);
    const [ expanded, setExpanded ] = useState( true );
    const [ menuStyle, setMenuStyle ] = useState( {} );

    useEffect( () => {
        async function fetchMenu() {
            setIsLoading(true);
            const response = await getMenu();
            if(response) {
                setMenuItems(response);
                setIsLoading(false);
            }
        }

        fetchMenu();
    }, []);

    const getParentMenu = (menu) => {
        switch (menu) {
            case 'Spam Protection':
                return (
                    <>
                        <img src={`${CF7Apps.assetsURL}/images/spam-protection.png`} width="23px" alt={menu} /> { menu }
                    </>
                );

            case 'General':
                return (
                    <>
                        <img style={ { marginTop: '-7px' } } src={`${ CF7Apps.assetsURL }/images/general.png`} width={'23px'} alt={ menu } /> { menu }
                    </>
                );
                break;

            case 'Integration':
                return (
                    <>
                        <img style={ { marginTop: '-7px' } } src={`${ CF7Apps.assetsURL }/images/spam-protection.png`} width={'23px'} alt={ menu } /> { menu }
                    </>
                );
                break;
            default:
                return menu;
        }
    }

    const handleMenuClick = ( e ) => {
        e.preventDefault();
        setExpanded( ! expanded );

        let style;
        if ( expanded ) {
            style = { width: '60px' };
        } else {
            style = {};
        }

        setMenuStyle( style );
    }

    return (
        <>
            <div
                className="cf7apps-menu-bar"
                style={ menuStyle }
            >

                
                <Button onClick={ handleMenuClick } className="cf7apps-btn tertiary-secondary cf7apps-expand-menu-btn">
                    {
                        expanded ? <KeyboardArrowLeft /> : <DensityMedium />
                    }
                </Button>

                <div className="cf7apps-clearfix"></div>
            

            {
                isLoading
                ?
                <div style={{ padding: '20px', paddingTop: '5px' }}>
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
                <div style={ { display: expanded ? 'block' : 'none' } }>
                    <div className="cf7apps-menu-container">
                        {
                            Object.keys(menuItems).map((parentMenu, parentIndex) => {
                                return (
                                    <Accordion key={parentIndex} defaultExpanded className="cf7apps-menu-accordion">
                                        <AccordionSummary
                                            expandIcon={ <ExpandMore /> }
                                            >
                                                <Typography component="span" className="cf7apps-menu-heading">
                                                    { getParentMenu(parentMenu) }
                                                </Typography>
                                            </AccordionSummary>
                                            <AccordionDetails className="cf7apps-menu-routes-container">
                                                {
                                                    Object.entries(menuItems[parentMenu]).map(([route, subMenu], subMenuIndex) => {
                                                        return (
                                                            <div className='cf7apps-menu-route'>
                                                                <NavLink to={`/settings/${route}`}>{ subMenu }</NavLink>
                                                            </div>
                                                        )
                                                    })
                                                }
                                            </AccordionDetails>
                                    </Accordion>
                                )
                            })
                        }
                    </div>
                </div>
            }
            </div>
        </>
    );
}

export default MenuBar;