import '@inertiajs/core';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
        };
        flashDataType: {
            success?: string;
        };
    }
}
