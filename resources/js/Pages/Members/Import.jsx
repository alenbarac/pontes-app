import React, { useState } from "react";
import { Head, Link, router } from "@inertiajs/react";
import { useDropzone } from "react-dropzone";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import Breadcrumb from "@/Components/Breadcrumb";
import ComponentCard from "@/Components/common/ComponentCard";
import Button from "@/Components/ui/button/Button";
import toast from "react-hot-toast";

export default function Import({ importResult }) {
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
            "application/vnd.openxmlformats-officedocument.spreadsheetml.sheet": [".xlsx"],
            "application/vnd.ms-excel": [".xls"],
            "text/csv": [".csv"],
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

        router.post(
            route("members.import"),
            formData,
            {
                forceFormData: true,
                preserveState: true,
                preserveScroll: true,
                onSuccess: (page) => {
                    setUploading(false);
                    setFile(null);
                    if (page.props.importResult) {
                        const { created_count, failed_count, errors } = page.props.importResult;
                        if (created_count > 0) {
                            toast.success(
                                `Uspješno uvezeno ${created_count} članova.`
                            );
                        }
                        if (failed_count > 0) {
                            toast.error(
                                `${failed_count} zapisa nije moglo biti uvezeno.`
                            );
                        }
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
            }
        );
    };

    const downloadTemplate = () => {
        window.location.href = route("members.import.template");
    };

    return (
        <AuthenticatedLayout>
            <Head title="Import" />
            <Breadcrumb pageName="Uvoz članova" />
            <div className="space-y-6">
                <ComponentCard title="Uvoz članova">
                    <div className="space-y-4">
                        <p className="text-sm text-gray-600 dark:text-gray-400">
                            Učitajte Excel ili CSV datoteku s članovima. Datoteka
                            mora imati sljedeće kolone: GRUPA, IME I PREZIME,
                            E-MAIL, ČLANARINA.
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
                                    <div className="mb-[22px] flex justify-center">
                                        <div className="flex h-[68px] w-[68px] items-center justify-center rounded-full bg-gray-200 text-gray-700 dark:bg-gray-800 dark:text-gray-400">
                                            <svg
                                                className="fill-current"
                                                width="29"
                                                height="28"
                                                viewBox="0 0 29 28"
                                                xmlns="http://www.w3.org/2000/svg"
                                            >
                                                <path
                                                    fillRule="evenodd"
                                                    clipRule="evenodd"
                                                    d="M14.5019 3.91699C14.2852 3.91699 14.0899 4.00891 13.953 4.15589L8.57363 9.53186C8.28065 9.82466 8.2805 10.2995 8.5733 10.5925C8.8661 10.8855 9.34097 10.8857 9.63396 10.5929L13.7519 6.47752V18.667C13.7519 19.0812 14.0877 19.417 14.5019 19.417C14.9161 19.417 15.2519 19.0812 15.2519 18.667V6.48234L19.3653 10.5929C19.6583 10.8857 20.1332 10.8855 20.426 10.5925C20.7188 10.2995 20.7186 9.82463 20.4256 9.53184L15.0838 4.19378C14.9463 4.02488 14.7367 3.91699 14.5019 3.91699ZM5.91626 18.667C5.91626 18.2528 5.58047 17.917 5.16626 17.917C4.75205 17.917 4.41626 18.2528 4.41626 18.667V21.8337C4.41626 23.0763 5.42362 24.0837 6.66626 24.0837H22.3339C23.5766 24.0837 24.5839 23.0763 24.5839 21.8337V18.667C24.5839 18.2528 24.2482 17.917 23.8339 17.917C23.4197 17.917 23.0839 18.2528 23.0839 18.667V21.8337C23.0839 22.2479 22.7482 22.5837 22.3339 22.5837H6.66626C6.25205 22.5837 5.91626 22.2479 5.91626 21.8337V18.667Z"
                                                />
                                            </svg>
                                        </div>
                                    </div>

                                    <h4 className="mb-3 font-semibold text-gray-800 text-theme-xl dark:text-white/90">
                                        {isDragActive
                                            ? "Spustite datoteku ovdje"
                                            : "Povucite i spustite datoteku ovdje"}
                                    </h4>

                                    <span className="text-center mb-5 block w-full max-w-[290px] text-sm text-gray-700 dark:text-gray-400">
                                        Povucite i spustite Excel ili CSV datoteku
                                        ovdje ili kliknite za pregled
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
                                {uploading ? "Učitavanje..." : "Uvezi članove"}
                            </Button>
                            <Button
                                onClick={downloadTemplate}
                                variant="outline"
                            >
                                Preuzmi predložak
                            </Button>
                        </div>
                    </div>
                </ComponentCard>

                {importResult && (
                    <ComponentCard title="Rezultati uvoza">
                        <div className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div className="p-4 bg-green-50 dark:bg-green-900/20 rounded-lg">
                                    <p className="text-sm text-gray-600 dark:text-gray-400">
                                        Uspješno uvezeno
                                    </p>
                                    <p className="text-2xl font-bold text-green-600 dark:text-green-400">
                                        {importResult.created_count || 0}
                                    </p>
                                </div>
                                <div className="p-4 bg-red-50 dark:bg-red-900/20 rounded-lg">
                                    <p className="text-sm text-gray-600 dark:text-gray-400">
                                        Neuspješno
                                    </p>
                                    <p className="text-2xl font-bold text-red-600 dark:text-red-400">
                                        {importResult.failed_count || 0}
                                    </p>
                                </div>
                            </div>

                            {importResult.errors &&
                                importResult.errors.length > 0 && (
                                    <div className="mt-4">
                                        <h4 className="font-medium text-gray-800 dark:text-white mb-2">
                                            Greške:
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
                                                    </tr>
                                                </thead>
                                                <tbody className="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                                                    {importResult.errors.map(
                                                        (error, index) => (
                                                            <tr key={index}>
                                                                <td className="px-4 py-2 text-sm text-gray-900 dark:text-gray-100">
                                                                    {error.row}
                                                                </td>
                                                                <td className="px-4 py-2 text-sm text-red-600 dark:text-red-400">
                                                                    {error.message}
                                                                </td>
                                                            </tr>
                                                        )
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
