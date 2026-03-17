import { useEffect, useMemo, useState } from "react";
import Chart from "react-apexcharts";

function formatCurrency(value) {
  return new Intl.NumberFormat("hr-HR", {
    style: "currency",
    currency: "EUR",
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(Number(value || 0));
}

export default function RevenueTrendChart({ trend }) {
  const [selectedRange, setSelectedRange] = useState("12m");
  const [isDark, setIsDark] = useState(false);

  const labels = trend?.labels || [];
  const generated = trend?.generated || [];
  const paid = trend?.paid || [];

  useEffect(() => {
    const root = document.documentElement;
    const updateTheme = () => setIsDark(root.classList.contains("dark"));

    updateTheme();
    const observer = new MutationObserver(updateTheme);
    observer.observe(root, { attributes: true, attributeFilter: ["class"] });

    return () => observer.disconnect();
  }, []);

  const visible = useMemo(() => {
    if (selectedRange === "1m") {
      return {
        labels: labels.slice(-1),
        generated: generated.slice(-1),
        paid: paid.slice(-1),
      };
    }

    if (selectedRange === "4m") {
      return {
        labels: labels.slice(-4),
        generated: generated.slice(-4),
        paid: paid.slice(-4),
      };
    }

    return { labels, generated, paid };
  }, [selectedRange, labels, generated, paid]);

  const detailTextColor = isDark ? "#CBD5E1" : "#6B7280";
  const gridColor = isDark ? "#334155" : "#E5E7EB";

  const options = {
    chart: {
      fontFamily: "Outfit, sans-serif",
      type: "area",
      toolbar: { show: false },
      zoom: { enabled: false },
      foreColor: detailTextColor,
    },
    colors: isDark ? ["#7C8CFF", "#34D399"] : ["#465FFF", "#12B76A"],
    dataLabels: { enabled: false },
    stroke: {
      curve: "smooth",
      width: 2,
    },
    fill: {
      type: "gradient",
      gradient: {
        opacityFrom: 0.35,
        opacityTo: 0.04,
      },
    },
    grid: {
      borderColor: gridColor,
      xaxis: { lines: { show: false } },
      yaxis: { lines: { show: true } },
    },
    xaxis: {
      categories: visible.labels,
      axisBorder: { show: false },
      axisTicks: { show: false },
      labels: {
        style: {
          colors: detailTextColor,
        },
      },
    },
    yaxis: {
      labels: {
        formatter: (value) => formatCurrency(value),
        style: {
          colors: [detailTextColor],
        },
      },
    },
    tooltip: {
      theme: isDark ? "dark" : "light",
      y: {
        formatter: (value) => formatCurrency(value),
      },
    },
    legend: {
      show: true,
      position: "top",
      horizontalAlign: "left",
      labels: {
        colors: detailTextColor,
      },
    },
  };

  const series = [
    { name: "Generirani", data: visible.generated },
    { name: "Plaćeni", data: visible.paid },
  ];

  return (
    <div className="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
      <div className="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <h3 className="text-lg font-semibold text-gray-800 dark:text-white">
          Trend računa i naplate (12 mjeseci)
        </h3>

        <div className="inline-flex w-fit items-center gap-1 rounded-lg bg-gray-100 p-1 dark:bg-gray-900">
          <button
            onClick={() => setSelectedRange("1m")}
            className={`rounded-md px-3 py-1.5 text-xs font-medium transition-colors ${
              selectedRange === "1m"
                ? "bg-white text-gray-900 shadow-sm dark:bg-gray-800 dark:text-white"
                : "text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
            }`}
          >
            Trenutni mjesec
          </button>
          <button
            onClick={() => setSelectedRange("4m")}
            className={`rounded-md px-3 py-1.5 text-xs font-medium transition-colors ${
              selectedRange === "4m"
                ? "bg-white text-gray-900 shadow-sm dark:bg-gray-800 dark:text-white"
                : "text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
            }`}
          >
            Kvartal
          </button>
          <button
            onClick={() => setSelectedRange("12m")}
            className={`rounded-md px-3 py-1.5 text-xs font-medium transition-colors ${
              selectedRange === "12m"
                ? "bg-white text-gray-900 shadow-sm dark:bg-gray-800 dark:text-white"
                : "text-gray-600 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white"
            }`}
          >
            12 mjeseci
          </button>
        </div>
      </div>

      <div className="custom-scrollbar max-w-full overflow-x-auto">
        <Chart options={options} series={series} type="area" height={320} />
      </div>
    </div>
  );
}
