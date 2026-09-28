import Badge from "@/ui/badge/Badge";
import { slipMailingPresentation } from "@/utils/slipMailing";

export default function SlipMailingBadge({ mailing }) {
    const view = slipMailingPresentation(mailing);
    const title = view.error || view.recipient || undefined;

    return (
        <span title={title}>
            <Badge variant="light" color={view.color} size="sm" className="whitespace-nowrap">
                {view.badge}
            </Badge>
        </span>
    );
}
