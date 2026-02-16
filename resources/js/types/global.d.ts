import { PageProps as InertiaPageProps } from '@inertiajs/core';
import { AxiosInstance } from 'axios';
import { route as ziggyRoute } from 'ziggy-js';
import { PageProps as AppPageProps } from './';

declare global {
    interface Window {
        axios: AxiosInstance;
    }

    /* eslint-disable no-var */
    var route: typeof ziggyRoute;
}

declare module '@inertiajs/core' {
    interface PageProps extends InertiaPageProps, AppPageProps {}
}

// Icon module declarations
declare module '@/icons';
declare module '../../../icons';
declare module '../../icons';
declare module '../icons';

// ComponentCard module declarations
declare module '../../common/ComponentCard';
declare module '../common/ComponentCard';
declare module '@/Components/common/ComponentCard';
