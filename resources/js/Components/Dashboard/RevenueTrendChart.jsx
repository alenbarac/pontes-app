import { useEffect, useState } from "react";
import Chart from "react-apexcharts";
import MonthSelect from "@/Components/Dashboard/MonthSelect";

function formatCurrency(value) {
  return new Intl.NumberFormat("hr-HR", {
    style: "currency",
    currency: "EUR",
    minimumFractionDigits: 0,
    maximumFractionDigits: 0,
  }).format(Number(value || 0));
}

export default function RevenueTrendChart({ months, monthKey, onMonthChange }) {
  const [isDark, setIsDark] = useState(false);
  const active = months.find((month) => month.key === monthKey) || months[months.length - 1] || {};

  useEffect(() => {
    const root = document.documentElement;
    const updateTheme = () => setIsDark(root.classList.contains("dark"));

    updateTheme();
    const observer = new MutationObserver(updateTheme);
    observer.observe(root, { attributes: true, attributeFilter: ["class"] });

    return () => observer.disconnect();
  }, []);

  const detailTextColor = isDark ? "#CBD5E1" : "#6B7280";
  const gridColor = isDark ? "#334155" : "#E5E7EB";
  const label = active.label || "";

  const options = {
    chart: {
      fontFamily: "Outfit, sans-serif",
      type: "bar",
      toolbar: { show: false },
      zoom: { enabled: false },
      foreColor: detailTextColor,
    },
    colors: isDark ? ["#7C8CFF", "#34D399"] : ["#465FFF", "#12B76A"],
    dataLabels: {
      enabled: true,
      formatter: (value) => formatCurrency(value),
      offsetY: -20,
      style: {
        fontSize: "12px",
        fontWeight: 600,
        colors: [detailTextColor],
      },
    },
    stroke: { width: 0 },
    plotOptions: {
      bar: {
        borderRadius: 6,
        columnWidth: "36%",
        dataLabels: { position: "top" },
      },
    },
    grid: {
      borderColor: gridColor,
      xaxis: { lines: { show: false } },
      yaxis: { lines: { show: true } },
      padding: { top: 24 },
    },
    xaxis: {
      categories: [label],
      axisBorder: { show: false },
      axisTicks: { show: false },
      labels: {
        style: { colors: detailTextColor },
      },
    },
    yaxis: {
      min: 0,
      labels: {
        formatter: (value) => formatCurrency(value),
        style: { colors: [detailTextColor] },
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
      labels: { colors: detailTextColor },
    },
  };

  const series = [
    { name: "Očekivano", data: [Number(active.expected_amount || 0)] },
    { name: "Naplaćeno", data: [Number(active.collected_amount || 0)] },
  ];

  return (
    <div className="h-full rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03]">
      <div className="mb-4 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
        <h3 className="text-lg font-semibold text-gray-800 dark:text-white">
          Očekivano vs. naplaćeno
        </h3>

        {months.length > 0 && (
          <MonthSelect
            id="trend-month"
            months={months}
            value={active.key || ""}
            onChange={onMonthChange}
          />
        )}
      </div>

      <div className="custom-scrollbar max-w-full overflow-x-auto">
        <Chart key={active.key || "month"} options={options} series={series} type="bar" height={320} />
      </div>
    </div>
  );
}
