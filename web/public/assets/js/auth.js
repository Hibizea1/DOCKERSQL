(function () {
    const REFRESH_ENDPOINT = "/php/refresh.php";
    let refreshPromise = null;

    function decodeJwtPayload(token) {
        try {
            const parts = token.split(".");
            if (parts.length !== 3) {
                return null;
            }

            const payloadBase64 = parts[1].replace(/-/g, "+").replace(/_/g, "/");
            const payloadJson = atob(payloadBase64);
            return JSON.parse(payloadJson);
        } catch (error) {
            return null;
        }
    }

    function shouldRefresh(token) {
        if (!token) {
            return true;
        }

        const payload = decodeJwtPayload(token);
        if (!payload || typeof payload.exp !== "number") {
            return true;
        }

        const now = Math.floor(Date.now() / 1000);
        return payload.exp - now < 60;
    }

    async function refreshAccessToken() {
        if (refreshPromise) {
            return refreshPromise;
        }

        refreshPromise = (async () => {
            const response = await fetch(REFRESH_ENDPOINT, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    "X-Client-Type": "web"
                },
                credentials: "include",
                body: JSON.stringify({})
            });

            const data = await response.json().catch(() => ({}));
            if (!response.ok || data.status !== "success" || !data.access_token) {
                sessionStorage.removeItem("access_token");
                throw new Error(data.status || "refresh_failed");
            }

            sessionStorage.setItem("access_token", data.access_token);
            return data.access_token;
        })();

        try {
            return await refreshPromise;
        } finally {
            refreshPromise = null;
        }
    }

    async function getValidAccessToken() {
        const token = sessionStorage.getItem("access_token");
        if (shouldRefresh(token)) {
            return refreshAccessToken();
        }
        return token;
    }

    async function authFetch(url, options) {
        const requestOptions = options ? { ...options } : {};
        const requestHeaders = requestOptions.headers ? { ...requestOptions.headers } : {};
        const token = await getValidAccessToken();

        if (token) {
            requestHeaders.Authorization = "Bearer " + token;
        }

        requestHeaders["X-Client-Type"] = "web";
        requestOptions.headers = requestHeaders;
        requestOptions.credentials = "include";

        return fetch(url, requestOptions);
    }

    function startAutoRefresh() {
        const token = sessionStorage.getItem("access_token");
        if (!token) {
            return;
        }

        refreshAccessToken().catch(() => {
            // If refresh fails, next protected request will handle auth failure.
        });

        window.setInterval(() => {
            const currentToken = sessionStorage.getItem("access_token");
            if (!currentToken) {
                return;
            }

            if (shouldRefresh(currentToken)) {
                refreshAccessToken().catch(() => {
                    // Silent retry strategy for background refresh.
                });
            }
        }, 30000);
    }

    window.AuthClient = {
        refreshAccessToken,
        getValidAccessToken,
        authFetch,
        startAutoRefresh
    };

    document.addEventListener("DOMContentLoaded", () => {
        startAutoRefresh();
    });
})();
