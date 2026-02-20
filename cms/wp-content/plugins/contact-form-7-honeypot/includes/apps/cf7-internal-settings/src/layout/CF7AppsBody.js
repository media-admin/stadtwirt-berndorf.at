import { Route, Routes } from 'react-router';
import CF7AppsSettings from '../screens/CF7AppsSettings';
const CF7AppsBody = () => {

    return (
        <Routes>
            <Route path={ '/' } element={ <CF7AppsSettings /> } />
            <Route path={ 'settings/:app' } element={ <CF7AppsSettings /> } />
        </Routes>
    );
};

export default CF7AppsBody;