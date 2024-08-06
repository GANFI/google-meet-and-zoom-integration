import * as React from 'react';
import Button from '@mui/material/Button';
import { Google } from "@mui/icons-material";


export default function googleProvider() {
    return (
        <Button
            component="label"
            role={undefined}
            variant="contained"
            tabIndex={-1}
            startIcon={<Google />}
        >
            Connect google
        </Button>
    );
}
