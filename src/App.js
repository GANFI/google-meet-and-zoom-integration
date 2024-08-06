import React from 'react';
import { createTheme, ThemeProvider } from '@mui/material';
import { GoogleOAuthProvider } from '@react-oauth/google';
import { ruRU } from "@mui/material/locale";
import ActionTabs from "./components/ActionTabs";


const App = () => {

    const themeWithLocale = React.useMemo(
        () => createTheme({
            palette: {
                primary: { main: '#1976d2' },
            },
        }, ruRU),
        []
    );

    return (
        <ThemeProvider theme={themeWithLocale}>
            <GoogleOAuthProvider clientId="174013105887-hijopn916usvs4tuqqb0u7fm47hmbd67.apps.googleusercontent.com">
                <ActionTabs />
            </GoogleOAuthProvider>
        </ThemeProvider>
    );
};

export default App;
