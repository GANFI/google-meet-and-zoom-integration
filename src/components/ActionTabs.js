import { __ } from '@wordpress/i18n';
import * as React from 'react';
import { useEffect, useState } from "react";
import PropTypes from 'prop-types';
import Tabs from '@mui/material/Tabs';
import Tab from '@mui/material/Tab';
import Box from '@mui/material/Box';
import GoogleConnection from "./meetProviders/google";
import { useApiFetch } from "../hooks/api";
import ZoomConnection from "./meetProviders/zoom";
import {Alert, LinearProgress, Stack} from "@mui/material";
import IconButton from "@mui/material/IconButton";
import CloseIcon from "@mui/icons-material/Close";
import MeetManager from "./meetTable/MeetManager";

function TabPanel(props) {
    const { children, value, index, ...other } = props;

    return (
        <div
            role="tabpanel"
            hidden={value !== index}
            id={`simple-tabpanel-${index}`}
            aria-labelledby={`simple-tab-${index}`}
            {...other}
        >
            {value === index && <Box sx={{ p: 3 }}>{children}</Box>}
        </div>
    );
}

TabPanel.propTypes = {
    children: PropTypes.node,
    index: PropTypes.number.isRequired,
    value: PropTypes.number.isRequired,
};

function a11yProps(index) {
    return {
        id: `simple-tab-${index}`,
        'aria-controls': `simple-tabpanel-${index}`,
    };
}

export default function ActionTabs() {

    const [loader, setLoader] = React.useState(true);
    const [value, setValue] = React.useState(0);
    const [zoomCode, setZoomCode] = React.useState(null);
    const [isGoogleConnected, setIsGoogleConnected] = React.useState(false);
    const [isZoomConnected, setIsZoomConnected] = React.useState(false);
    const [providerInfo, setProviderInfo] = React.useState({google: false, zoom: false});

    const [showAlert, setShowAlert] = React.useState(false);
    const [alertType, setAlertType] = React.useState('');
    const [alertMessage, setAlertMessage] = React.useState('');

    const [permissions, setPermissions] = useState(null);

    const handleChange = (event, newValue) => {
        setValue(newValue);
    };

    const { apiData, isApiPending, apiError, executeApi } = useApiFetch('info');

    useEffect(() => {
        const queryParameters = new URLSearchParams(window.location.search);
        setZoomCode(queryParameters.get('code'));
    }, []);

    useEffect(() => {
        if (zoomCode) {
            setValue(1);
        }
    }, zoomCode)

    useEffect(() => {
        if (apiData) {
            setIsGoogleConnected(apiData.google_connected);
            setIsZoomConnected(apiData.zoom_connected);
            setProviderInfo({
                google: apiData.google_connected,
                zoom: apiData.zoom_connected
            })
        }
    }, apiData)

    const permissionHook = useApiFetch('permissions');
    useEffect(() => {
        if (permissionHook && permissionHook.apiData) {
            if (permissionHook.apiData.permissions) {
                setPermissions(permissionHook.apiData.permissions);
            }
        } else if (permissionHook.apiError) {
            console.error('API Error:', permissionHook.apiError);
        }
    }, [permissionHook.apiData, permissionHook.apiError]);

    useEffect(() => {
        if (permissionHook.isApiPending && isApiPending) {
            setLoader(true);
        } else {
            setTimeout(() => {
                setLoader(false);
            }, 500)
        }
    }, [permissionHook.isApiPending, isApiPending]);

    return (
        <>
            {loader && (
                <Stack sx={{ width: '100%', my: 4 }} spacing={2}>
                    <LinearProgress />
                </Stack>
            )}

            {!loader && (
                <Box sx={{ width: '100%' }}>
                    <Box sx={{ borderBottom: 1, borderColor: 'divider' }}>
                        <Tabs value={value} onChange={handleChange} aria-label="tabs" className="meet-tabs">
                            <Tab
                                label={__( 'My meets', 'google-meet-and-zoom-integration')}
                                {...a11yProps(0)}
                            />
                            <Tab
                                label={__( 'Settings', 'google-meet-and-zoom-integration')}
                                {...a11yProps(1)}
                            />
                        </Tabs>
                    </Box>
                    <TabPanel value={value} index={0}>
                        <MeetManager
                            userPermissions={permissions}
                            userProviderInfo={providerInfo}
                        />
                    </TabPanel>
                    <TabPanel value={value} index={1}>
                        <h3>{__( 'Set up your accounts here', 'google-meet-and-zoom-integration')}</h3>
                        <Box sx={{ mt: 2 }}>
                            {showAlert && (
                                <Alert
                                    variant="outlined"
                                    severity={alertType}
                                    sx={{ mb: 2, mt: 2 }}
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
                            <div
                                style={{display: 'flex', justifyContent: 'flex-start', alignItems: 'center', gap: 10}}
                            >
                                <GoogleConnection
                                    handleAlert={(show, type = '', message = '') => {
                                        setShowAlert(show);
                                        setAlertType(type);
                                        setAlertMessage(message);
                                    }}
                                    isConnected={isGoogleConnected}
                                />
                                <ZoomConnection
                                    handleAlert={(show, type = '', message = '') => {
                                        setShowAlert(show);
                                        setAlertType(type);
                                        setAlertMessage(message);
                                    }}
                                    isConnected={isZoomConnected}
                                    code={zoomCode}
                                />
                            </div>
                        </Box>
                    </TabPanel>
                </Box>
            )}
        </>);
}
