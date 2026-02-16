// Type declarations for icon imports - permissive approach for build
// Using any to allow all icon exports without strict typing
declare module '@/icons' {
    export const CalenderIcon: any;
    export const EyeIcon: any;
    export const EyeCloseIcon: any;
    export const TimeIcon: any;
    export const EnvelopeIcon: any;
    export const ArrowRightIcon: any;
    export const LockIcon: any;
    export const UserIcon: any;
    export const MoreDotIcon: any;
    export const ErrorHexaIcon: any;
    export const AlertHexaIcon: any;
    export const DownloadIcon: any;
    export const FileIcon: any;
    export const GridIcon: any;
    export const AudioIcon: any;
    export const VideoIcon: any;
    export const BoltIcon: any;
    export const PlusIcon: any;
    export const BoxIcon: any;
    export const CloseIcon: any;
    export const CheckCircleIcon: any;
    export const AlertIcon: any;
    export const InfoIcon: any;
    export const ErrorIcon: any;
    export const ArrowUpIcon: any;
    export const FolderIcon: any;
    export const ArrowDownIcon: any;
    export const GroupIcon: any;
    export const BoxIconLine: any;
    export const ShootingStarIcon: any;
    export const DollarLineIcon: any;
    export const TrashBinIcon: any;
    export const AngleUpIcon: any;
    export const AngleDownIcon: any;
    export const PencilIcon: any;
    export const CheckLineIcon: any;
    export const CloseLineIcon: any;
    export const ChevronDownIcon: any;
    export const PaperPlaneIcon: any;
    export const UserCircleIcon: any;
    export const TaskIcon: any;
    export const ListIcon: any;
    export const TableIcon: any;
    export const PageIcon: any;
    export const PieChartIcon: any;
    export const BoxCubeIcon: any;
    export const PlugInIcon: any;
    export const DocsIcon: any;
    export const MailIcon: any;
    export const HorizontaLDots: any;
    export const ChevronUpIcon: any;
    export const ChatIcon: any;
    export const CopyIcon: any;
    export const ChevronLeftIcon: any;
}

declare module '../../../icons' {
    export * from '@/icons';
}

declare module '../../icons' {
    export * from '@/icons';
}

declare module '../icons' {
    export * from '@/icons';
}

// Allow importing from icons directory
declare module '*.svg?react' {
    import * as React from 'react';
    export const ReactComponent: React.FunctionComponent<React.SVGProps<SVGSVGElement>>;
}
