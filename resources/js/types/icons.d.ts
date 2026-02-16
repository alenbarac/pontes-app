// Type declarations for icon imports - permissive approach for build
declare module '@/icons' {
    const exports: any;
    export = exports;
}

declare module '../../../icons' {
    const exports: any;
    export = exports;
}

declare module '../../icons' {
    const exports: any;
    export = exports;
}

declare module '../icons' {
    const exports: any;
    export = exports;
}

// Allow importing from icons directory
declare module '*.svg?react' {
    import * as React from 'react';
    export const ReactComponent: React.FunctionComponent<React.SVGProps<SVGSVGElement>>;
}
