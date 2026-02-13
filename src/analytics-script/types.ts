export interface ScriptConfig {
    analyticsHost: string;
    siteId: string;
    debounceDuration: number;
    autoTrackPageview: boolean;
    autoTrackSpa: boolean;
    trackQuerystring: boolean;
    trackOutbound: boolean;
    enableWebVitals: boolean;
    trackErrors: boolean;
    skipPatterns: string[];
    maskPatterns: string[];
    trackButtonClicks: boolean;
    trackCopy: boolean;
    trackFormInteractions: boolean;
}

export interface BasePayload {
    siteId: string;
    hostname: string;
    pathname: string;
    querystring: string;
    screenWidth: number;
    screenHeight: number;
    language: string;
    pageTitle: string;
    referrer: string;
    userId?: string;
}

export interface TrackingPayload extends BasePayload {
    type: "pageview" | "custom_event" | "outbound" | "performance" | "error" | "button_click" | "copy" | "form_submit" | "input_change";
    eventName?: string;
    properties?: string;
    // Web vitals metrics
    lcp?: number | null;
    cls?: number | null;
    inp?: number | null;
    fcp?: number | null;
    ttfb?: number | null;
}

export interface WebVitalsData {
    lcp: number | null;
    cls: number | null;
    inp: number | null;
    fcp: number | null;
    ttfb: number | null;
}

export interface ErrorProperties {
    filename?: string;
    lineno?: number | string;
    colno?: number | string;
    [key: string]: any;
}

export interface LukaLtaAPI {
    pageview: () => void;
    event: (name: string, properties?: Record<string, any>) => void;
    error: (error: Error, properties?: ErrorProperties) => void;
    trackOutbound: (url: string, text?: string, target?: string) => void;
    identify: (userId: string) => void;
    clearUserId: () => void;
    getUserId: () => string | null;
}

export interface ButtonClickProperties {
    text?: string;
    [key: string]: string | undefined; // Additional data-lukalta-* attributes
}

export interface CopyProperties {
    text: string;
    textLength?: number; // Only sent if text was truncated
    sourceElement: string;
}

export interface FormSubmitProperties {
    formId: string;
    formName: string;
    formAction: string;
    method: string;
    fieldCount: number;
    [key: string]: string | number | undefined;
}

export interface InputChangeProperties {
    element: string; // "input" | "select" | "textarea"
    inputType?: string; // For inputs: "text", "email", "checkbox", etc.
    inputName: string; // Name or id attribute
    formId?: string; // Parent form id if within a form
    [key: string]: string | undefined;
}