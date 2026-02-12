import React, { useState, useRef } from "react";
import { Modal } from "@/Components/ui/modal";
import Button from "@/Components/ui/button/Button";
import toast from "react-hot-toast";
import axios from "axios";

export default function BulkSlipsDownloadModal({
    isOpen,
    onClose,
    selectedMembers,
    members,
    currentGroup,
    onSuccess,
}) {
    const [selectedMonth, setSelectedMonth] = useState(() => {
        // Default to current month in YYYY-MM format
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        return `${year}-${month}`;
    });
    const [processing, setProcessing] = useState(false);
    const formRef = useRef(null);

    const selectedMembersData = members.filter((m) =>
        selectedMembers.includes(m.id)
    );

    const handleSubmit = async (e) => {
        e.preventDefault();

        if (!selectedMonth) {
            toast.error("Molimo odaberite mjesec.");
            return;
        }

        // Validate month format (YYYY-MM)
        if (!/^\d{4}-\d{2}$/.test(selectedMonth)) {
            toast.error("Nevažeći format mjeseca. Koristite YYYY-MM.");
            return;
        }

        setProcessing(true);

        try {
            // Use axios for file download (already configured with CSRF token)
            const response = await axios.post(
                route("member-groups.bulk-download-slips", currentGroup.id),
                {
                    member_ids: selectedMembers,
                    month: selectedMonth,
                },
                {
                    responseType: 'blob', // Important for file downloads
                }
            );

            // Check if response is actually a ZIP file (not an error JSON)
            const contentType = response.headers['content-type'] || '';
            const isZipFile = contentType.includes('application/zip') || 
                             contentType.includes('application/x-zip-compressed') ||
                             contentType.includes('application/octet-stream');

            // If not a ZIP file, it's likely an error response
            if (!isZipFile) {
                // Try to parse as JSON error
                try {
                    const text = await response.data.text();
                    const json = JSON.parse(text);
                    
                    // Extract all error messages
                    const errorMessages = [];
                    if (json.message) {
                        errorMessages.push(json.message);
                    }
                    if (json.errors) {
                        // Flatten all error messages from the errors object
                        const flatErrors = Object.values(json.errors).flat();
                        errorMessages.push(...flatErrors);
                    }
                    
                    // Display all error messages
                    const errorMessage = errorMessages.length > 0 
                        ? errorMessages.join(' ') 
                        : "Došlo je do pogreške prilikom preuzimanja uplatnica.";
                    
                    toast.error(errorMessage);
                    setProcessing(false);
                    return;
                } catch (e) {
                    // If parsing fails, it's not JSON either - something went wrong
                    toast.error("Neočekivani odgovor od servera. Molimo pokušajte ponovno.");
                    setProcessing(false);
                    return;
                }
            }

            // Create blob and trigger download
            const blob = new Blob([response.data], { 
                type: 'application/zip'
            });
            const url = window.URL.createObjectURL(blob);
            
            // Get filename from Content-Disposition header or use default
            const contentDisposition = response.headers['content-disposition'];
            let filename = `uplatnice-${selectedMonth}.zip`;
            if (contentDisposition) {
                // Handle both quoted and unquoted filenames, including URL encoding
                const filenameMatch = contentDisposition.match(/filename[^;=\n]*=((['"]).*?\2|[^;\n]*)/);
                if (filenameMatch && filenameMatch[1]) {
                    filename = filenameMatch[1]
                        .replace(/['"]/g, '') // Remove quotes
                        .replace(/^UTF-8''/, '') // Remove UTF-8 encoding prefix if present
                        .replace(/\+/g, ' '); // Replace + with space
                    // Decode URL encoding
                    try {
                        filename = decodeURIComponent(filename);
                    } catch (e) {
                        // If decoding fails, use as-is
                    }
                }
            }

            // Create temporary link and trigger download
            const link = document.createElement('a');
            link.href = url;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            // Clean up blob URL
            setTimeout(() => window.URL.revokeObjectURL(url), 100);

            toast.success("Uplatnice su uspješno preuzete.");
            setProcessing(false);
            onSuccess();
        } catch (error) {
            console.error('Error downloading slips:', error);
            
            // Try to extract error message from response
            let errorMessage = "Došlo je do pogreške prilikom preuzimanja uplatnica.";
            
            if (error.response) {
                // If it's a validation error, try to parse JSON from blob
                if (error.response.data instanceof Blob) {
                    try {
                        const text = await error.response.data.text();
                        const json = JSON.parse(text);
                        
                        // Extract all error messages
                        const errorMessages = [];
                        if (json.message) {
                            errorMessages.push(json.message);
                        }
                        if (json.errors) {
                            // Flatten all error messages from the errors object
                            const flatErrors = Object.values(json.errors).flat();
                            errorMessages.push(...flatErrors);
                        }
                        
                        errorMessage = errorMessages.length > 0 
                            ? errorMessages.join(' ') 
                            : errorMessage;
                    } catch (e) {
                        // If parsing fails, check status code
                        if (error.response.status === 404) {
                            errorMessage = "Nema računa za odabrane članove u tom mjesecu.";
                        } else if (error.response.status === 422) {
                            errorMessage = "Nevažeći podaci. Molimo provjerite unos.";
                        } else if (error.response.status === 500) {
                            errorMessage = "Greška na serveru. Molimo pokušajte ponovno.";
                        }
                    }
                } else if (error.response.data) {
                    // Handle regular JSON error response
                    const errorMessages = [];
                    if (error.response.data.message) {
                        errorMessages.push(error.response.data.message);
                    }
                    if (error.response.data.errors) {
                        // Flatten all error messages from the errors object
                        const flatErrors = Object.values(error.response.data.errors).flat();
                        errorMessages.push(...flatErrors);
                    }
                    if (error.response.data.error) {
                        errorMessages.push(error.response.data.error);
                    }
                    
                    errorMessage = errorMessages.length > 0 
                        ? errorMessages.join(' ') 
                        : errorMessage;
                } else if (error.response.status === 404) {
                    errorMessage = "Nema računa za odabrane članove u tom mjesecu.";
                } else if (error.response.status === 422) {
                    errorMessage = "Nevažeći podaci. Molimo provjerite unos.";
                } else if (error.response.status === 500) {
                    errorMessage = "Greška na serveru. Molimo pokušajte ponovno.";
                }
            } else if (error.request) {
                errorMessage = "Nema odgovora od servera. Provjerite internetsku vezu.";
            }
            
            toast.error(errorMessage);
            setProcessing(false);
        }
    };

    return (
        <Modal
            isOpen={isOpen}
            onClose={onClose}
            className="max-w-[600px] m-4"
        >
            <div className="no-scrollbar relative w-full max-w-[600px] overflow-y-auto rounded-3xl bg-white p-4 dark:bg-gray-900 lg:p-11">
                <h5 className="text-xl mb-5 font-semibold text-gray-800 dark:text-white/90">
                    Preuzmi uplatnice
                </h5>

                <div className="mb-6">
                    <p className="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        Odabrano je <strong>{selectedMembers.length}</strong>{" "}
                        članova za preuzimanje uplatnica:
                    </p>
                    <div className="max-h-32 overflow-y-auto border border-gray-200 dark:border-gray-700 rounded-lg p-3 bg-gray-50 dark:bg-gray-800">
                        <ul className="space-y-1">
                            {selectedMembersData.map((member) => (
                                <li
                                    key={member.id}
                                    className="text-sm text-gray-700 dark:text-gray-300"
                                >
                                    {member.first_name} {member.last_name}
                                </li>
                            ))}
                        </ul>
                    </div>
                </div>

                <form ref={formRef} onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <label
                            htmlFor="month"
                            className="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2"
                        >
                            Mjesec
                        </label>
                        <input
                            type="month"
                            id="month"
                            value={selectedMonth}
                            onChange={(e) => setSelectedMonth(e.target.value)}
                            className="w-full rounded-lg border border-gray-300 px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-brand-500 dark:border-gray-700 dark:bg-gray-800 dark:text-white"
                            required
                        />
                        <p className="mt-2 text-xs text-gray-500 dark:text-gray-400">
                            Odaberite mjesec za koji želite preuzeti uplatnice.
                        </p>
                    </div>

                    <div className="flex items-center justify-end gap-3 mt-6">
                        <Button
                            type="button"
                            onClick={onClose}
                            variant="outline"
                            size="sm"
                            disabled={processing}
                        >
                            Odustani
                        </Button>
                        <Button
                            type="submit"
                            variant="primary"
                            size="sm"
                            disabled={processing || !selectedMonth}
                        >
                            {processing ? "Preuzimanje..." : "Preuzmi"}
                        </Button>
                    </div>
                </form>
            </div>
        </Modal>
    );
}
