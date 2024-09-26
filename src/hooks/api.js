import { useState, useEffect } from "react";
import tokens from "../utils/token";

export const useApiFetch = (route, method = 'GET', requestData = null, executeOnMount = true) => {

    const [apiData, setApiData] = useState(null);
    const [isApiPending, setIsApiPending] = useState(false);
    const [apiError, setApiError] = useState(null);

    const handleData = async (requestData = null, url = null) => {
        setIsApiPending(true);
        let apiUrl = url ? url : route;
        try {
            const fetchURL = new URL(`/wp-json/google-meet-and-zoom-integration/v1/${apiUrl}`, window.location.origin);
            const response = await fetch(fetchURL.href, {
                method,
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': tokens.Token,
                },
                ...(requestData && { body: JSON.stringify(requestData) })
            });
            if (!response.ok) throw new Error(response.statusText);
            const json = await response.json();
            setApiData(json?.data || {});
            setApiError(null);
            setIsApiPending(false);
        } catch (error) {
            setApiError(`${error} Could not Fetch Data`);
        } finally {
            setIsApiPending(false);
        }
    };

    useEffect(() => {
        if (executeOnMount) {
            const fetchData = async () => {
                await handleData(requestData);
            };
            fetchData();
        }
    }, [route, method, requestData, executeOnMount]);

    return { apiData, isApiPending, apiError, executeApi: handleData };
};
