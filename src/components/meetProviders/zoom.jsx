import { __ } from '@wordpress/i18n';
import * as React from 'react';
import Button from '@mui/material/Button';
import { useApiFetch } from "../../hooks/api";
import tokens from "../../utils/token";

const ZoomConnection = ({handleAlert, isConnected = false, code = null}) => {
    const [buttonText, setButtonText] = React.useState(isConnected ? __( 'Zoom connected', 'google-meet-and-zoom-integration') : __( 'Connect Zoom', 'google-meet-and-zoom-integration'));
    const [buttonDisabled, setButtonDisabled] = React.useState(isConnected);

    const redirectUri = new URL(`/my-account/my-meets/`, window.location.origin).href;

    const login = () => {
        window.location.href = `https://zoom.us/oauth/authorize?response_type=code&client_id=${tokens.zoomApiKey}&redirect_uri=${redirectUri}`;
    }

    const { apiData, isApiPending, apiError, executeApi } = useApiFetch(
        'save-token',
        'POST',
        null,
        false
    );

    React.useEffect(() => {
        if (code) {
            executeApi({
                token: code,
                type: 'zoom'
            })
        }
    }, code)

    React.useEffect(() => {
        if (apiData && !isApiPending && !apiError) {
            window.history.replaceState(null, '', window.location.pathname);
            setButtonText(__( 'Zoom connected', 'google-meet-and-zoom-integration'));
            setButtonDisabled(true);
            handleAlert(true, 'success', __( 'Zoom account was connected successfully.', 'google-meet-and-zoom-integration'));
            setTimeout(() => {
                handleAlert(false)
            }, 4000);
        }
        if (apiError) {
            window.history.replaceState(null, '', window.location.pathname);
            setButtonDisabled(false);
            handleAlert(true, 'error', __( 'Something went wrong. Please try again.', 'google-meet-and-zoom-integration'));
            setTimeout(() => {
                handleAlert(false)
            }, 4000);
        }
    }, [isApiPending, apiError, apiData]);

    return (
        <div>
            <Button
                sx={{ mb: 2, mt: 2 }}
                onClick={() => login()}
                component="label"
                role={undefined}
                variant="contained"
                tabIndex={-1}
                startIcon={
                    <svg xmlns="http://www.w3.org/2000/svg" x="0px" y="0px" width="1em" height="1em" viewBox="0 0 48 48">
                        <circle cx="24" cy="24" r="20" fill="#000"></circle><path fill="#fff" d="M29,31H14c-1.657,0-3-1.343-3-3V17h15c1.657,0,3,1.343,3,3V31z"></path><polygon fill="#fff" points="37,31 31,27 31,21 37,17"></polygon>
                    </svg>
                }
                disabled={buttonDisabled}
            >
                {buttonText}
            </Button>
        </div>
    );
};

export default ZoomConnection;
