import { useEffect } from "@wordpress/element";
import { __ } from "@wordpress/i18n";
import { Route, Routes, useLocation } from "react-router";
import Apps from "../screens/Apps";
import AppSettings from "../screens/AppSettings";

const resetPageScroll = () => {
    window.scrollTo( 0, 0 );

    if ( document.documentElement ) {
        document.documentElement.scrollTop = 0;
    }

    if ( document.body ) {
        document.body.scrollTop = 0;
    }
};

const ScrollToTop = () => {
    const { pathname } = useLocation();

    useEffect( () => {
        resetPageScroll();
    }, [ pathname ] );

    return null;
};

const Body = () => {
    return (
        <>
            <ScrollToTop />
            <Routes>
                <Route path='/' element={<Apps />} />
                <Route path='/:parent' element={<Apps />} />
                <Route path="/settings">
                    <Route path=':app' element={<AppSettings />} />
                    <Route path=':app/:section' element={ <AppSettings /> } />
                </Route>
            </Routes>
        </>
    );
}

export default Body;