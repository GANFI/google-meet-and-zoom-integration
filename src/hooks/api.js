import { useState, useEffect } from "react";
import Token from "../utils/token";

export const useApiFetch = (route, method = 'GET', requestData = null, executeOnMount = true) => {
    const [apiData, setApiData] = useState(null);
    const [isApiPending, setIsApiPending] = useState(false);
    const [apiError, setApiError] = useState(null);

    const handleData = async (requestData) => {
        try {
            const fetchURL = new URL(`/wp-json/google-meet-and-zoom-integration/v1/${route}`, window.location.origin);
            const response = await fetch(fetchURL.href, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': Token,
                },
                body: requestData ? JSON.stringify(requestData) : null
            });
            if (!response.ok) throw new Error(response.statusText);
            const json = await response.json();
            setIsApiPending(false);
            setApiData(json?.data || {});
            setApiError(null);
        } catch (error) {
            setApiError(`${error} Could not Fetch Data`);
            setIsApiPending(false);
        }
    }

    useEffect(async () => {
        if (executeOnMount) await handleData(requestData);
    }, []);

    return { apiData, isApiPending, apiError, executeApi: handleData };
};
