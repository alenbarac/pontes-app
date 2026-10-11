import { Head, Link, router } from "@inertiajs/react";
import { useState } from "react";
import { useDropzone } from "react-dropzone";
import toast from "react-hot-toast";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import Breadcrumb from "@/Components/Breadcrumb";
import ComponentCard from "@/Components/common/ComponentCard";
import Button from "@/Components/ui/button/Button";

export default function ImportMBanking({ latestImport }) {
    const [uploading, setUploading] = useState(false);
    const [file, setFile] = useState(null);

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
            onFinish: () => setUploading(false),
            onError: (errors) => {
                toast.error(firstError(errors) || "Došlo je do greške prilikom uvoza.");
            },
        });
    };

    const openIssues = latestImport
        ? latestImport.open_mismatch_count +
          latestImport.open_unresolved_count +
          latestImport.open_ambiguous_count
        : 0;

    return (
        <AuthenticatedLayout>
            <Head title="Uvoz - mBanking" />
            <Breadcrumb pageName="Uvoz - mBanking" />

            <div className="space-y-6">
                {latestImport && (
                    <ComponentCard title="Zadnji uvoz">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p className="text-sm text-gray-800 dark:text-gray-100">
                                    <span className="font-medium">{latestImport.filename}</span>
                                    <span className="text-gray-500 dark:text-gray-400">
                                        {" "}
                                        · {latestImport.imported_at}
                                    </span>
                                </p>
                                <p className="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    {openIssues > 0
                                        ? `Otvoreno za provjeru: ${latestImport.open_mismatch_count} neusklađenih, ${latestImport.open_unresolved_count} bez računa, ${latestImport.open_ambiguous_count} s više računa.`
                                        : "Nema otvorenih stavki za provjeru."}
                                </p>
                            </div>
                            <Link
                                href={route("invoices.importMbanking.show", latestImport.id)}
                                className="inline-flex items-center justify-center rounded-lg bg-brand-500 px-4 py-2.5 text-sm font-medium text-white hover:bg-brand-600"
                            >
                                Otvori pregled
                            </Link>
                        </div>
                    </ComponentCard>
                )}

                <ComponentCard title="Uvoz bankovnog izvoda (mBanking)">
                    <div className="space-y-4">
                        <p className="text-sm text-gray-600 dark:text-gray-400">
                            Učitajte CSV datoteku izvoda. Obradit će se samo stavke
                            s uplatom (Uplate), povezati s računima i ažurirati
                            status računa. Pregled ostaje spremljen pa ga možete
                            osvježiti i otvoriti u drugom tabu.
                        </p>

                        <div
                            {...getRootProps()}
                            className={`transition border border-gray-300 border-dashed cursor-pointer dark:hover:border-brand-500 dark:border-gray-700 rounded-xl hover:border-brand-500 ${
                                isDragActive
                                    ? "border-brand-500 bg-gray-100 dark:bg-gray-800"
                                    : "border-gray-300 bg-gray-50 dark:border-gray-700 dark:bg-gray-900"
                            }`}
                        >
                            <div className="dropzone rounded-xl border-dashed border-gray-300 p-7 lg:p-10">
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
            </div>
        </AuthenticatedLayout>
    );
}

function firstError(errors) {
    const value = Object.values(errors || {})[0];
    if (!value) {
        return null;
    }

    return Array.isArray(value) ? value[0] : value;
}
