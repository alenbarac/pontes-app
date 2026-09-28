import {
    Table,
    TableHeader,
    TableBody,
    TableRow,
    TableCell,
} from "@/Components/ui/table";
import SlipMailingBadge from "@/Components/Member/SlipMailingBadge";
import {
    invoiceMonthLabel,
    reminderMailingPresentation,
} from "@/utils/slipMailing";

const headerClass =
    "px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase dark:text-gray-400";
const cellClass = "px-4 py-3 align-middle text-sm text-gray-700 dark:text-gray-300";

function invoicesNewestFirst(invoicesByWorkshop) {
    const rows = Object.values(invoicesByWorkshop || {}).flat();

    return rows.sort((a, b) => {
        const byDate = String(b.due_date ?? "").localeCompare(String(a.due_date ?? ""));
        if (byDate !== 0) {
            return byDate;
        }

        return (b.id ?? 0) - (a.id ?? 0);
    });
}

function EmptyValue() {
    return <span className="text-gray-400 dark:text-gray-500">—</span>;
}

function Note({ resent, error }) {
    if (!resent && !error) {
        return <EmptyValue />;
    }

    return (
        <div className="flex flex-col gap-0.5">
            {resent && (
                <span className="text-xs text-gray-500 dark:text-gray-400">
                    ponovno poslana
                </span>
            )}
            {error && (
                <span className="text-xs text-red-600 dark:text-red-400">{error}</span>
            )}
        </div>
    );
}

export default function MemberSendHistory({ invoicesByWorkshop = {} }) {
    const invoices = invoicesNewestFirst(invoicesByWorkshop);
    const workshopIds = new Set(
        invoices.map((invoice) => invoice.workshop_id).filter((id) => id != null),
    );
    const showWorkshop = workshopIds.size > 1;

    if (invoices.length === 0) {
        return (
            <p className="text-sm text-gray-500 dark:text-gray-400">
                Nema računa za evidenciju slanja.
            </p>
        );
    }

    return (
        <div className="overflow-x-auto">
            <Table className="w-full">
                <TableHeader>
                    <TableRow className="border-b border-gray-200 dark:border-gray-700">
                        <TableCell isHeader className={headerClass}>
                            Uplatnica
                        </TableCell>
                        {showWorkshop && (
                            <TableCell isHeader className={headerClass}>
                                Radionica
                            </TableCell>
                        )}
                        <TableCell isHeader className={headerClass}>
                            Slanje
                        </TableCell>
                        <TableCell isHeader className={headerClass}>
                            Primatelj
                        </TableCell>
                        <TableCell isHeader className={headerClass}>
                            Napomena
                        </TableCell>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {invoices.map((invoice) => {
                        const month = invoiceMonthLabel(invoice.due_date);
                        const reminder = reminderMailingPresentation(invoice.reminder_mailing);
                        const workshopName = invoice.workshop?.name;

                        return (
                            <InvoiceRows
                                key={invoice.id}
                                invoice={invoice}
                                month={month}
                                reminder={reminder}
                                workshopName={workshopName}
                                showWorkshop={showWorkshop}
                            />
                        );
                    })}
                </TableBody>
            </Table>
        </div>
    );
}

function InvoiceRows({ invoice, month, reminder, workshopName, showWorkshop }) {
    const slip = invoice.slip_mailing;

    return (
        <>
            <TableRow className="border-b border-gray-200 dark:border-gray-700">
                <TableCell className={`${cellClass} font-medium text-gray-900 dark:text-white`}>
                    {month ? `Uplatnica ${month}` : "Uplatnica"}
                </TableCell>
                {showWorkshop && (
                    <TableCell className={cellClass}>{workshopName || <EmptyValue />}</TableCell>
                )}
                <TableCell className={cellClass}>
                    <SlipMailingBadge mailing={slip} />
                </TableCell>
                <TableCell className={cellClass}>
                    {slip?.recipient || <EmptyValue />}
                </TableCell>
                <TableCell className={cellClass}>
                    <Note resent={Boolean(slip?.resent)} error={slip?.status === "failed" ? slip?.error : null} />
                </TableCell>
            </TableRow>
            {reminder && (
                <TableRow className="border-b border-gray-200 bg-gray-50 dark:border-gray-700 dark:bg-white/[0.02]">
                    <TableCell className={`${cellClass} text-gray-600 dark:text-gray-300`}>
                        Opomena {month}
                    </TableCell>
                    {showWorkshop && <TableCell className={cellClass} />}
                    <TableCell className={cellClass}>
                        <SlipMailingBadge mailing={invoice.reminder_mailing} />
                    </TableCell>
                    <TableCell className={cellClass}>
                        {reminder.recipient || <EmptyValue />}
                    </TableCell>
                    <TableCell className={cellClass}>
                        <Note error={reminder.error} />
                    </TableCell>
                </TableRow>
            )}
        </>
    );
}
