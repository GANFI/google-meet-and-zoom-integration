import React from 'react';
import dayjs from 'dayjs';
import 'dayjs/locale/uk';
import utc from 'dayjs/plugin/utc';
import timezone from 'dayjs/plugin/timezone';
import { createTheme, ThemeProvider } from '@mui/material';
import { LocalizationProvider } from '@mui/x-date-pickers/LocalizationProvider';
import { AdapterDayjs } from '@mui/x-date-pickers/AdapterDayjs';
import { GoogleOAuthProvider } from '@react-oauth/google';
import { ConfirmProvider } from "material-ui-confirm";
import { ukUA } from "@mui/material/locale";
import ActionTabs from "./components/ActionTabs";
import tokens from "./utils/token";

dayjs.extend(utc);
dayjs.extend(timezone);

dayjs.tz.setDefault('Europe/Kiev');

const App = () => {
    const themeWithLocale = React.useMemo(
        () => createTheme({
            palette: {
                primary: { main: '#1976d2' },
            },
        }, ukUA),
        []
    );

    return (
        <ThemeProvider theme={themeWithLocale}>
            <LocalizationProvider dateAdapter={AdapterDayjs} adapterLocale="uk">
                <GoogleOAuthProvider clientId={tokens.googleClient}>
                    <ConfirmProvider>
                        <ActionTabs />
                    </ConfirmProvider>
                </GoogleOAuthProvider>
            </LocalizationProvider>
        </ThemeProvider>
    );
};

export default App;
