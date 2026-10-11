import { Head, router } from "@inertiajs/react";
import MonthPicker from "@/Components/form/MonthPicker";
import { useState } from "react";
import toast from "react-hot-toast";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import Breadcrumb from "@/Components/Breadcrumb";
import ComponentCard from "@/Components/common/ComponentCard";

export default function ImportMBankingReview({
    statementImport,
    mismatches = [],
    unresolved = [],
    ambiguous = [],
    resolved = [],
}) {
    const [pendingId, setPendingId] = useState(null);

    const postLine = (name, lineId, data = {}) => {
        setPendingId(lineId);
        router.post(
            route(name, [statementImport.id, lineId]),
            data,
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    const success = page.props.flash?.success;
                    if (success) {
                        toast.success(success);
                    }
                },
                onError: (errors) => {
                    toast.error(firstError(errors) || "Došlo je do greške.");
                },
                onFinish: () => setPendingId(null),
            },
        );
    };

    return (
        <AuthenticatedLayout>
            <Head title="Pregled uvoza - mBanking" />
            <Breadcrumb
                items={[
                    { label: "Uvoz - mBanking", href: route("invoices.importMbanking.index") },
                    { label: "Pregled" },
                ]}
            />

            <div className="space-y-6">
                <ComponentCard title="Rezultat uvoza">
                    <div className="space-y-4">
                        <p className="text-sm text-gray-600 dark:text-gray-400">
                            {statementImport.filename} · {statementImport.imported_at}. Pregled
                            ostaje spremljen nakon osvježavanja.
                        </p>

                        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <ResultBox label="Ukupno stavki" value={statementImport.total_rows} />
                            <ResultBox label="Ulazne uplate" value={statementImport.incoming_rows} />
                            <ResultBox label="Označeno plaćeno" value={statementImport.paid_count} color="green" />
                            <ResultBox label="Neusklađeno" value={statementImport.mismatch_count} color="orange" />
                        </div>
                        <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <ResultBox label="Neobrađeno" value={statementImport.failed_count} color="red" />
                            <ResultBox label="Nije pronađen račun" value={statementImport.unresolved_count} />
                            <ResultBox label="Više mogućih računa" value={statementImport.ambiguous_count} />
                            <ResultBox label="Preskočeno" value={statementImport.skipped_count} />
                        </div>
                    </div>
                </ComponentCard>

                <ComponentCard title={`Neusklađene stavke (${mismatches.length})`}>
                    {mismatches.length === 0 ? (
                        <EmptyText>Nema otvorenih neusklađenih stavki.</EmptyText>
                    ) : (
                        <div className="space-y-4">
                            {mismatches.map((line) => (
                                <MismatchRow
                                    key={line.id}
                                    line={line}
                                    disabled={pendingId === line.id}
                                    onAlign={() => postLine("invoices.importMbanking.lines.align", line.id)}
                                    onApplyPlan={(planId) =>
                                        postLine("invoices.importMbanking.lines.applyPlan", line.id, {
                                            membership_plan_id: planId,
                                        })
                                    }
                                    onSaveNote={(note) =>
                                        postLine("invoices.importMbanking.lines.note", line.id, { note })
                                    }
                                />
                            ))}
                        </div>
                    )}
                </ComponentCard>

                <ComponentCard title={`Nije pronađen račun (${unresolved.length})`}>
                    {unresolved.length === 0 ? (
                        <EmptyText>Nema otvorenih stavki bez računa.</EmptyText>
                    ) : (
                        <div className="space-y-4">
                            {unresolved.map((line) => (
                                <ProblemRow
                                    key={line.id}
                                    line={line}
                                    disabled={pendingId === line.id}
                                    onAttach={(invoiceId) =>
                                        postLine("invoices.importMbanking.lines.attach", line.id, {
                                            invoice_id: invoiceId,
                                        })
                                    }
                                    onLinkMember={(memberId) =>
                                        postLine("invoices.importMbanking.lines.linkMember", line.id, {
                                            member_id: memberId,
                                        })
                                    }
                                    onGenerateInvoice={(memberId, workshopId, targetMonth) =>
                                        postLine("invoices.importMbanking.lines.generateInvoice", line.id, {
                                            member_id: memberId,
                                            workshop_id: workshopId,
                                            target_month: targetMonth,
                                        })
                                    }
                                    onSaveNote={(note) =>
                                        postLine("invoices.importMbanking.lines.note", line.id, { note })
                                    }
                                    onDismiss={() => postLine("invoices.importMbanking.lines.dismiss", line.id)}
                                />
                            ))}
                        </div>
                    )}
                </ComponentCard>

                {ambiguous.length > 0 && (
                    <ComponentCard title={`Više mogućih računa (${ambiguous.length})`}>
                        <div className="space-y-4">
                            {ambiguous.map((line) => (
                                <ProblemRow
                                    key={line.id}
                                    line={line}
                                    disabled={pendingId === line.id}
                                    onAttach={(invoiceId) =>
                                        postLine("invoices.importMbanking.lines.attach", line.id, {
                                            invoice_id: invoiceId,
                                        })
                                    }
                                    onLinkMember={(memberId) =>
                                        postLine("invoices.importMbanking.lines.linkMember", line.id, {
                                            member_id: memberId,
                                        })
                                    }
                                    onGenerateInvoice={(memberId, workshopId, targetMonth) =>
                                        postLine("invoices.importMbanking.lines.generateInvoice", line.id, {
                                            member_id: memberId,
                                            workshop_id: workshopId,
                                            target_month: targetMonth,
                                        })
                                    }
                                    onSaveNote={(note) =>
                                        postLine("invoices.importMbanking.lines.note", line.id, { note })
                                    }
                                    onDismiss={() => postLine("invoices.importMbanking.lines.dismiss", line.id)}
                                />
                            ))}
                        </div>
                    </ComponentCard>
                )}

                {resolved.length > 0 && (
                    <ComponentCard title={`Riješeno (${resolved.length})`}>
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead>
                                    <tr>
                                        <Th>Red</Th>
                                        <Th>Platitelj</Th>
                                        <Th>Član</Th>
                                        <Th>Referenca</Th>
                                        <Th>Iznos</Th>
                                        <Th>Ishod</Th>
                                        <Th>Bilješka</Th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-200 dark:divide-gray-700">
                                    {resolved.map((line) => (
                                        <tr key={line.id}>
                                            <Td>{line.row_number}</Td>
                                            <Td>{line.payer || "-"}</Td>
                                            <Td>
                                                <MemberLink id={line.member_id} name={line.member_name} />
                                            </Td>
                                            <Td>{line.reference || "-"}</Td>
                                            <Td>{money(line.amount_paid)}</Td>
                                            <Td>{outcomeLabel(line)}</Td>
                                            <Td>{line.note || "-"}</Td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </ComponentCard>
                )}
            </div>
        </AuthenticatedLayout>
    );
}

function MismatchRow({ line, disabled, onAlign, onApplyPlan, onSaveNote }) {
    return (
        <article className="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
            <div className="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div className="space-y-1 text-sm text-gray-800 dark:text-gray-100">
                    <p>
                        <span className="text-gray-500 dark:text-gray-400">Red {line.row_number}</span>
                        {" · "}
                        {line.payer || "-"}
                    </p>
                    <p>
                        Član: <MemberLink id={line.member_id} name={line.member_name} />
                        {line.reference ? ` · ${line.reference}` : ""}
                    </p>
                    <p>
                        Uplaćeno {money(line.amount_paid)} / račun {money(line.amount_due)}
                    </p>
                    <p className="text-gray-600 dark:text-gray-400">
                        Trenutni plan: {line.current_plan ? `${line.current_plan.name} (${money(line.current_plan.fee)})` : "nije postavljen"}
                    </p>
                </div>
                <div className="flex flex-col items-start gap-2">
                    <ActionButton onClick={onAlign} disabled={disabled}>
                        Uskladi račun s uplatom
                    </ActionButton>
                    {line.matching_plans.map((plan) => (
                        <ActionButton
                            key={plan.id}
                            onClick={() => onApplyPlan(plan.id)}
                            disabled={disabled}
                        >
                            Postavi plan {plan.name} ({money(plan.fee)}) i uskladi račun
                        </ActionButton>
                    ))}
                </div>
            </div>
            <div className="mt-3">
                <NoteField note={line.note} disabled={disabled} onSave={onSaveNote} />
            </div>
        </article>
    );
}

function ProblemRow({ line, disabled, onAttach, onLinkMember, onGenerateInvoice, onSaveNote, onDismiss }) {
    const memberSuggestions = line.member_suggestions || [];
    const hasSuggestions = line.suggestions.length > 0 || memberSuggestions.length > 0;

    return (
        <article className="rounded-xl border border-gray-200 p-4 dark:border-gray-700">
            <div className="space-y-3">
                <div className="text-sm text-gray-800 dark:text-gray-100">
                    <p>
                        <span className="text-gray-500 dark:text-gray-400">Red {line.row_number}</span>
                        {" · "}
                        {line.payer || "-"}
                        {" · "}
                        {money(line.amount_paid)}
                        {line.reference ? ` · ${line.reference}` : ""}
                        {line.value_date ? ` · ${line.value_date}` : ""}
                    </p>
                    {line.member_id && (
                        <p>
                            Član: <MemberLink id={line.member_id} name={line.member_name} />
                        </p>
                    )}
                    {line.description && (
                        <p className="text-gray-600 dark:text-gray-400">{line.description}</p>
                    )}
                    {line.message && (
                        <p className="text-red-600 dark:text-red-400">{line.message}</p>
                    )}
                </div>

                {memberSuggestions.map((member) => (
                    <div
                        key={member.member_id}
                        className="space-y-2 rounded-lg bg-gray-50 px-3 py-2 dark:bg-gray-800"
                    >
                        <div className="flex flex-col gap-2 text-sm sm:flex-row sm:items-center sm:justify-between">
                            <span className="text-gray-800 dark:text-gray-100">
                                U tekstu uplate:{" "}
                                <MemberLink id={member.member_id} name={member.member_name} />
                            </span>
                            {line.member_id !== member.member_id && (
                                <ActionButton
                                    onClick={() => onLinkMember(member.member_id)}
                                    disabled={disabled}
                                >
                                    Poveži s članom
                                </ActionButton>
                            )}
                        </div>
                        {(member.missing_invoices || []).map((item) => (
                            <div
                                key={`${item.workshop_id}-${item.target_month}`}
                                className="flex flex-col gap-2 text-sm sm:flex-row sm:items-center sm:justify-between"
                            >
                                <span className="text-gray-700 dark:text-gray-300">
                                    U opisu je {item.label}
                                    {item.workshop_name ? ` · ${item.workshop_name}` : ""}
                                </span>
                                <ActionButton
                                    onClick={() =>
                                        onGenerateInvoice(member.member_id, item.workshop_id, item.target_month)
                                    }
                                    disabled={disabled}
                                >
                                    Generiraj račun za {item.label}
                                </ActionButton>
                            </div>
                        ))}
                        <GenerateForMonth
                            workshops={member.workshops || []}
                            suggestedMonth={member.suggested_month}
                            disabled={disabled}
                            onGenerate={(workshopId, targetMonth) =>
                                onGenerateInvoice(member.member_id, workshopId, targetMonth)
                            }
                        />
                        {member.invoices.length > 0 && (
                            <ul className="space-y-2">
                                {member.invoices.map((suggestion) => (
                                    <InvoiceSuggestion
                                        key={suggestion.invoice_id}
                                        suggestion={suggestion}
                                        disabled={disabled}
                                        onAttach={onAttach}
                                    />
                                ))}
                            </ul>
                        )}
                    </div>
                ))}

                {line.suggestions.length > 0 && (
                    <ul className="space-y-2">
                        {line.suggestions.map((suggestion) => (
                            <InvoiceSuggestion
                                key={suggestion.invoice_id}
                                suggestion={suggestion}
                                disabled={disabled}
                                onAttach={onAttach}
                            />
                        ))}
                    </ul>
                )}

                {!hasSuggestions && (
                    <p className="text-sm text-gray-500 dark:text-gray-400">
                        Nema prijedloga. Stavku odbacite ako uplata nije članarina.
                    </p>
                )}

                <NoteField note={line.note} disabled={disabled} onSave={onSaveNote} />

                <div>
                    <ActionButton onClick={onDismiss} disabled={disabled} tone="muted">
                        Odbaci
                    </ActionButton>
                </div>
            </div>
        </article>
    );
}

function GenerateForMonth({ workshops, suggestedMonth, disabled, onGenerate }) {
    const [open, setOpen] = useState(false);
    const [month, setMonth] = useState(suggestedMonth || "");
    const [workshopId, setWorkshopId] = useState(workshops[0]?.workshop_id ? String(workshops[0].workshop_id) : "");

    if (workshops.length === 0) {
        return null;
    }

    return (
        <div className="space-y-2">
            <ActionButton onClick={() => setOpen((current) => !current)} disabled={disabled} tone="muted">
                {open ? "Sakrij generiranje" : "Generiraj za mjesec"}
            </ActionButton>
            {open && (
                <div className="flex flex-col gap-2 sm:flex-row sm:items-end">
                    <label className="text-sm text-gray-600 dark:text-gray-400">
                        Mjesec
                        <MonthPicker
                            value={month}
                            onChange={setMonth}
                            disabled={disabled}
                            className="mt-1 block rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                        />
                    </label>
                    {workshops.length > 1 && (
                        <label className="text-sm text-gray-600 dark:text-gray-400">
                            Radionica
                            <select
                                value={workshopId}
                                onChange={(event) => setWorkshopId(event.target.value)}
                                className="mt-1 block rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                            >
                                {workshops.map((workshop) => (
                                    <option key={workshop.workshop_id} value={workshop.workshop_id}>
                                        {workshop.workshop_name}
                                    </option>
                                ))}
                            </select>
                        </label>
                    )}
                    <ActionButton
                        onClick={() => onGenerate(Number(workshopId), month)}
                        disabled={disabled || !month || !workshopId}
                    >
                        Generiraj račun
                    </ActionButton>
                </div>
            )}
        </div>
    );
}

function InvoiceSuggestion({ suggestion, disabled, onAttach }) {
    return (
        <li className="flex flex-col gap-2 rounded-lg bg-white px-3 py-2 text-sm ring-1 ring-gray-200 dark:bg-gray-900 dark:ring-gray-700 sm:flex-row sm:items-center sm:justify-between">
            <span className="text-gray-800 dark:text-gray-100">
                <MemberLink id={suggestion.member_id} name={suggestion.member_name} />
                {suggestion.reference ? ` · ${suggestion.reference}` : ""}
                {suggestion.due_date ? ` · ${suggestion.due_date}` : ""}
                {` · račun ${money(suggestion.amount_due)}`}
            </span>
            <ActionButton onClick={() => onAttach(suggestion.invoice_id)} disabled={disabled}>
                Poveži s računom
            </ActionButton>
        </li>
    );
}

function NoteField({ note, disabled, onSave }) {
    const [open, setOpen] = useState(false);
    const [value, setValue] = useState(note || "");
    const hasNote = Boolean(note);

    return (
        <div className="space-y-2">
            <ActionButton onClick={() => setOpen((current) => !current)} disabled={disabled} tone="muted">
                {open ? "Sakrij bilješku" : hasNote ? "Uredi bilješku" : "Dodaj bilješku"}
            </ActionButton>
            {hasNote && !open && (
                <p className="text-sm text-gray-600 dark:text-gray-400">{note}</p>
            )}
            {open && (
                <div className="flex flex-col gap-2 sm:flex-row sm:items-end">
                    <label className="block flex-1 text-sm text-gray-600 dark:text-gray-400">
                        Bilješka
                        <textarea
                            value={value}
                            onChange={(event) => setValue(event.target.value)}
                            rows={2}
                            maxLength={1000}
                            className="mt-1 w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-800 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-100"
                        />
                    </label>
                    <ActionButton
                        onClick={() => {
                            onSave(value);
                            setOpen(false);
                        }}
                        disabled={disabled}
                        tone="muted"
                    >
                        Spremi bilješku
                    </ActionButton>
                </div>
            )}
        </div>
    );
}

function MemberLink({ id, name }) {
    if (!id) {
        return name || "-";
    }

    return (
        <a
            href={route("members.show", id)}
            target="_blank"
            rel="noreferrer"
            className="font-medium text-brand-500 underline"
        >
            {name || "Otvori člana"}
        </a>
    );
}

function ActionButton({ children, onClick, disabled, tone = "primary" }) {
    const tones = {
        primary: "bg-brand-500 text-white hover:bg-brand-600",
        muted: "bg-white text-gray-700 ring-1 ring-inset ring-gray-300 hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:ring-gray-700",
    };

    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            className={`rounded-lg px-3 py-1.5 text-left text-xs font-medium disabled:opacity-50 ${tones[tone]}`}
        >
            {children}
        </button>
    );
}

function ResultBox({ label, value, color = "gray" }) {
    const classes = {
        gray: "bg-gray-50 dark:bg-gray-800",
        green: "bg-green-50 dark:bg-green-900/20",
        orange: "bg-orange-50 dark:bg-orange-900/20",
        red: "bg-red-50 dark:bg-red-900/20",
    };

    return (
        <div className={`p-4 rounded-lg ${classes[color] || classes.gray}`}>
            <p className="text-sm text-gray-600 dark:text-gray-400">{label}</p>
            <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">{value}</p>
        </div>
    );
}

function EmptyText({ children }) {
    return <p className="text-sm text-gray-500 dark:text-gray-400">{children}</p>;
}

function Th({ children }) {
    return (
        <th className="px-4 py-2 text-left text-xs font-medium uppercase text-gray-500 dark:text-gray-400">
            {children}
        </th>
    );
}

function Td({ children }) {
    return <td className="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">{children}</td>;
}

function money(value) {
    return `${Number(value || 0).toFixed(2)} EUR`;
}

function outcomeLabel(line) {
    if (line.resolution === "dismissed") {
        return "Odbaceno";
    }

    if (line.kind === "mismatch") {
        return "Usklađeno";
    }

    return "Povezano";
}

function firstError(errors) {
    const value = Object.values(errors || {})[0];
    if (!value) {
        return null;
    }

    return Array.isArray(value) ? value[0] : value;
}
