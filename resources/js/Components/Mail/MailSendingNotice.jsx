import { usePage } from "@inertiajs/react";

export default function MailSendingNotice({ always = false, className = "" }) {
    const sending = usePage().props.mailSending;

    if (!sending || (!always && !sending.testSending)) {
        return null;
    }

    const tone = !sending.testSending
        ? "border-gray-200 bg-gray-50 text-gray-700 dark:border-gray-700 dark:bg-white/[0.03] dark:text-gray-300"
        : sending.allowlistConfigured
          ? "border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200"
          : "border-red-200 bg-red-50 text-red-800 dark:border-red-500/30 dark:bg-red-500/10 dark:text-red-200";

    const text = !sending.testSending
        ? "Stvarno slanje. Poruke idu na e-mail za račune."
        : sending.allowlistConfigured
          ? "Testno slanje je uključeno. Poruke idu samo na dopuštene adrese."
          : "Testno slanje je uključeno, ali popis adresa je prazan. Slanje je zaustavljeno.";

    return (
        <div className={`rounded-lg border px-3 py-2 text-sm ${tone} ${className}`.trim()}>
            {text}
        </div>
    );
}
