export default function MonthSelect({ months, value, onChange, id }) {
  return (
    <select
      id={id}
      value={value}
      onChange={(event) => onChange(event.target.value)}
      className="h-8 cursor-pointer rounded-lg border border-gray-200 bg-white px-3 pr-8 text-xs font-medium text-gray-900 shadow-sm focus:border-brand-500 focus:outline-none focus:ring-2 focus:ring-brand-500/20 dark:border-gray-700 dark:bg-gray-900 dark:text-white"
    >
      {months.map((month) => (
        <option key={month.key} value={month.key}>
          {month.label}
        </option>
      ))}
    </select>
  );
}
