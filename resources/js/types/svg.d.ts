declare module '*.svg?react' {
    import * as React from 'react';
    export const ReactComponent: React.FunctionComponent<React.SVGProps<SVGSVGElement>>;
}

declare module '*.svg' {
    const content: string;
    export default content;
}
