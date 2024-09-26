import { __ } from '@wordpress/i18n';
import React, {useEffect, useState, useRef} from 'react';
import {
    Box,
    Button,
    Modal,
    IconButton,
    FormControl,
    InputLabel,
    Select,
    MenuItem,
    OutlinedInput,
    TextField,
    Table,
    TableBody,
    TableCell,
    TableContainer,
    TableHead,
    TableRow,
    Pagination,
    FormHelperText,
    Tooltip,
    InputAdornment,
    Stack,
    LinearProgress,
    Typography,
    Backdrop,
    CircularProgress,
    Snackbar,
    Alert
} from '@mui/material';
import {ClearIcon, DatePicker, DateTimePicker} from '@mui/x-date-pickers';
import CloseIcon from '@mui/icons-material/Close';
import EditIcon from '@mui/icons-material/Edit';
import CopyIcon from '@mui/icons-material/ContentCopy';
import DeleteIcon from '@mui/icons-material/Delete';
import DownloadIcon from '@mui/icons-material/Download';
import { useApiFetch } from "../../hooks/api";
import { useConfirm } from "material-ui-confirm";
import dayjs from "dayjs";

const MeetManager = ({userPermissions, userProviderInfo}) => {

    const confirm = useConfirm();

    const [tableLoader, setTableLoader] = useState(true);
    const [modalLoader, setModalLoader] = useState(false);
    const [showNotification, setShowNotification] = useState(false);
    const [notificationMessage, setNotificationMessage] = useState('');
    const [notificationType, setNotificationType] = useState('success');
    const [openModal, setOpenModal] = useState(false);
    const [modalType, setModalType] = useState('create');
    const [selectedFromDate, setSelectedFromDate] = useState(null);
    const [selectedToDate, setSelectedToDate] = useState(null);
    const [selectedDoctor, setSelectedDoctor] = useState([]);
    const [selectedUser, setSelectedUser] = useState([]);
    const [meetUser, setMeetUser] = useState('');
    const [meetDoctor, setMeetDoctor] = useState('');
    const [meetDate, setMeetDate] = useState('');
    const [meetProvider, setMeetProvider] = useState('');
    const [selectedStatus, setSelectedStatus] = useState([]);
    const [paginationPage, setPaginationPage] = useState(1);
    const [currentMeet, setCurrentMeet] = useState(null);

    const [dateError, setDateError] = useState(false);
    const [doctorError, setDoctorError] = useState(false);
    const [userError, setUserError] = useState(false);
    const [providerError, setProviderError] = useState(false);
    const [providerErrorText, setProviderErrorText] = useState(null);

    const [permissions, setPermissions] = useState(userPermissions);
    useEffect(() => {
        setPermissions(userPermissions)
    }, [userPermissions]);

    const [currentUserProviderInfo, setCurrentUserProviderInfo] = useState(userProviderInfo);
    useEffect(() => {
        setCurrentUserProviderInfo(userProviderInfo);
    }, [userProviderInfo]);

    const [doctors, setDoctors] = useState([]);
    const doctorsHook = useApiFetch('doctors');
    useEffect(() => {
        if (doctorsHook && doctorsHook.apiData) {
            if (doctorsHook.apiData.doctors) {
                setDoctors(doctorsHook.apiData.doctors);
            }
        } else if (doctorsHook.apiError) {
            console.error('API Error:', doctorsHook.apiError);
        }
    }, [doctorsHook.apiData, doctorsHook.apiError]);

    const [patients, setPatients] = useState([])
    const patientsHook = useApiFetch('patients');
    useEffect(() => {
        if (patientsHook && patientsHook.apiData) {
            if (patientsHook.apiData.patients) {
                setPatients(patientsHook.apiData.patients);
            }
        } else if (patientsHook.apiError) {
            console.error('API Error:', patientsHook.apiError);
        }
    }, [patientsHook.apiData, patientsHook.apiError]);

    const [meetings, setMeetings] = useState([]);
    const [meetingsPagination, setMeetingsPagination] = useState(null);
    const isInitialLoad = useRef(true);
    const meetingsHook = useApiFetch('meetings');
    useEffect(() => {
        if (meetingsHook && meetingsHook.apiData) {
            if (meetingsHook.apiData.meetings) {
                setMeetings(meetingsHook.apiData.meetings);
            }
            if (meetingsHook.apiData.pagination) {
                setMeetingsPagination(meetingsHook.apiData.pagination);
                setPaginationPage(meetingsHook.apiData.pagination.current_page);
            }
        } else if (meetingsHook.apiError) {
            console.error('API Error:', meetingsHook.apiError);
        }
    }, [meetingsHook.apiData, meetingsHook.apiError]);

    useEffect( () => {
        setPaginationPage(1);
    }, [selectedStatus, selectedDoctor, selectedUser, selectedFromDate, selectedToDate]);

    useEffect( () => {

        if (modalLoader === true) return;

        const filters = {
            status: selectedStatus,
            doctor_id: selectedDoctor,
            user_id: selectedUser,
            date_from: selectedFromDate,
            date_to: selectedToDate,
            page: paginationPage
        };

        if (isInitialLoad.current) {
            isInitialLoad.current = false;
            return;
        }

        const queryString = Object.keys(filters)
            .filter(key => filters[key])
            .map(key => `${encodeURIComponent(key)}=${encodeURIComponent(filters[key])}`)
            .join('&');
        meetingsHook.executeApi(null, 'meetings?' + queryString)
    }, [selectedStatus, selectedDoctor, selectedUser, selectedFromDate, selectedToDate, paginationPage, modalLoader]);

    useEffect(() => {
        if (meetingsHook.isApiPending) {
            setTableLoader(true);
        } else {
            setTimeout(() => {
                setTableLoader(false);
            }, 1000)
        }
    }, [meetingsHook.isApiPending, meetingsHook.apiData]);

    const handleOpenModal = (type, meet = null) => {
        setModalType(type);
        if (type === 'edit' && meet) {
            setMeetDate(dayjs(meet.date));
            setMeetDoctor(meet.doctor.id);
            setMeetUser(meet.user.id);
            setMeetProvider(meet.provider);
            setCurrentMeet(meet);
        } else {
            resetForm();
        }
        setOpenModal(true);
    };

    const handleCloseModal = () => {
        resetForm();
        setOpenModal(false);
    };

    const resetForm = () => {
        setMeetDate(null);
        setMeetDoctor('');
        setMeetUser('');
        setMeetProvider('');
        setDateError(false);
        setDoctorError(false);
        setUserError(false);
        setProviderError(false);
        setCurrentMeet(null);
        setProviderErrorText(null);
    };

    const createMeetHook = useApiFetch('create-meet', 'POST', null, false);
    const isInitCreateMeetHook = useRef(true);
    const handleCreateOrUpdateMeet = async () => {
        const isValid = validateForm();

        if (!isValid) return;

        const meetData = {
            doctor_id: meetDoctor,
            client_id: meetUser,
            type: meetProvider,
            date: meetDate,
            meet_id: currentMeet?.id,
        }

        handleCloseModal();
        setModalLoader(true);

        if (modalType === 'create') {
            await createMeetHook.executeApi({
                doctor_id: meetData.doctor_id,
                client_id: meetData.client_id,
                type: meetData.type,
                date: meetData.date,
            });
        } else if (modalType === 'edit') {
            await createMeetHook.executeApi({
                meet_id: meetData.meet_id,
                type: meetData.type,
                date: meetData.date,
            });
        }
        setModalLoader(false);
    };

    useEffect(() => {

        if (isInitCreateMeetHook.current) {
            isInitCreateMeetHook.current = false;
            return;
        }

        if (modalLoader) return;

        if (createMeetHook.apiError) {
            setNotificationType('error');
            setNotificationMessage(__('Error. Try again', 'google-meet-and-zoom-integration'));
        } else {
            setNotificationType('success');
            setNotificationMessage(__('Success. Data is updated', 'google-meet-and-zoom-integration'));
        }

        setShowNotification(true);

    }, [createMeetHook.apiError, modalLoader]);

    const validateForm = () => {
        let valid = true;

        if (!meetDate) {
            setDateError(true);
            valid = false;
        } else {
            setDateError(false);
        }

        if (!meetDoctor) {
            setDoctorError(true);
            valid = false;
        } else {
            setDoctorError(false);
        }

        if (!meetUser) {
            setUserError(true);
            valid = false;
        } else {
            setUserError(false);
        }

        if (!meetProvider) {
            setProviderError(true);
            valid = false;
        } else {
            setProviderError(false);
        }

        if (meetDoctor && modalType !== 'edit') {
            const doctor = doctors.find((doctor) => doctor.user_id === meetDoctor);
            if (!doctor[meetProvider]) {
                setProviderErrorText(__( 'Selected doctor doesn\'t connect selected provider. The meet will be created by current user.', 'google-meet-and-zoom-integration'));
            }
            if (!doctor[meetProvider] && !currentUserProviderInfo[meetProvider]) {
                valid = false;
                setProviderErrorText(__( 'Selected doctor and current user don\'t connect selected provider. Please set up the selected provider first.', 'google-meet-and-zoom-integration'));
            }
        }

        return valid;
    };

    const handlePageChange = (event, value) => {
        setPaginationPage(value);
    };

    const cancelMeetHook = useApiFetch('cancel-meet', 'POST', null, false);
    const isInitCancelMeetHook = useRef(true);
    const handleCancelMeet = async (meet) => {
        await confirm({
            title: __( 'Cancel meet', 'google-meet-and-zoom-integration'),
            description: __( 'Do you want to cancel meet?', 'google-meet-and-zoom-integration'),
            confirmationText: __( 'Yes', 'google-meet-and-zoom-integration'),
            cancellationText: __( 'No', 'google-meet-and-zoom-integration'),
            confirmationButtonProps: {
                className: 'cancel-meet-btn',
            },
            cancellationButtonProps: {
                className: 'cancel-meet-btn',
            }
        }).then(async () => {
            setModalLoader(true);
            await cancelMeetHook.executeApi({ meet_id: meet.id, type: meet.provider })
            setModalLoader(false);
        }).catch((error) => {
            console.log(error);
        })
    }

    useEffect(() => {

        if (isInitCancelMeetHook.current) {
            isInitCancelMeetHook.current = false;
            return;
        }

        if (modalLoader) return;

        if (cancelMeetHook.apiError) {
            setNotificationType('error');
            setNotificationMessage(__('Error. Try again', 'google-meet-and-zoom-integration'));
        } else {
            setNotificationType('success');
            setNotificationMessage(__('Success. Data is updated', 'google-meet-and-zoom-integration'));
        }

        setShowNotification(true);

    }, [cancelMeetHook.apiError, modalLoader]);

    const downloadMeetHook = useApiFetch('download', 'POST', null, false);
    const isInitDownloadMeetHook = useRef(true);
    const handleDownloadMeet = async (meet) => {
        setModalLoader(true);
        await downloadMeetHook.executeApi({ meet_id: meet.id, type: meet.provider })
        setModalLoader(false);
    }

    useEffect(() => {

        if (isInitDownloadMeetHook.current) {
            isInitDownloadMeetHook.current = false;
            return;
        }

        if (modalLoader) return;

        if (downloadMeetHook.apiError) {
            setNotificationType('error');
            setNotificationMessage(__('Error. Try again', 'google-meet-and-zoom-integration'));
        } else {
            setNotificationType('success');
            setNotificationMessage(__('Записів під час міту не велося', 'google-meet-and-zoom-integration'));
        }

        setShowNotification(true);

    }, [downloadMeetHook.apiError, modalLoader]);

    const modalStyle = {
        position: 'absolute',
        top: '50%',
        left: '50%',
        transform: 'translate(-50%, -50%)',
        width: 400,
        bgcolor: 'background.paper',
        border: '2px solid #000',
        boxShadow: 24,
        p: 4,
    };

    return (
        <div>
            {permissions && permissions.meet.create && (
                <Button
                    sx={{float: 'right', mb: 2}}
                    variant="contained"
                    onClick={() => handleOpenModal('create')}
                >
                    {__( 'Create Meet', 'google-meet-and-zoom-integration')}
                </Button>
            )}

            <Backdrop
                sx={() => ({ color: '#fff', zIndex: 2000 })}
                open={modalLoader}
            >
                <CircularProgress color="inherit" />
            </Backdrop>

            <Snackbar
                open={showNotification}
                autoHideDuration={3000}
                onClose={() => setShowNotification(false)}
                anchorOrigin={{
                    vertical: 'bottom',
                    horizontal: 'right'
                }}
            >
                <Alert
                    onClose={() => setShowNotification(false)}
                    severity={notificationType}
                    variant="filled"
                    sx={{ width: '100%' }}
                >
                    {notificationMessage}
                </Alert>
            </Snackbar>

            <Modal open={openModal} onClose={handleCloseModal} className="meet-modal">
                <Box sx={modalStyle}>
                    <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
                        <h2>{modalType === 'create' ? __( 'Create Meet', 'google-meet-and-zoom-integration') : __( 'Edit Meet', 'google-meet-and-zoom-integration')}</h2>
                        <IconButton onClick={handleCloseModal}>
                            <CloseIcon />
                        </IconButton>
                    </Box>

                    <DateTimePicker
                        label={__( 'Date', 'google-meet-and-zoom-integration')}
                        value={meetDate}
                        timezone="Europe/Kiev"
                        onChange={(newValue) => {
                            setMeetDate(newValue);
                            setDateError(false);
                        }}
                        renderInput={(params) => (
                            <TextField
                                {...params}
                                fullWidth
                                size="small"
                                required
                                error={dateError}
                                helperText={dateError && __( 'Date is required', 'google-meet-and-zoom-integration')}
                            />
                        )}
                        slotProps={{ textField: { size: 'small', fullWidth: true, required: true, error: dateError, helperText: dateError && __( 'Date is required', 'google-meet-and-zoom-integration') } }}
                        className="meet-date"
                    />

                    <FormControl fullWidth margin="normal" required error={doctorError} size="small">
                        <InputLabel>{__( 'Doctor', 'google-meet-and-zoom-integration')}</InputLabel>
                        <Select
                            value={meetDoctor}
                            onChange={(e) => {
                                setMeetDoctor(e.target.value);
                                setDoctorError(false);
                            }}
                            disabled={modalType === 'edit'}
                            input={<OutlinedInput label={__( 'Doctor', 'google-meet-and-zoom-integration')} />}
                        >
                            {doctors.map((doctor) => (
                                <MenuItem key={doctor.user_id} value={doctor.user_id}>
                                    {doctor.name}
                                </MenuItem>
                            ))}
                        </Select>
                        {doctorError && <FormHelperText>{__( 'Doctor is required', 'google-meet-and-zoom-integration')}</FormHelperText>}
                    </FormControl>

                    <FormControl fullWidth margin="normal" required error={userError} size="small">
                        <InputLabel>{__( 'Client', 'google-meet-and-zoom-integration')}</InputLabel>
                        <Select
                            value={meetUser}
                            onChange={(e) => {
                                setMeetUser(e.target.value);
                                setUserError(false);
                            }}
                            disabled={modalType === 'edit'}
                            input={<OutlinedInput label={__( 'Client', 'google-meet-and-zoom-integration')} />}
                        >
                            {patients.map((patient) => (
                                <MenuItem key={patient.user_id} value={patient.user_id}>
                                    {patient.name}
                                </MenuItem>
                            ))}
                        </Select>
                        {userError && <FormHelperText>{__( 'Client is required', 'google-meet-and-zoom-integration')}</FormHelperText>}
                    </FormControl>

                    <FormControl fullWidth margin="normal" required error={providerError} size="small">
                        <InputLabel>{__( 'Meet Provider', 'google-meet-and-zoom-integration')}</InputLabel>
                        <Select
                            value={meetProvider}
                            onChange={(e) => {
                                setMeetProvider(e.target.value);
                                setProviderError(false);
                            }}
                            disabled={modalType === 'edit'}
                            input={<OutlinedInput label={__( 'Meet Provider', 'google-meet-and-zoom-integration')} />}
                        >
                            <MenuItem value="google">{__( 'Google', 'google-meet-and-zoom-integration')}</MenuItem>
                            <MenuItem value="zoom">{__( 'Zoom', 'google-meet-and-zoom-integration')}</MenuItem>
                        </Select>
                        {providerError && <FormHelperText>{__( 'Meet Provider is required', 'google-meet-and-zoom-integration')}</FormHelperText>}
                    </FormControl>

                    {providerErrorText && (
                        <FormControl sx={{my: 2}} fullWidth margin="normal" required error={providerError} size="small">
                            <FormHelperText sx={{color: 'red'}}>{providerErrorText}</FormHelperText>
                        </FormControl>
                    )}

                    <Button variant="contained" color="primary" onClick={handleCreateOrUpdateMeet}>
                        {modalType === 'create' ? __( 'Create', 'google-meet-and-zoom-integration') : __( 'Update', 'google-meet-and-zoom-integration')}
                    </Button>
                </Box>
            </Modal>

            <Box sx={{ width: '100%', display: 'flex', gap: 2, mb: 2 }}>
                <FormControl sx={{ minWidth: 150 }} size="small">
                    <InputLabel>{__( 'Status', 'google-meet-and-zoom-integration')}</InputLabel>
                    <Select
                        multiple
                        value={selectedStatus}
                        onChange={(e) => setSelectedStatus(e.target.value)}
                        input={<OutlinedInput label={__( 'Status', 'google-meet-and-zoom-integration')} />}
                        endAdornment={
                            selectedStatus.length > 0 && (
                                <InputAdornment sx={{ marginRight: "10px" }} position="end">
                                    <IconButton
                                        onClick={() => {
                                            setSelectedStatus([]);
                                        }}
                                    >
                                        <ClearIcon></ClearIcon>
                                    </IconButton>
                                </InputAdornment>
                            )
                        }
                    >
                        <MenuItem value="new">{__( 'Active', 'google-meet-and-zoom-integration')}</MenuItem>
                        <MenuItem value="passed">{__( 'Passed', 'google-meet-and-zoom-integration')}</MenuItem>
                        <MenuItem value="canceled">{__( 'Canceled', 'google-meet-and-zoom-integration')}</MenuItem>
                    </Select>
                </FormControl>

                {permissions && permissions.filters.doctor && (
                    <FormControl sx={{ minWidth: 150 }} size="small">
                        <InputLabel>{__( 'Doctor', 'google-meet-and-zoom-integration')}</InputLabel>
                        <Select
                            multiple
                            value={selectedDoctor}
                            onChange={(e) => setSelectedDoctor(e.target.value)}
                            input={<OutlinedInput label={__( 'Doctor', 'google-meet-and-zoom-integration')} />}
                            endAdornment={
                                selectedDoctor.length > 0 && (
                                    <InputAdornment sx={{ marginRight: "10px" }} position="end">
                                        <IconButton
                                            onClick={() => {
                                                setSelectedDoctor([]);
                                            }}
                                        >
                                            <ClearIcon></ClearIcon>
                                        </IconButton>
                                    </InputAdornment>
                                )
                            }
                        >
                            {doctors.map((doctor) => (
                                <MenuItem key={doctor.user_id} value={doctor.user_id}>
                                    {doctor.name}
                                </MenuItem>
                            ))}
                        </Select>
                    </FormControl>
                )}

                {permissions && permissions.filters.users && (
                    <FormControl sx={{ minWidth: 150 }} size="small">
                        <InputLabel>{__( 'Client', 'google-meet-and-zoom-integration')}</InputLabel>
                        <Select
                            multiple
                            value={selectedUser}
                            onChange={(e) => setSelectedUser(e.target.value)}
                            input={<OutlinedInput label={__( 'Client', 'google-meet-and-zoom-integration')} />}
                            endAdornment={
                                selectedUser.length > 0 && (
                                    <InputAdornment sx={{ marginRight: "10px" }} position="end">
                                        <IconButton
                                            onClick={() => {
                                                setSelectedDoctor([]);
                                            }}
                                        >
                                            <ClearIcon></ClearIcon>
                                        </IconButton>
                                    </InputAdornment>
                                )
                            }
                        >
                            {patients.map((patient) => (
                                <MenuItem key={patient.user_id} value={patient.user_id}>
                                    {patient.name}
                                </MenuItem>
                            ))}
                        </Select>
                    </FormControl>
                )}

                <DatePicker
                    label={__( 'Date From', 'google-meet-and-zoom-integration')}
                    value={selectedFromDate}
                    onChange={(newValue) => setSelectedFromDate(newValue)}
                    renderInput={(params) => <TextField size="small" {...params} />}
                    slotProps={{ textField: { size: 'small' }, field: {clearable: true} }}
                    className="meet-date"
                />

                <DatePicker
                    label={__( 'Date To', 'google-meet-and-zoom-integration')}
                    value={selectedToDate}
                    onChange={(newValue) => setSelectedToDate(newValue)}
                    renderInput={(params) => <TextField size="small" {...params} />}
                    slotProps={{ textField: { size: 'small' }, field: {clearable: true} }}
                    className="meet-date"
                />

            </Box>

            {tableLoader === true && (
                <Stack sx={{ width: '100%', my: 4 }} spacing={2}>
                    <LinearProgress />
                </Stack>
            )}

            {tableLoader === false && meetings.length > 0 && (
                <TableContainer>
                    <Table>
                        <TableHead>
                            <TableRow>
                                <TableCell>ID</TableCell>
                                <TableCell>{__( 'Status', 'google-meet-and-zoom-integration')}</TableCell>
                                <TableCell>{__( 'Date', 'google-meet-and-zoom-integration')}</TableCell>
                                <TableCell>{__( 'Doctor', 'google-meet-and-zoom-integration')}</TableCell>
                                <TableCell>{__( 'Client', 'google-meet-and-zoom-integration')}</TableCell>
                                <TableCell>{__( 'Meet Provider', 'google-meet-and-zoom-integration')}</TableCell>
                                <TableCell>{__( 'Actions', 'google-meet-and-zoom-integration')}</TableCell>
                            </TableRow>
                        </TableHead>
                        <TableBody>
                            {meetings.map((meet) => (
                                <TableRow key={meet.id}>
                                    <TableCell>{meet.id}</TableCell>
                                    <TableCell>{meet.status_name}</TableCell>
                                    <TableCell>{meet.date}</TableCell>
                                    <TableCell>{meet.doctor.name}</TableCell>
                                    <TableCell>{meet.user.name}</TableCell>
                                    <TableCell>{meet.provider}</TableCell>
                                    <TableCell>
                                        <Box
                                            sx={{display: 'flex'}}
                                        >
                                            {meet.status === 'new' && (
                                                <Tooltip
                                                    title={__( 'Copy meet link', 'google-meet-and-zoom-integration')}
                                                    onClick={() => navigator.clipboard.writeText(meet.link)}
                                                >
                                                    <IconButton
                                                        size="small"
                                                    >
                                                        <CopyIcon />
                                                    </IconButton>
                                                </Tooltip>
                                            )}
                                            {permissions && permissions.meet.edit && meet.status === 'new' && (
                                                <Tooltip title={__( 'Edit meet', 'google-meet-and-zoom-integration')}>
                                                    <IconButton
                                                        size="small"
                                                        onClick={() => handleOpenModal('edit', meet)}
                                                    >
                                                        <EditIcon />
                                                    </IconButton>
                                                </Tooltip>
                                            )}
                                            {permissions && permissions.meet.cancel && meet.status === 'new' && (
                                                <Tooltip
                                                    title={__( 'Cancel meet', 'google-meet-and-zoom-integration')}
                                                >
                                                    <IconButton
                                                        size="small"
                                                        onClick={() => handleCancelMeet(meet)}
                                                    >
                                                        <DeleteIcon />
                                                    </IconButton>
                                                </Tooltip>
                                            )}
                                            {permissions && permissions.meet.download && meet.status === 'passed' && (
                                                <Tooltip title={__( 'Download record', 'google-meet-and-zoom-integration')}>
                                                    <IconButton
                                                        size="small"
                                                        onClick={() => handleDownloadMeet(meet)}
                                                    >
                                                        <DownloadIcon />
                                                    </IconButton>
                                                </Tooltip>
                                            )}
                                        </Box>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </TableContainer>
            )}

            {tableLoader === false && meetingsPagination && meetingsPagination.total_pages > 0 && (
                <Box sx={{ display: 'flex', justifyContent: 'center', mt: 2 }}>
                    <Pagination count={meetingsPagination.total_pages} page={paginationPage} onChange={handlePageChange} />
                </Box>
            )}

            {tableLoader === false && meetings.length === 0 && (
                <Box sx={{ display: 'flex', justifyContent: 'center', mt: 2 }}>
                    <Typography variant="h6">{__( 'No meetings', 'google-meet-and-zoom-integration')}</Typography>
                </Box>
            )}

        </div>
    );
};


export default MeetManager;
