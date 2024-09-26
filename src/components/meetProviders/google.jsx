import { __ } from '@wordpress/i18n';
import * as React from 'react';
import { useGoogleLogin } from '@react-oauth/google';
import Button from '@mui/material/Button';
import { Google } from "@mui/icons-material";
import { useApiFetch } from "../../hooks/api";

const GoogleConnection = ({handleAlert, isConnected = false}) => {
    const [buttonText, setButtonText] = React.useState(isConnected ? __( 'Google connected', 'google-meet-and-zoom-integration') : __( 'Connect Google', 'google-meet-and-zoom-integration'));
    const [buttonDisabled, setButtonDisabled] = React.useState(isConnected);

    const requiredScopes = ['https://www.googleapis.com/auth/meetings.space.created', 'https://www.googleapis.com/auth/meetings.media.readonly', 'https://www.googleapis.com/auth/calendar'];

    const neededScopesAreExisted = (scopes) => {
        return scopes.includes(requiredScopes[0]) && scopes.includes(requiredScopes[1]);
    };

    const { apiData, isApiPending, apiError, executeApi } = useApiFetch(
        'save-token',
        'POST',
        null,
        false
    );

    const login = useGoogleLogin({
        onSuccess: async (response) => {
            if (neededScopesAreExisted(response.scope)) {
                console.log(response)
                setButtonDisabled(true);
                await executeApi({
                    token: response.code,
                    type: 'google',
                    expire: response.expires_in,
                });
            } else {
                handleAlert(true, 'error', __( 'Please provide the required scopes to use this app.', 'google-meet-and-zoom-integration'));
                setTimeout(() => {
                    handleAlert(false);
                }, 4000);
            }
        },
        onError: (error) => {
            console.log(error);
            handleAlert(true, 'error', __( 'Something went wrong. Please try again.', 'google-meet-and-zoom-integration'));
            setTimeout(() => {
                handleAlert(false)
            }, 4000);
        },
        onNonOAuthError: (error) => {
            console.log(error);
            handleAlert(true, 'error', __( 'Something went wrong. Please try again.', 'google-meet-and-zoom-integration'));
            setTimeout(() => {
                handleAlert(false)
            }, 4000);
        },
        scope: requiredScopes.join(' '),
        prompt: 'consent',
        include_granted_scopes: true,
        access_type: 'offline',
        flow: 'auth-code',
    });

    React.useEffect(() => {
        if (apiData && !isApiPending && !apiError) {
            setButtonText(__( 'Google connected', 'google-meet-and-zoom-integration'));
            handleAlert(true, 'success', __( 'Google account was connected successfully.', 'google-meet-and-zoom-integration'));
            setTimeout(() => {
                handleAlert(false)
            }, 4000);
        }
        if (apiError) {
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
                startIcon={<Google />}
                disabled={buttonDisabled}
            >
                {buttonText}
            </Button>
        </div>
    );
};

export default GoogleConnection;
