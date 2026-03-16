import React, { useState } from "react";
import { Head, router } from "@inertiajs/react";
import { useDropzone } from "react-dropzone";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import Breadcrumb from "@/Components/Breadcrumb";
import ComponentCard from "@/Components/common/ComponentCard";
import Button from "@/Components/ui/button/Button";
import toast from "react-hot-toast";

export default function ImportMBanking({ importResult }) {
    const [uploading, setUploading] = useState(false);
    const [file, setFile] = useState(null);
    const [showOnlyDifferences, setShowOnlyDifferences] = useState(false);

    const onDrop = (acceptedFiles) => {
        if (acceptedFiles.length > 0) {
            setFile(acceptedFiles[0]);
        }
    };

    const { getRootProps, getInputProps, isDragActive } = useDropzone({
        onDrop,
        accept: {
            "text/csv": [".csv"],
            "text/plain": [".txt"],
        },
        multiple: false,
    });

    const handleUpload = () => {
        if (!file) {
            toast.error("Molimo odaberite datoteku za učitavanje.");
            return;
        }

        setUploading(true);
        const formData = new FormData();
        formData.append("file", file);

        router.post(route("invoices.importMbanking.store"), formData, {
            forceFormData: true,
            preserveState: true,
            preserveScroll: true,
            onSuccess: (page) => {
                setUploading(false);
                setFile(null);

                const result = page.props.importResult;
                if (!result) {
                    return;
                }

                if (result.paid_count > 0) {
                    toast.success(`Plaćeno označeno: ${result.paid_count} računa.`);
                }
                if (result.mismatch_count > 0) {
                    toast(`Neusklađeno označeno: ${result.mismatch_count} računa.`, {
                        icon: "⚠️",
                    });
                }
                if (result.failed_count > 0) {
                    toast.error(`Neobrađenih stavki: ${result.failed_count}.`);
                }
            },
            onError: (errors) => {
                setUploading(false);
                if (errors.file) {
                    toast.error(errors.file);
                } else {
                    toast.error("Došlo je do greške prilikom uvoza.");
                }
            },
        });
    };

    const allMatches = importResult?.matches || [];
    const allErrors = importResult?.errors || [];
    const visibleMatches = showOnlyDifferences
        ? allMatches.filter((match) => match.status === "Neusklađeno")
        : allMatches;
    const visibleErrors = allErrors;

    return (
        <AuthenticatedLayout>
            <Head title="Uvoz - mBanking" />
            <Breadcrumb pageName="Uvoz - mBanking" />

            <div className="space-y-6">
                <ComponentCard title="Uvoz bankovnog izvoda (mBanking)">
                    <div className="space-y-4">
                        <p className="text-sm text-gray-600 dark:text-gray-400">
                            Učitajte CSV datoteku izvoda. Obradit će se samo stavke
                            s uplatom (`Uplate`), povezati s računima i ažurirati
                            status računa.
                        </p>

                        <div
                            {...getRootProps()}
                            className={`transition border border-gray-300 border-dashed cursor-pointer dark:hover:border-brand-500 dark:border-gray-700 rounded-xl hover:border-brand-500 ${
                                isDragActive
                                    ? "border-brand-500 bg-gray-100 dark:bg-gray-800"
                                    : "border-gray-300 bg-gray-50 dark:border-gray-700 dark:bg-gray-900"
                            }`}
                        >
                            <div
                                className={`dropzone rounded-xl border-dashed border-gray-300 p-7 lg:p-10 ${
                                    isDragActive
                                        ? "border-brand-500 bg-gray-100 dark:bg-gray-800"
                                        : "border-gray-300 bg-gray-50 dark:border-gray-700 dark:bg-gray-900"
                                }`}
                            >
                                <input {...getInputProps()} />
                                <div className="dz-message flex flex-col items-center m-0!">
                                    <h4 className="mb-3 font-semibold text-gray-800 text-theme-xl dark:text-white/90">
                                        {isDragActive
                                            ? "Spustite CSV datoteku ovdje"
                                            : "Povucite i spustite CSV datoteku ovdje"}
                                    </h4>
                                    <span className="text-center mb-5 block w-full max-w-[320px] text-sm text-gray-700 dark:text-gray-400">
                                        Kliknite ili povucite CSV izvod iz mBanking
                                        aplikacije.
                                    </span>
                                    <span className="font-medium underline text-theme-sm text-brand-500">
                                        Odaberi datoteku
                                    </span>
                                </div>
                            </div>
                        </div>

                        {file && (
                            <div className="mt-4 p-4 bg-gray-50 dark:bg-gray-800 rounded-lg">
                                <p className="text-sm text-gray-700 dark:text-gray-300">
                                    Odabrana datoteka:{" "}
                                    <span className="font-medium">{file.name}</span>
                                </p>
                            </div>
                        )}

                        <div className="flex gap-4">
                            <Button
                                onClick={handleUpload}
                                disabled={!file || uploading}
                                variant="primary"
                            >
                                {uploading ? "Učitavanje..." : "Uvezi izvod"}
                            </Button>
                        </div>
                    </div>
                </ComponentCard>

                {importResult && (
                    <ComponentCard title="Rezultati uvoza">
                        <div className="space-y-4">
                            <div className="flex items-center justify-between gap-3">
                                <p className="text-sm text-gray-600 dark:text-gray-400">
                                    {showOnlyDifferences
                                        ? "Prikaz: samo razlike (Neusklađeno + greške)"
                                        : "Prikaz: sve obrađene stavke"}
                                </p>
                                <label className="inline-flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300 cursor-pointer">
                                    <input
                                        type="checkbox"
                                        checked={showOnlyDifferences}
                                        onChange={(event) =>
                                            setShowOnlyDifferences(
                                                event.target.checked,
                                            )
                                        }
                                        className="accent-brand-500 size-4"
                                    />
                                    Prikaži samo razlike
                                </label>
                            </div>

                            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <ResultBox
                                    label="Ukupno stavki"
                                    value={importResult.total_rows || 0}
                                />
                                <ResultBox
                                    label="Ulazne uplate"
                                    value={importResult.incoming_rows || 0}
                                />
                                <ResultBox
                                    label="Označeno plaćeno"
                                    value={importResult.paid_count || 0}
                                    color="green"
                                />
                                <ResultBox
                                    label="Neusklađeno"
                                    value={importResult.mismatch_count || 0}
                                    color="orange"
                                />
                            </div>

                            <div className="grid grid-cols-2 md:grid-cols-4 gap-4">
                                <ResultBox
                                    label="Neobrađeno"
                                    value={importResult.failed_count || 0}
                                    color="red"
                                />
                                <ResultBox
                                    label="Nije pronađen račun"
                                    value={importResult.unresolved_count || 0}
                                />
                                <ResultBox
                                    label="Više mogućih računa"
                                    value={importResult.ambiguous_count || 0}
                                />
                                <ResultBox
                                    label="Preskočeno"
                                    value={importResult.skipped_count || 0}
                                />
                            </div>

                            {visibleMatches.length > 0 && (
                                    <div className="mt-4">
                                        <h4 className="font-medium text-gray-800 dark:text-white mb-2">
                                            {showOnlyDifferences
                                                ? "Neusklađene stavke:"
                                                : "Uspješno obrađene stavke:"}
                                        </h4>
                                        <div className="max-h-72 overflow-y-auto">
                                            <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                                <thead className="bg-gray-50 dark:bg-gray-800">
                                                    <tr>
                                                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                                            Red
                                                        </th>
                                                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                                            Platitelj
                                                        </th>
                                                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                                            Član
                                                        </th>
                                                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                                            Referenca
                                                        </th>
                                                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                                            Iznos
                                                        </th>
                                                        <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                                            Status
                                                        </th>
                                                    </tr>
                                                </thead>
                                                <tbody className="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                                                    {visibleMatches.map(
                                                        (match, index) => (
                                                            <tr key={index}>
                                                                <td className="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                                                                    {match.row}
                                                                </td>
                                                                <td className="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                                                                    {match.payer || "-"}
                                                                </td>
                                                                <td className="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                                                                    {match.member || "-"}
                                                                </td>
                                                                <td className="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                                                                    {match.reference}
                                                                </td>
                                                                <td className="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                                                                    {Number(
                                                                        match.amount_paid || 0,
                                                                    ).toFixed(
                                                                        2,
                                                                    )}{" "}
                                                                    /{" "}
                                                                    {Number(
                                                                        match.amount_due || 0,
                                                                    ).toFixed(
                                                                        2,
                                                                    )}{" "}
                                                                    EUR
                                                                </td>
                                                                <td className="px-4 py-2 text-sm">
                                                                    <span
                                                                        className={`inline-block px-2 py-1 text-xs rounded ${
                                                                            match.status ===
                                                                            "Plaćeno"
                                                                                ? "bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400"
                                                                                : "bg-orange-100 text-orange-800 dark:bg-orange-900/30 dark:text-orange-400"
                                                                        }`}
                                                                    >
                                                                        {match.status}
                                                                    </span>
                                                                </td>
                                                            </tr>
                                                        ),
                                                    )}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                )}

                            {visibleErrors.length > 0 && (
                                <div className="mt-4">
                                    <h4 className="font-medium text-gray-800 dark:text-white mb-2">
                                        Problemi po retcima:
                                    </h4>
                                    <div className="max-h-64 overflow-y-auto">
                                        <table className="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                            <thead className="bg-gray-50 dark:bg-gray-800">
                                                <tr>
                                                    <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                                        Red
                                                    </th>
                                                    <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                                        Greška
                                                    </th>
                                                    <th className="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase">
                                                        Referenca
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody className="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                                                {visibleErrors.map(
                                                    (error, index) => (
                                                        <tr key={index}>
                                                            <td className="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                                                                {error.row}
                                                            </td>
                                                            <td className="px-4 py-2 text-sm text-red-600 dark:text-red-400">
                                                                {error.message}
                                                            </td>
                                                            <td className="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                                                                {error.reference || "-"}
                                                            </td>
                                                        </tr>
                                                    ),
                                                )}
                                            </tbody>
                                        </table>
                                    </div>
                                </div>
                            )}
                        </div>
                    </ComponentCard>
                )}
            </div>
        </AuthenticatedLayout>
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
            <p className="text-2xl font-bold text-gray-900 dark:text-gray-100">
                {value}
            </p>
        </div>
    );
}
