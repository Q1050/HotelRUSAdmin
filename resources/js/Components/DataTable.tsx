
import { useState } from "react";
import { cn } from "@/lib/utils";

interface Column<T> {
  header: string;
  accessor: keyof T;
  cell?: (item: T) => React.ReactNode;
}

interface DataTableProps<T> {
  data: T[];
  columns: Column<T>[];
  onRowClick?: (item: T) => void;
  className?: string;
}

export function DataTable<T>({ data, columns, onRowClick, className }: DataTableProps<T>) {
  const [sortField, setSortField] = useState<keyof T | null>(null);
  const [sortDirection, setSortDirection] = useState<"asc" | "desc">("asc");

  const handleSort = (field: keyof T) => {
    if (sortField === field) {
      setSortDirection(sortDirection === "asc" ? "desc" : "asc");
    } else {
      setSortField(field);
      setSortDirection("asc");
    }
  };

  const sortedData = [...data].sort((a, b) => {
    if (!sortField) return 0;
    
    const aValue = a[sortField];
    const bValue = b[sortField];
    
    if (aValue === bValue) return 0;
    
    const result = aValue < bValue ? -1 : 1;
    return sortDirection === "asc" ? result : -result;
  });

  return (
    <div className={cn("overflow-x-auto", className)}>
      <table className="w-full border-collapse">
        <thead>
          <tr className="bg-gray-50 border-b">
            {columns.map((column, index) => (
              <th
                key={index}
                className="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider cursor-pointer hover:bg-gray-100"
                onClick={() => handleSort(column.accessor)}
              >
                <div className="flex items-center space-x-1">
                  <span>{column.header}</span>
                  {sortField === column.accessor && (
                    <span>
                      {sortDirection === "asc" ? " ▲" : " ▼"}
                    </span>
                  )}
                </div>
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {sortedData.map((item, rowIndex) => (
            <tr
              key={rowIndex}
              className={cn(
                "border-b hover:bg-gray-50 transition-colors",
                onRowClick && "cursor-pointer"
              )}
              onClick={() => onRowClick && onRowClick(item)}
            >
              {columns.map((column, colIndex) => (
                <td key={colIndex} className="px-4 py-4 whitespace-nowrap text-sm">
                  {column.cell ? column.cell(item) : String(item[column.accessor] || '')}
                </td>
              ))}
            </tr>
          ))}
          
          {sortedData.length === 0 && (
            <tr>
              <td
                colSpan={columns.length}
                className="px-4 py-8 text-center text-gray-500"
              >
                No data available
              </td>
            </tr>
          )}
        </tbody>
      </table>
    </div>
  );
}
