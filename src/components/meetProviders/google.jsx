import * as React from 'react';
import { useGoogleLogin } from '@react-oauth/google';
import Button from '@mui/material/Button';
import { Google } from "@mui/icons-material";
import { Alert } from "@mui/material";
import IconButton from '@mui/material/IconButton';
import CloseIcon from '@mui/icons-material/Close';
import { useApiFetch } from "../../hooks/api";

const GoogleConnection = ({isConnected = false}) => {
    const [showAlert, setShowAlert] = React.useState(false);
    const [alertType, setAlertType] = React.useState('');
    const [alertMessage, setAlertMessage] = React.useState('');
    const [buttonText, setButtonText] = React.useState(isConnected ? 'Google connected' : 'Connect Google');
    const [buttonDisabled, setButtonDisabled] = React.useState(isConnected);

    const requiredScopes = ['https://www.googleapis.com/auth/meetings.space.created', 'https://www.googleapis.com/auth/meetings.media.readonly'];

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
                setButtonDisabled(true);
                await executeApi({
                    token: response.access_token,
                    type: 'google',
                    expire: response.expires_in,
                });
            } else {
                setAlertMessage('Please provide the required scopes to use this app.');
                setAlertType('error');
                setShowAlert(true);
                setTimeout(() => {
                    setShowAlert(false);
                }, 4000);
            }
        },
        onError: (error) => {
            console.log(error);
            setAlertMessage('Something went wrong. Please try again.');
            setAlertType('error');
            setShowAlert(true);
            setTimeout(() => {
                setShowAlert(false);
            }, 4000);
        },
        onNonOAuthError: (error) => {
            console.log(error);
            setAlertMessage('Something went wrong. Please try again.');
            setAlertType('error');
            setShowAlert(true);
            setTimeout(() => {
                setShowAlert(false);
            }, 4000);
        },
        scope: requiredScopes.join(' '),
    });

    React.useEffect(() => {
        if (apiData && !isApiPending && !apiError) {
            setButtonText('Google connected');
            setAlertMessage('Google account was connected successfully.');
            setAlertType('success');
            setShowAlert(true);
            setTimeout(() => {
                setShowAlert(false);
            }, 4000);
        }
        if (apiError) {
            setButtonDisabled(false)
            setAlertMessage('Something went wrong. Please try again.');
            setAlertType('error');
            setShowAlert(true);
            setTimeout(() => {
                setShowAlert(false);
            }, 4000);
        }
    }, [isApiPending, apiError, apiData]);

    return (
        <div>
            {showAlert && (
                <Alert
                    variant="outlined"
                    severity={alertType}
                    sx={{ mb: 2 }}
                    action={
                        <IconButton
                            aria-label="close"
                            color="inherit"
                            size="small"
                            onClick={() => {
                                setShowAlert(false);
                            }}
                        >
                            <CloseIcon className='alert-close-icon' fontSize="inherit" />
                        </IconButton>
                    }
                >
                    {alertMessage}
                </Alert>
            )}
            <Button
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
